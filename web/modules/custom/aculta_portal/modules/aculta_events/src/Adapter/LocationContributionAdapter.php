<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Adapter;

use Drupal\aculta_cgu\Contract\ContributionAdapterInterface;
use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_events\Access\EventPermissionPolicy;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Drupal\aculta_events\Service\OrganizationContributionEventQueue;
use Drupal\aculta_portal\Domain\DomainSiteManager;
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
 * Validates and applies private proposals for Events-owned locations.
 */
final class LocationContributionAdapter implements ContributionAdapterInterface {

  /**
   * Public, reviewer-selectable location data. */
  private const FIELDS = [
    'title',
    'field_tipo',
    'field_endereco',
    'field_precisao',
    'field_horario',
    'field_descricao',
    'field_telefone',
    'field_site',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?DomainSiteManager $siteManagers,
    private readonly EventPermissionPolicy $permissionPolicy,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly OrganizationContributionEventQueue $eventQueue,
  ) {}

  /**
   * {@inheritdoc} */
  public function id(): string {
    return 'events_location';
  }

  /**
   * {@inheritdoc} */
  public function moduleId(): string {
    return 'aculta_events';
  }

  /**
   * {@inheritdoc} */
  public function purpose(?EntityInterface $target): string {
    return 'eventos';
  }

  /**
   * {@inheritdoc} */
  public function entityAccess(AccountInterface $account, Contribution $contribution, string $operation): AccessResultInterface {
    if ($contribution->get('source_module')->value !== 'aculta_events'
      || $contribution->get('purpose')->value !== 'eventos'
      || !in_array($contribution->get('intent')->value, ['location_update', 'location_create'], TRUE)) {
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
   * {@inheritdoc} */
  public function submitAccess(AccountInterface $account, string $intent, ?EntityInterface $target): bool {
    if (!$account->isAuthenticated() || (int) $account->id() < 1
      || $this->siteManagers?->getCurrentSiteKey()?->value !== 'eventos') {
      return FALSE;
    }
    if ($intent === 'location_create') {
      return $target === NULL && $this->permissionPolicy->canCreateLocation($account);
    }
    return $intent === 'location_update'
      && $target instanceof EventLocation
      && $target->access('update', $account, TRUE)->isAllowed();
  }

  /**
   * {@inheritdoc} */
  public function reviewAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return in_array($intent, ['location_update', 'location_create'], TRUE)
      && $this->siteManagers?->getCurrentSiteKey()?->value === 'main'
      && $reviewer->hasPermission('administer aculta events');
  }

  /**
   * {@inheritdoc} */
  public function applyAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return $this->reviewAccess($reviewer, $intent, $target)
      && (($intent === 'location_create' && $target === NULL)
        || ($intent === 'location_update' && $target instanceof EventLocation));
  }

  /**
   * {@inheritdoc} */
  public function loadTarget(string $intent, ?string $targetUuid): ?EntityInterface {
    if ($intent === 'location_create') {
      return NULL;
    }
    if ($intent !== 'location_update' || $targetUuid === NULL || $targetUuid === '') {
      return NULL;
    }
    $matches = $this->entityTypeManager->getStorage('aculta_event_location')->loadByProperties(['uuid' => $targetUuid]);
    $target = reset($matches);
    return $target instanceof EventLocation ? $target : NULL;
  }

