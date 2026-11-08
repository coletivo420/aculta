<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Adapter;

use Drupal\aculta_cgu\Contract\ContributionAdapterInterface;
use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_events\Access\EventPermissionPolicy;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Drupal\aculta_events\Slug\EventSlugGenerator;
use Drupal\aculta_events\Service\OrganizationContributionEventQueue;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Contribution policy and applier for public profiles of unclaimed groups.
 */
final class OrganizationContributionAdapter implements ContributionAdapterInterface {

  /**
   * Public profile fields; ownership and editorial metadata are excluded. */
  private const FIELDS = ['title', 'body', 'imagem', 'links', 'localidade', 'uf', 'ocultar_localidade'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?DomainPurposeManager $domainPurposes,
    private readonly EventPermissionPolicy $permissionPolicy,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly OrganizationContributionEventQueue $eventQueue,
  ) {}

  /**
   * Returns this registered adapter's stable identifier.
   */
  public function id(): string {
    return 'events_organization';
  }

  /**
   * Returns the domain module that owns organization records.
   */
  public function moduleId(): string {
    return 'aculta_events';
  }

  /**
   * Returns the canonical purpose for organization contributions.
   */
  public function purpose(?EntityInterface $target): string {
    return 'eventos';
  }

  /**
   * Restricts proposal access to its author and Events administrators.
   */
  public function entityAccess(AccountInterface $account, Contribution $contribution, string $operation): AccessResultInterface {
    if ($contribution->get('source_module')->value !== 'aculta_events'
      || $contribution->get('purpose')->value !== 'eventos'
      || !in_array($contribution->get('intent')->value, ['organization_update', 'organization_create'], TRUE)) {
      return AccessResult::forbidden()->cachePerUser()->cachePerPermissions();
    }
    if ($account->hasPermission('administer aculta events')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if ((int) $contribution->getOwnerId() === (int) $account->id()
      && (in_array($operation, ['view', 'view label', 'view revision'], TRUE)
        || ($operation === 'update' && $contribution->get('state')->value === 'needs_changes'))) {
      return AccessResult::allowed()->cachePerUser();
    }
    return AccessResult::forbidden()->cachePerUser()->cachePerPermissions();
  }

  /**
   * Checks authenticated Events-purpose access to an unclaimed organization.
   */
  public function submitAccess(AccountInterface $account, string $intent, ?EntityInterface $target): bool {
    return $account->isAuthenticated()
      && (int) $account->id() > 0
      && $this->domainPurposes?->getCurrentPurpose() === 'eventos'
      && $this->permissionPolicy->canCreateEvent($account)
      && (($intent === 'organization_update' && $this->eligibleTarget($target))
        || ($intent === 'organization_create' && $target === NULL));
  }

  /**
   * Checks administrative review access from the main purpose.
   */
  public function reviewAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return in_array($intent, ['organization_update', 'organization_create'], TRUE)
      && $this->domainPurposes?->getCurrentPurpose() === 'main'
      && $reviewer->hasPermission('administer aculta events');
  }

