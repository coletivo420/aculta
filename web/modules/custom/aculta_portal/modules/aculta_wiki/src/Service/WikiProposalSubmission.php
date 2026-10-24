<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Creates private proposals against the published Wiki revision.
 */
final class WikiProposalSubmission {

  private const FLOOD_EVENT = 'aculta_wiki.proposal_submission';

  private const FLOOD_LIMIT = 10;

  private const FLOOD_WINDOW = 3600;

  private const IDEMPOTENCY_TTL = 86400;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly EmailConfirmationPolicy $emailConfirmationPolicy,
    private readonly FloodInterface $flood,
    private readonly LockBackendInterface $lock,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
    private readonly Connection $database,
  ) {}

  /**
   * Persists a proposal after revalidating target and contributor access.
   *
   * @param \Drupal\node\NodeInterface $target
   *   Current target Wiki entry.
   * @param string $intent
   *   Allowlisted proposal intent.
   * @param string $proposedText
   *   Human-readable summary of the contribution.
   * @param string $rationale
   *   Reason the contribution improves the entry.
   * @param list<string> $sourceUris
   *   Public HTTP(S) sources.
   * @param string|null $idempotencyToken
   *   Optional form submission token.
   * @param array<string, mixed> $proposedFields
   *   Optional structured values for allowlisted Wiki editorial fields.
   * @param int|null $expectedTargetRevisionId
   *   Optional default revision captured when the contribution form opened.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When the actor, target, or current Wiki purpose is not authorized.
   * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
   *   When the target changed after the contributor opened the form.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   When submitted fields do not meet the proposal contract.
   */
  public function submit(
    NodeInterface $target,
    string $intent,
    string $proposedText,
    string $rationale,
    array $sourceUris = [],
    ?string $idempotencyToken = NULL,
    array $proposedFields = [],
    ?int $expectedTargetRevisionId = NULL,
  ): WikiProposal {
    $this->assertCanSubmit();

    // Route/controller entities may be stale after a concurrent publication or
    // withdrawal. Always bind the proposal to the current default revision.
    $target = $this->entityTypeManager->getStorage('node')->loadUnchanged($target->id());
    if (!$target instanceof NodeInterface) {
      throw new AccessDeniedHttpException();
    }
    if ($expectedTargetRevisionId !== NULL && (int) $target->getRevisionId() !== $expectedTargetRevisionId) {
      throw new ConflictHttpException('O verbete mudou enquanto você preparava a proposta. Reabra a contribuição para revisar a versão atual.');
    }

    $wikiDomain = $this->domainPurposes->getDomain('wiki');
    if ($this->domainPurposes->getCurrentPurpose() !== 'wiki'
      || !$wikiDomain
      || $target->bundle() !== 'wiki_entry'
      || !$target->isDefaultRevision()
      || !$target->isPublished()
      || !$target->access('view', $this->currentUser)
      || $target->access('update', $this->currentUser)
      || !$target->hasField('field_domain_source')
      || $target->get('field_domain_source')->target_id !== $wikiDomain->id()) {
      throw new AccessDeniedHttpException();
    }

    if (!isset(WikiProposal::INTENTS[$intent])) {
      throw new UnprocessableEntityHttpException('Tipo de contribuição inválido.');
    }
    $proposedText = trim($proposedText);
    $rationale = trim($rationale);
    if ($proposedText === '' || mb_strlen($proposedText) > 20000
      || $rationale === '' || mb_strlen($rationale) > 5000) {
      throw new UnprocessableEntityHttpException('Texto da proposta ou justificativa inválidos.');
    }

    if (count($sourceUris) > 10) {
      throw new UnprocessableEntityHttpException('A proposta aceita até dez fontes.');
    }
    $validatedSources = [];
    foreach ($sourceUris as $sourceUri) {
      if (!is_string($sourceUri)) {
        throw new UnprocessableEntityHttpException('A fonte deve ser uma URL HTTP ou HTTPS válida.');
      }
      $sourceUri = trim($sourceUri);
      $scheme = strtolower((string) parse_url($sourceUri, PHP_URL_SCHEME));
      $isValidUrl = filter_var($sourceUri, FILTER_VALIDATE_URL) !== FALSE;
      $isSafeScheme = in_array($scheme, ['http', 'https'], TRUE);
      if ($sourceUri === '' || mb_strlen($sourceUri) > 2048 || !$isValidUrl || !$isSafeScheme) {
        throw new UnprocessableEntityHttpException('A fonte deve usar uma URL HTTP ou HTTPS válida.');
      }
      $validatedSources[] = $sourceUri;
    }
    $validatedSources = array_values(array_unique($validatedSources));
    $validatedProposedFields = $this->validateProposedFields($proposedFields, $target, $wikiDomain);

    $idempotencyToken ??= bin2hex(random_bytes(32));
    if (!preg_match('/^[a-f0-9]{64}$/D', $idempotencyToken)) {
      throw new UnprocessableEntityHttpException('Token de envio inválido. Recarregue o formulário.');
    }
    $identifier = (string) $this->currentUser->id();
    $idempotencyKey = hash('sha256', $identifier . ':' . $idempotencyToken);
    $lockName = 'aculta_wiki.proposal_submission:' . $idempotencyKey;
    if (!$this->lock->acquire($lockName, 30.0)) {
      $this->lock->wait($lockName, 5);
      if (!$this->lock->acquire($lockName, 30.0)) {
        throw new TooManyRequestsHttpException(5, 'Este envio ainda está sendo processado. Tente novamente em alguns segundos.');
      }
    }

    $store = $this->keyValueExpirableFactory->get('aculta_wiki_proposal_submissions');
    try {
      $existingId = $store->get($idempotencyKey);
      if (is_numeric($existingId)) {
        $existing = $this->entityTypeManager->getStorage('aculta_wiki_proposal')->load((int) $existingId);
        if ($existing instanceof WikiProposal && (int) $existing->getOwnerId() === (int) $identifier) {
          return $existing;
        }
        $store->delete($idempotencyKey);
      }

      if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::FLOOD_LIMIT, self::FLOOD_WINDOW, $identifier)) {
        throw new TooManyRequestsHttpException(self::FLOOD_WINDOW, 'O limite temporário de propostas foi atingido. Tente novamente mais tarde.');
      }

      $intentLabel = WikiProposal::INTENTS[$intent];
      $subjectSuffix = ' — ' . $intentLabel;
      $subject = mb_substr(trim($target->label()), 0, 255 - mb_strlen($subjectSuffix)) . $subjectSuffix;
      $proposal = $this->entityTypeManager->getStorage('aculta_wiki_proposal')->create([
        'uid' => $this->currentUser->id(),
        'subject' => $subject,
        'target' => $target->id(),
        'target_revision_id' => $target->getRevisionId(),
        'intent' => $intent,
        'proposed_text' => $proposedText,
        'proposed_fields' => $validatedProposedFields,
        'rationale' => $rationale,
        'source_uri' => array_map(static fn (string $sourceUri): array => ['value' => $sourceUri], $validatedSources),
        'state' => 'submitted',
        'revision_log' => 'Proposta enviada',
      ]);
      if (!$proposal instanceof WikiProposal) {
        throw new \LogicException('Wiki proposal storage returned an unexpected entity.');
      }
      $proposal->setNewRevision(TRUE);
      $proposal->isDefaultRevision(TRUE);
      $transaction = $this->database->startTransaction();
      try {
        $proposal->save();
        $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW, $identifier);
        $store->setWithExpire($idempotencyKey, (int) $proposal->id(), self::IDEMPOTENCY_TTL);
        unset($transaction);
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }

      return $proposal;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Validates the structured, allowlisted Wiki field values proposed by a user.
   *
   * @param array<string, mixed> $proposedFields
   *   Proposed field names and values.
   * @param \Drupal\node\NodeInterface $target
   *   Current published Wiki entry.
   * @param \Drupal\domain\DomainInterface $wikiDomain
   *   Wiki domain resolved by the Portal.
   *
   * @return array<string, mixed>
   *   Normalized values safe to persist in the private proposal entity.
   */
  private function validateProposedFields(array $proposedFields, NodeInterface $target, DomainInterface $wikiDomain): array {
    if (array_diff(array_keys($proposedFields), array_keys(WikiProposal::EDITORIAL_FIELDS)) !== []) {
      throw new UnprocessableEntityHttpException('A proposta contém um campo não permitido.');
    }

    $validated = [];
    foreach ($proposedFields as $fieldName => $value) {
      switch ($fieldName) {
        case 'title':
          if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 255) {
            throw new UnprocessableEntityHttpException('O título proposto deve ter entre 1 e 255 caracteres.');
          }
          $validated[$fieldName] = ['value' => trim($value)];
          break;

        case 'field_wiki_summary':
          $validated[$fieldName] = $this->validateFormattedText($value, 10000, 'resumo');
          break;

        case 'body':
          if (!is_array($value) || !isset($value['value']) || !is_string($value['value'])) {
            throw new UnprocessableEntityHttpException('O corpo proposto é inválido.');
          }
          $validated[$fieldName] = $this->validateFormattedText($value, 100000, 'corpo', TRUE);
          break;

        case 'field_wiki_references':
          if (!is_array($value) || count($value) > 50) {
            throw new UnprocessableEntityHttpException('A proposta aceita até 50 referências.');
          }
          $references = [];
          foreach ($value as $reference) {
            if (!is_array($reference) || !isset($reference['uri']) || !is_string($reference['uri'])) {
              throw new UnprocessableEntityHttpException('Cada referência precisa de uma URL HTTP ou HTTPS válida.');
            }
            $uri = trim($reference['uri']);
            $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
            if (!filter_var($uri, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], TRUE)) {
              throw new UnprocessableEntityHttpException('Cada referência precisa de uma URL HTTP ou HTTPS válida.');
            }
            $title = isset($reference['title']) && is_string($reference['title']) ? trim($reference['title']) : '';
            if (mb_strlen($title) > 255) {
              throw new UnprocessableEntityHttpException('O título de uma referência excede 255 caracteres.');
            }
            $references[$uri] = ['uri' => $uri, 'title' => $title];
          }
          $validated[$fieldName] = array_values($references);
          break;

        case 'field_wiki_categories':
          $validated[$fieldName] = $this->validateTermReferences($value);
          break;

        case 'field_wiki_related':
          $validated[$fieldName] = $this->validateRelatedEntries($value, $target, $wikiDomain);
          break;

        case 'field_wiki_image':
          if ($value === NULL || $value === '') {
            $validated[$fieldName] = NULL;
            break;
          }
          $media = $this->entityTypeManager->getStorage('media')->load((int) $value);
          if (!$media instanceof MediaInterface || $media->bundle() !== 'image'
            || !$media->isPublished() || !$media->access('view', $this->currentUser)) {
            throw new UnprocessableEntityHttpException('A imagem proposta não está disponível para uso.');
          }
          $validated[$fieldName] = (int) $media->id();
          break;
      }
    }
    return $validated;
  }

  /**
   * Validates a text field value and the author's access to its text format.
   *
   * @param mixed $value
   *   Raw field payload.
   * @param int $limit
   *   Maximum text length.
   * @param string $label
   *   Human-readable field label for errors.
   * @param bool $hasSummary
   *   Whether the field stores a separate summary.
   *
   * @return array{value: string, format: string, summary?: string}
   *   Normalized formatted text.
   */
  private function validateFormattedText(mixed $value, int $limit, string $label, bool $hasSummary = FALSE): array {
    if (!is_array($value) || !isset($value['value']) || !is_string($value['value'])) {
      throw new UnprocessableEntityHttpException('O ' . $label . ' proposto é inválido.');
    }
    $text = trim($value['value']);
    $format = isset($value['format']) && is_string($value['format']) ? $value['format'] : 'wiki';
    if ($text === '' || mb_strlen($text) > $limit || !in_array($format, ['wiki', 'plain_text'], TRUE)) {
      throw new UnprocessableEntityHttpException('O ' . $label . ' proposto ou seu formato são inválidos.');
    }
    $formatEntity = $this->entityTypeManager->getStorage('filter_format')->load($format);
    if ($formatEntity === NULL || !$formatEntity->access('use', $this->currentUser)) {
      throw new AccessDeniedHttpException();
    }
    $normalized = ['value' => $text, 'format' => $format];
    if ($hasSummary) {
      $summary = isset($value['summary']) && is_string($value['summary']) ? trim($value['summary']) : '';
      if (mb_strlen($summary) > 512) {
        throw new UnprocessableEntityHttpException('O resumo do corpo não pode exceder 512 caracteres.');
      }
      $normalized['summary'] = $summary;
    }
    return $normalized;
  }

  /**
   * Validates taxonomy references against the Wiki category vocabulary.
   *
   * @return list<int>
   *   Unique, readable term IDs.
   */
  private function validateTermReferences(mixed $value): array {
    if (!is_array($value) || count($value) > 100) {
      throw new UnprocessableEntityHttpException('A proposta aceita até 100 categorias.');
    }
    $ids = [];
    foreach ($value as $item) {
      $id = is_array($item) ? ($item['target_id'] ?? NULL) : $item;
      if (!is_numeric($id)) {
        throw new UnprocessableEntityHttpException('Uma categoria proposta é inválida.');
      }
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $id);
      if (!$term instanceof TermInterface || $term->bundle() !== 'wiki_category' || !$term->access('view', $this->currentUser)) {
        throw new UnprocessableEntityHttpException('Uma categoria proposta não está disponível para uso.');
      }
      $ids[] = (int) $term->id();
    }
    return array_values(array_unique($ids));
  }

  /**
   * Validates related entry references in the current Wiki domain.
   *
   * @return list<int>
   *   Unique published Wiki node IDs.
   */
  private function validateRelatedEntries(mixed $value, NodeInterface $target, DomainInterface $wikiDomain): array {
    if (!is_array($value) || count($value) > 100) {
      throw new UnprocessableEntityHttpException('A proposta aceita até 100 verbetes relacionados.');
    }
    $ids = [];
    foreach ($value as $item) {
      $id = is_array($item) ? ($item['target_id'] ?? NULL) : $item;
      if (!is_numeric($id) || (int) $id === (int) $target->id()) {
        throw new UnprocessableEntityHttpException('Um verbete relacionado proposto é inválido.');
      }
      $related = $this->entityTypeManager->getStorage('node')->load((int) $id);
      if (!$related instanceof NodeInterface || $related->bundle() !== 'wiki_entry' || !$related->isDefaultRevision()
        || !$related->isPublished() || !$related->hasField('field_domain_source')
        || $related->get('field_domain_source')->target_id !== $wikiDomain->id()
        || !$related->access('view', $this->currentUser)) {
        throw new UnprocessableEntityHttpException('Um verbete relacionado não está disponível para uso.');
      }
      $ids[] = (int) $related->id();
    }
    return array_values(array_unique($ids));
  }

  /**
   * Checks authentication, confirmed e-mail, and proposal-create permission.
   *
   * This check also protects the GET form so an unconfirmed account does not
   * receive an unusable submission screen.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When the current account cannot submit proposals.
   */
  public function assertCanSubmit(): void {
    if (!$this->currentUser->isAuthenticated() || (int) $this->currentUser->id() < 1) {
      throw new AccessDeniedHttpException();
    }
    if (!$this->emailConfirmationPolicy->isConfirmed($this->currentUser)) {
      throw new AccessDeniedHttpException();
    }
    $access = $this->entityTypeManager->getAccessControlHandler('aculta_wiki_proposal')
      ->createAccess(NULL, $this->currentUser, [], TRUE);
    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
  }

}