  /**
   * {@inheritdoc} */
  public function validatePayload(string $intent, array $payload, ?EntityInterface $target = NULL): array {
    $validTarget = $intent === 'location_create'
      ? $target === NULL
      : ($intent === 'location_update' && $target instanceof EventLocation);
    if (!$validTarget || $payload === []) {
      throw new UnprocessableEntityHttpException('A proposta de Local não é válida.');
    }
    $normalized = [];
    foreach ($payload as $field => $value) {
      if (!is_string($field) || !in_array($field, self::FIELDS, TRUE)) {
        throw new UnprocessableEntityHttpException('A proposta contém um campo de Local não permitido.');
      }
      if ($field === 'field_endereco') {
        $normalized[$field] = $this->normalizeAddress($value);
        continue;
      }
      if ($field === 'field_tipo') {
        $normalized[$field] = $this->normalizeLocationType($value);
        continue;
      }
      if ($field === 'field_precisao') {
        if (!in_array($value, ['exato', 'aproximado'], TRUE)) {
          throw new UnprocessableEntityHttpException('A precisão do endereço é inválida.');
        }
        $normalized[$field] = $value;
        continue;
      }
      $maxLength = match ($field) {
        'title' => 255,
        'field_telefone' => 32,
        'field_site' => 2048,
        default => 20000,
      };
      if (!is_string($value) || mb_strlen($value) > $maxLength) {
        throw new UnprocessableEntityHttpException('Um dos campos propostos para o Local é inválido.');
      }
      $value = trim($value);
      if ($field === 'field_site' && $value !== '') {
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], TRUE)
          || preg_match('/[\x00-\x20<\x22\x3e\x7f]/', $value) === 1
          || filter_var($value, FILTER_VALIDATE_URL) === FALSE) {
          throw new UnprocessableEntityHttpException('O site do Local precisa ser uma URL HTTP ou HTTPS válida.');
        }
      }
      if ($field === 'title' && $value === '') {
        throw new UnprocessableEntityHttpException('O nome do Local não pode ficar vazio.');
      }
      $normalized[$field] = $value;
    }
    if ($intent === 'location_create' && empty($normalized['title'])) {
      throw new UnprocessableEntityHttpException('A proposta de criação precisa incluir o nome do Local.');
    }
    return $normalized;
  }

  /**
   * {@inheritdoc} */
  public function validateSourceData(string $intent, array $sourceData): array {
    if (!in_array($intent, ['location_update', 'location_create'], TRUE) || $sourceData !== []) {
      throw new UnprocessableEntityHttpException('A proposta contém contexto de origem inválido.');
    }
    return [];
  }

  /**
   * {@inheritdoc} */
  public function validateDecisionData(string $intent, array $selectedFields, array $decisionData): array {
    if (!in_array($intent, ['location_update', 'location_create'], TRUE) || $decisionData !== []) {
      throw new UnprocessableEntityHttpException('A decisão do Local contém opções inválidas.');
    }
    return [];
  }

  /**
   * {@inheritdoc} */
  public function currentVersion(string $intent, ?EntityInterface $target): string {
    if ($intent === 'location_create' && $target === NULL) {
      return 'location:create:v1';
    }
    if ($intent !== 'location_update' || !$target instanceof EventLocation) {
      return '';
    }
    return hash('sha256', json_encode([
      'location' => $target->toArray(),
      'linked_events' => $this->linkedEventContext($target),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
  }

  /**
   * {@inheritdoc} */
  public function recordDecision(Contribution $proposal, string $outcome): void {
    $ownerId = (int) $proposal->getOwnerId();
    if ($ownerId < 1 || !in_array($outcome, OrganizationContributionOutcomeRecordedEvent::OUTCOMES, TRUE)) {
      return;
    }
    $this->eventQueue->enqueue(new OrganizationContributionOutcomeRecordedEvent(
      'events_location',
      (string) $proposal->uuid(),
      (int) $proposal->getRevisionId(),
      $ownerId,
      $outcome,
    ));
  }

  /**
   * Prepares queue storage before the lifecycle opens the decision transaction.
   */

  /**
   * Applies an accepted update or creates a draft Local after review.
   */
  public function applyContribution(Contribution $proposal, string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface {
    if ($intent === 'location_create') {
      return $this->applyCreate($target, $expectedVersion, $selectedPayload, (int) $proposal->getOwnerId(), $decisionData);
    }
    return $this->apply($intent, $target, $expectedVersion, $selectedPayload, $reviewer, $decisionData);
  }

  /**
   * {@inheritdoc} */
  public function apply(string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface {
    if ($intent !== 'location_update' || !$target instanceof EventLocation || $selectedPayload === [] || $decisionData !== []) {
      throw new ConflictHttpException('O Local da proposta não está disponível.');
    }
    $normalized = $this->validatePayload($intent, $selectedPayload, $target);
    $locationId = (int) $target->id();
    $lockName = 'aculta_events.location.' . $locationId;
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new ConflictHttpException('O Local está sendo atualizado. Tente novamente.');
    }
    $transaction = NULL;
    try {
      $transaction = $this->database->startTransaction();
      $select = $this->database->select('aculta_event_location', 'location');
      $select->fields('location', ['id'])->condition('id', $locationId)->forUpdate();
      $select->execute()->fetchField();
      $fresh = $this->entityTypeManager->getStorage('aculta_event_location')->loadUnchanged($locationId);
      if (!$fresh instanceof EventLocation || !hash_equals($expectedVersion, $this->currentVersion($intent, $fresh))) {
        throw new ConflictHttpException('O Local mudou desde o envio da proposta.');
      }
      $candidate = $normalized + [
        'title' => (string) $fresh->label(),
        'field_endereco' => $fresh->get('field_endereco')->getValue(),
      ];
      $this->assertNoDuplicate($candidate, $locationId);
      if (isset($normalized['field_endereco']) || isset($normalized['field_precisao'])) {
        $this->assertLinkedEventsAvailable($fresh, $reviewer);
      }
      $this->applyFields($fresh, $normalized);
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
   * Creates a non-public draft and attributes it to the proposal author.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $target
   *   Must be NULL for a create proposal.
   * @param string $expectedVersion
   *   Stable create sentinel captured at submission.
   * @param array<string, mixed> $selectedPayload
   *   Reviewer-selected public fields.
   * @param int $proponentUid
   *   User ID to attribute as the Location owner.
   * @param array<string, mixed> $decisionData
   *   Adapter-specific choices, which must be empty.
   *
   * @return \Drupal\aculta_events\Entity\EventLocation
   *   The new draft Location.
   */
  private function applyCreate(?EntityInterface $target, string $expectedVersion, array $selectedPayload, int $proponentUid, array $decisionData): EventLocation {
    if ($target !== NULL || $proponentUid < 1 || $decisionData !== [] || !hash_equals('location:create:v1', $expectedVersion)) {
      throw new ConflictHttpException('A proposta de criação do Local não está válida.');
    }
    $normalized = $this->validatePayload('location_create', $selectedPayload);
    if (empty($normalized['title'])) {
      throw new UnprocessableEntityHttpException('Selecione o nome para criar o Local.');
    }
    $lockName = 'aculta_events.location.create';
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new ConflictHttpException('A fila de criação de Locais está sendo processada.');
    }
    $transaction = NULL;
    try {
      $transaction = $this->database->startTransaction();
      $this->assertNoDuplicate($normalized);
      $created = $this->entityTypeManager->getStorage('aculta_event_location')->create([
        'title' => $normalized['title'],
        'field_organizador' => $proponentUid,
        'field_status' => 'rascunho',
        'field_allow_claiming' => FALSE,
      ]);
      if (!$created instanceof EventLocation) {
        throw new \LogicException('The location storage returned an unexpected entity.');
      }
      $location = $created;
      $this->applyFields($location, $normalized);
      $location->set('field_status', 'rascunho');
      $location->save();
      unset($transaction);
      return $location;
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
   * Normalizes a Brazilian address value, dropping person/company fields. */
  private function normalizeAddress(mixed $value): array {
    if (!is_array($value)) {
      throw new UnprocessableEntityHttpException('O endereço do Local é inválido.');
    }
    if ($value === []) {
      return [];
    }
    if (array_is_list($value)) {
      if (count($value) > 1 || !isset($value[0]) || !is_array($value[0])) {
        throw new UnprocessableEntityHttpException('Informe um único endereço para o Local.');
      }
      $value = $value[0];
    }
    $country = strtoupper((string) ($value['country_code'] ?? ''));
    if ($country !== 'BR') {
      throw new UnprocessableEntityHttpException('O endereço do Local deve usar Brasil (BR).');
    }
    $keys = [
      'address_line1' => 255,
      'address_line2' => 255,
      'postal_code' => 32,
      'locality' => 128,
      'administrative_area' => 64,
      'dependent_locality' => 128,
    ];
    $address = ['country_code' => 'BR'];
    foreach ($keys as $key => $maxLength) {
      $part = $value[$key] ?? '';
      if (!is_string($part) || mb_strlen($part) > $maxLength) {
        throw new UnprocessableEntityHttpException('Uma parte do endereço do Local é inválida.');
      }
      $address[$key] = trim($part);
    }
    return [$address];
  }

  /**
   * Validates the selected taxonomy term and returns a reference item. */
  private function normalizeLocationType(mixed $value): array {
    if (is_array($value)) {
      $value = $value['target_id'] ?? $value[0]['target_id'] ?? NULL;
    }
    if ($value === NULL || $value === '') {
      return [];
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $value);
    if ($term === NULL || $term->bundle() !== 'aculta_location_type') {
      throw new UnprocessableEntityHttpException('O tipo de Local selecionado não está disponível.');
    }
    return [['target_id' => (int) $term->id()]];
  }

  /**
   * Applies only normalized allowlisted values. */
  private function applyFields(EventLocation $location, array $values): void {
    foreach ($values as $field => $value) {
      $location->set($field, $value);
    }
  }

  /**
   * Rejects likely duplicate locations without merging records. */
  private function sameLocation(EventLocation $duplicate, array $proposed): bool {
    $existingAddress = $duplicate->get('field_endereco')->first()?->getValue() ?? [];
    $newAddress = $proposed['field_endereco'][0] ?? [];
    $existingPostal = preg_replace('/\D/', '', (string) ($existingAddress['postal_code'] ?? ''));
    $newPostal = preg_replace('/\D/', '', (string) ($newAddress['postal_code'] ?? ''));
    $existingLine = mb_strtolower(trim((string) ($existingAddress['address_line1'] ?? '')));
    $newLine = mb_strtolower(trim((string) ($newAddress['address_line1'] ?? '')));
    $sameTitle = mb_strtolower(trim((string) $duplicate->label())) === mb_strtolower(trim((string) ($proposed['title'] ?? '')));
    if ($newPostal !== '' && $existingPostal !== '' && $newPostal === $existingPostal) {
      return ($newLine !== '' && $existingLine !== '' && $newLine === $existingLine)
        || ($sameTitle && ($newLine === '' || $existingLine === ''));
    }
    $sameAddress = $newLine !== '' && $existingLine === $newLine
      && mb_strtolower((string) ($existingAddress['locality'] ?? '')) === mb_strtolower((string) ($newAddress['locality'] ?? ''));
    return $sameAddress || ($sameTitle && ($newPostal === '' || $existingPostal === ''));
  }

  /**
   * Stops near duplicate creation or edits without merging existing records.
   *
   * @param array<string, mixed> $proposed
   *   Candidate public name and postal address.
   * @param int|null $excludedId
   *   Existing source entity ID, if this is an update.
   */
  private function assertNoDuplicate(array $proposed, ?int $excludedId = NULL): void {
    $storage = $this->entityTypeManager->getStorage('aculta_event_location');
    $query = $storage->getQuery()->accessCheck(FALSE)->range(0, 50);
    $conditions = $query->orConditionGroup();
    if (isset($proposed['title']) && is_string($proposed['title']) && trim($proposed['title']) !== '') {
      $conditions->condition('title', trim($proposed['title']));
    }
    $address = $proposed['field_endereco'][0] ?? [];
    $postalCode = is_array($address) ? preg_replace('/\D/', '', (string) ($address['postal_code'] ?? '')) : '';
    if ($postalCode !== '') {
      $conditions->condition('field_endereco.postal_code', $postalCode);
    }
    $query->condition($conditions);
    foreach ($storage->loadMultiple($query->execute()) as $duplicate) {
      if (!$duplicate instanceof EventLocation || (int) $duplicate->id() === $excludedId) {
        continue;
      }
      if ($this->sameLocation($duplicate, $proposed)) {
        throw new ConflictHttpException('Já existe um Local com nome e endereço semelhantes. Revise o cadastro existente antes de criar ou alterar.');
      }
    }
  }

  /**
   * Captures related dates and Event status in the Location base version.
   *
   * Changing a Location address can change information shown by every Event
   * using it. Those dependencies therefore participate in stale-source
   * detection when a reviewer applies an address or precision change.
   *
   * @return array<int, array<string, bool|int|string|null>>
   *   Stable, entity-ID-sorted context for Events that reference this Location.
   */
  private function linkedEventContext(EventLocation $location): array {
    $dateStorage = $this->entityTypeManager->getStorage('aculta_event_date');
    $dateIds = $dateStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_local', (int) $location->id())
      ->sort('id')
      ->execute();
    $context = [];
    foreach ($dateIds as $dateId) {
      $date = $dateStorage->loadUnchanged($dateId);
      if (!$date instanceof EventDate) {
        continue;
      }
      $eventId = $date->get('field_evento')->isEmpty()
        ? NULL
        : (int) $date->get('field_evento')->target_id;
      $event = $eventId === NULL
        ? NULL
        : $this->entityTypeManager->getStorage('aculta_event')->loadUnchanged($eventId);
      $range = $date->get('field_quando')->first()?->getValue() ?? [];
      $context[] = [
        'date_id' => (int) $date->id(),
        'event_id' => $eventId,
        'event_exists' => $event instanceof Event,
        'event_status' => $event instanceof Event ? (string) $event->get('field_status')->value : NULL,
        'event_published' => $event instanceof Event && $event->get('field_status')->value === 'publicado',
        'starts_at' => isset($range['value']) ? (string) $range['value'] : NULL,
        'ends_at' => isset($range['end_value']) ? (string) $range['end_value'] : NULL,
      ];
    }
    usort($context, static fn (array $left, array $right): int => $left['date_id'] <=> $right['date_id']);
    return $context;
  }

  /**
   * Fails closed if a sensitive Location update affects an unavailable Event.
   */
  private function assertLinkedEventsAvailable(EventLocation $location, AccountInterface $reviewer): void {
    foreach ($this->linkedEventContext($location) as $context) {
      if (!$context['event_exists'] || $context['event_id'] === NULL) {
        throw new ConflictHttpException('Um Evento vinculado ao Local não está mais disponível.');
      }
      $event = $this->entityTypeManager->getStorage('aculta_event')->loadUnchanged($context['event_id']);
      $this->entityTypeManager->getAccessControlHandler('aculta_event')->resetCache();
      if (!$event instanceof Event || !$event->access('view', $reviewer, TRUE)->isAllowed()) {
        throw new ConflictHttpException('Um Evento vinculado ao Local não está acessível para revisão.');
      }
    }
  }

}