  /**
   * Rechecks review authorization and target eligibility before application.
   */
  public function applyAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return $this->reviewAccess($reviewer, $intent, $target)
      && (($intent === 'organization_update' && $this->eligibleTarget($target))
        || ($intent === 'organization_create' && $target === NULL));
  }

  /**
   * Resolves only existing organization targets by UUID.
   */
  public function loadTarget(string $intent, ?string $targetUuid): ?EntityInterface {
    if ($intent === 'organization_create') {
      return NULL;
    }
    if ($intent !== 'organization_update' || $targetUuid === NULL || $targetUuid === '') {
      return NULL;
    }
    $matches = $this->entityTypeManager->getStorage('aculta_event_organization')->loadByProperties(['uuid' => $targetUuid]);
    $target = reset($matches);
    return $target instanceof EventOrganization ? $target : NULL;
  }

  /**
   * Normalizes only the explicitly allowlisted public profile fields.
   */
  public function validatePayload(string $intent, array $payload, ?EntityInterface $target = NULL): array {
    $validTarget = $intent === 'organization_create'
      ? $target === NULL
      : ($intent === 'organization_update' && $this->eligibleTarget($target));
    if (!$validTarget || $payload === []) {
      throw new UnprocessableEntityHttpException('A proposta de organização não é válida.');
    }
    $normalized = [];
    foreach ($payload as $field => $value) {
      if (!is_string($field) || !in_array($field, self::FIELDS, TRUE)) {
        throw new UnprocessableEntityHttpException('A proposta contém um campo não permitido.');
      }
      if ($field === 'ocultar_localidade') {
        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1'], TRUE)) {
          throw new UnprocessableEntityHttpException('A opção de privacidade da localidade é inválida.');
        }
        $normalized[$field] = (bool) $value;
        continue;
      }
      if (!is_string($value) || mb_strlen($value) > ($field === 'imagem' ? 512 : ($field === 'title' ? 255 : 20000))) {
        throw new UnprocessableEntityHttpException('Um dos campos propostos é inválido.');
      }
      $normalized[$field] = trim($value);
    }
    if (isset($normalized['title']) && $normalized['title'] === '') {
      throw new UnprocessableEntityHttpException('O nome da organização não pode ficar vazio.');
    }
    if ($intent === 'organization_create' && empty($normalized['title'])) {
      throw new UnprocessableEntityHttpException('A proposta de criação precisa incluir o nome da organização.');
    }
    return $normalized;
  }

  /**
   * Rejects private source context for organization profile proposals.
   */
  public function validateSourceData(string $intent, array $sourceData): array {
    if (!in_array($intent, ['organization_update', 'organization_create'], TRUE) || $sourceData !== []) {
      throw new UnprocessableEntityHttpException('A proposta contém contexto de origem inválido.');
    }
    return [];
  }

  /**
   * Rejects adapter-specific decision data for this proposal type.
   */
  public function validateDecisionData(string $intent, array $selectedFields, array $decisionData): array {
    if (!in_array($intent, ['organization_update', 'organization_create'], TRUE) || $decisionData !== []) {
      throw new UnprocessableEntityHttpException('A decisão da organização contém opções inválidas.');
    }
    return [];
  }

  /**
   * Fingerprints persisted organization state for stale proposal detection.
   */
  public function currentVersion(string $intent, ?EntityInterface $target): string {
    if ($intent === 'organization_create' && $target === NULL) {
      return 'organization:create:v1';
    }
    if ($intent !== 'organization_update' || !$target instanceof EventOrganization) {
      return '';
    }
    // Match the legacy suggestion contract: any persisted change to the
    // organization makes an outstanding proposal stale, including claim,
    // publication, slug, and membership-related profile changes.
    return hash('sha256', json_encode(
      $target->toArray(),
      JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
    ));
  }

  /**
   * Enqueues a minimal outcome event after the decision is recorded.
   */
  public function recordDecision(Contribution $proposal, string $outcome): void {
    $ownerId = (int) $proposal->getOwnerId();
    if ($ownerId < 1 || !in_array($outcome, OrganizationContributionOutcomeRecordedEvent::OUTCOMES, TRUE)) {
      return;
    }
    $this->eventQueue->enqueue(new OrganizationContributionOutcomeRecordedEvent(
      'events_organization',
      (string) $proposal->uuid(),
      (int) $proposal->getRevisionId(),
      $ownerId,
      $outcome,
    ));
  }

  /**
   * Prepares queue storage before the lifecycle opens its transaction.
   */

  /**
   * Applies a decision with access to the persisted proposal author.
   */
  public function applyContribution(Contribution $proposal, string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface {
    if ($intent === 'organization_create') {
      return $this->applyCreate($target, $expectedVersion, $selectedPayload, (int) $proposal->getOwnerId(), $decisionData);
    }
    return $this->apply($intent, $target, $expectedVersion, $selectedPayload, $reviewer, $decisionData);
  }

  /**
   * Applies reviewer-selected fields while holding source locks.
   *
   * @param string $intent
   *   Adapter intent being applied.
   * @param \Drupal\Core\Entity\EntityInterface|null $target
   *   Existing organization for update intents, or NULL for creation.
   * @param string $expectedVersion
   *   Captured source fingerprint or the create sentinel.
   * @param array<string, mixed> $selectedPayload
   *   Reviewer-selected and allowlisted profile fields.
   * @param \Drupal\Core\Session\AccountInterface $reviewer
   *   Authorized reviewer making the decision.
   * @param array<string, mixed> $decisionData
   *   Adapter-specific decision data, which must be empty.
   *
   * @return \Drupal\aculta_events\Entity\EventOrganization
   *   The updated or newly created organization.
   */
  public function apply(string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface {
    if ($intent !== 'organization_update' || !$target instanceof EventOrganization
      || $selectedPayload === [] || $decisionData !== []) {
      throw new ConflictHttpException('A organização da proposta não está disponível.');
    }
    $normalized = $this->validatePayload($intent, $selectedPayload, $target);
    $organizationId = (int) $target->id();
    $lockName = 'aculta_events.organization.' . $organizationId;
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new ConflictHttpException('A organização está sendo atualizada. Tente novamente.');
    }
    $transaction = NULL;
    try {
      $transaction = $this->database->startTransaction();
      $this->database->select('aculta_event_organization', 'organization')
        ->fields('organization', ['id'])
        ->condition('id', $organizationId)
        ->forUpdate()
        ->execute()
        ->fetchField();
      $fresh = $this->entityTypeManager->getStorage('aculta_event_organization')->loadUnchanged($organizationId);
      if (!$fresh instanceof EventOrganization || !$fresh->isUnclaimed()
        || !hash_equals($expectedVersion, $this->currentVersion($intent, $fresh))) {
        throw new ConflictHttpException('A organização mudou ou foi reivindicada desde o envio.');
      }
      foreach ($normalized as $field => $value) {
        $fresh->set($field, $value);
      }
      $fresh->save();
      unset($transaction);
      return $fresh;
    }
    catch (\Throwable $exception) {
      $transaction?->rollBack();
      throw $exception;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Creates a public unclaimed organization after administrative approval.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $target
   *   Must be NULL for a creation proposal.
   * @param string $expectedVersion
   *   Stable create sentinel captured at submission.
   * @param array<string, mixed> $selectedPayload
   *   Reviewer-selected profile fields including the required title.
   * @param int $proponentUid
   *   User ID attributed as the organization creator.
   * @param array<string, mixed> $decisionData
   *   Adapter-specific decision data, which must be empty.
   *
   * @return \Drupal\aculta_events\Entity\EventOrganization
   *   The newly created organization.
   */
  private function applyCreate(?EntityInterface $target, string $expectedVersion, array $selectedPayload, int $proponentUid, array $decisionData): EventOrganization {
    if ($target !== NULL || $proponentUid < 1 || $decisionData !== []
      || !hash_equals('organization:create:v1', $expectedVersion)) {
      throw new ConflictHttpException('A proposta de criação da organização não está válida.');
    }
    $normalized = $this->validatePayload('organization_create', $selectedPayload);
    if (empty($normalized['title'])) {
      throw new UnprocessableEntityHttpException('Selecione o nome da organização para aplicar a criação.');
    }
    $lockName = 'aculta_events.organization.create';
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new ConflictHttpException('A fila de criação de organizações está sendo processada.');
    }
    $transaction = NULL;
    try {
      $transaction = $this->database->startTransaction();
      $duplicates = $this->entityTypeManager->getStorage('aculta_event_organization')->getQuery()
        ->accessCheck(FALSE)
        ->condition('title', $normalized['title'])
        ->range(0, 1)
        ->execute();
      if ($duplicates !== []) {
        throw new ConflictHttpException('Já existe uma organização com este nome; revise o cadastro existente antes de aplicar.');
      }
      $slug = $this->uniqueOrganizationSlug($normalized['title']);
      $values = $normalized + [
        'body' => '',
        'imagem' => '',
        'links' => '',
        'localidade' => '',
        'uf' => '',
        'ocultar_localidade' => FALSE,
      ];
      $organization = $this->entityTypeManager->getStorage('aculta_event_organization')->create([
        'title' => $values['title'],
        'field_slug' => $slug,
        'body' => $values['body'],
        'imagem' => $values['imagem'],
        'links' => $values['links'],
        'localidade' => $values['localidade'],
        'uf' => strtoupper($values['uf']),
        'ocultar_localidade' => $values['ocultar_localidade'],
        'field_status' => 'publicado',
        'status' => 1,
        'reivindicada' => FALSE,
        'reivindicacao_status' => 'nao_reivindicada',
        'titular' => NULL,
        'pending_owner_uid' => NULL,
        'field_organizador' => $proponentUid,
      ]);
      if (!$organization instanceof EventOrganization) {
        throw new \LogicException('Organization storage returned an unexpected entity.');
      }
      $organization->save();
      unset($transaction);
      return $organization;
    }
    catch (\Throwable $exception) {
      $transaction?->rollBack();
      throw $exception;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Generates a unique organization slug while preserving existing slugs.
   *
   * @param string $title
   *   Organization title used to generate the slug.
   *
   * @return string
   *   A unique slug.
   */
  private function uniqueOrganizationSlug(string $title): string {
    $base = EventSlugGenerator::fromTitle($title);
    $candidate = $base;
    $suffix = 2;
    $storage = $this->entityTypeManager->getStorage('aculta_event_organization');
    while ($storage->getQuery()->accessCheck(FALSE)->condition('field_slug', $candidate)->range(0, 1)->execute() !== []) {
      $candidate = $base . '-' . $suffix++;
    }
    return $candidate;
  }

  /**
   * Returns whether the target remains an existing unclaimed organization.
   */
  private function eligibleTarget(?EntityInterface $target): bool {
    return $target instanceof EventOrganization && !$target->isNew() && $target->isUnclaimed();
  }

}
