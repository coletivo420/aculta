<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Creates private proposals against the published Wiki revision.
 */
final class WikiProposalSubmission {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly DomainPurposeManager $domainPurposes,
  ) {}

  /**
   * Persists a proposal after revalidating target and contributor access.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When the actor, target, or current Wiki purpose is not authorized.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   When submitted fields do not meet the proposal contract.
   */
  public function submit(
    NodeInterface $target,
    string $intent,
    string $proposedText,
    string $rationale,
    array $sourceUris = [],
  ): WikiProposal {
    if (!$this->currentUser->isAuthenticated() || (int) $this->currentUser->id() < 1) {
      throw new AccessDeniedHttpException();
    }
    $access = $this->entityTypeManager->getAccessControlHandler('aculta_wiki_proposal')
      ->createAccess(NULL, $this->currentUser, [], TRUE);
    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    // Route/controller entities may be stale after a concurrent publication or
    // withdrawal. Always bind the proposal to the current default revision.
    $target = $this->entityTypeManager->getStorage('node')->loadUnchanged($target->id());
    if (!$target instanceof NodeInterface) {
      throw new AccessDeniedHttpException();
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
    $proposal->save();

    return $proposal;
  }

}
