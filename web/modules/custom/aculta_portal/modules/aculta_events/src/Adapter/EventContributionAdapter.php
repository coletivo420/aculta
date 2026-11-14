<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Adapter;

use Drupal\aculta_cgu\Contract\ContributionAdapterInterface;
use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_events\Access\EventPermissionPolicy;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Drupal\aculta_events\Service\OrganizationContributionEventQueue;
use Drupal\aculta_events\Service\WebsiteUrl;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_user\Organization\OrganizationAccessService;
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
 * Domain policy and selective applier for community Event proposals.
 *
 * Date proposals are append-only. Existing Dates are never removed or replaced
 * by this adapter, and a create proposal always produces an unpublished draft.
 */
final class EventContributionAdapter implements ContributionAdapterInterface {

  /**
   * Public editorial properties; workflow, owner and publication are excluded. */
  private const FIELDS = [
    'title',
    'field_subtitulo',
    'body',
    'field_url',
    'field_organizacao',
    'field_coorganizadores',
    'dates',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?DomainSiteManager $siteManagers,
    private readonly EventPermissionPolicy $permissionPolicy,
    private readonly ?OrganizationAccessService $organizationAccess,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly OrganizationContributionEventQueue $eventQueue,
  ) {}

  /**
   * {@inheritdoc} */
  public function id(): string {
    return 'events_event';
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
      || !in_array($contribution->get('intent')->value, ['event_create', 'event_update'], TRUE)) {
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
    if ($intent === 'event_create') {
      return $target === NULL && $this->permissionPolicy->canCreateEvent($account);
    }
    return $intent === 'event_update'
      && $target instanceof Event
      && $target->access('update', $account, TRUE)->isAllowed();
  }

  /**
   * {@inheritdoc} */
  public function reviewAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return in_array($intent, ['event_create', 'event_update'], TRUE)
      && $this->siteManagers?->getCurrentSiteKey()?->value === 'main'
      && $reviewer->hasPermission('administer aculta events');
  }

  /**
   * {@inheritdoc} */
  public function applyAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return $this->reviewAccess($reviewer, $intent, $target)
      && (($intent === 'event_create' && $target === NULL)
        || ($intent === 'event_update' && $target instanceof Event));
  }

  /**
   * {@inheritdoc} */
  public function loadTarget(string $intent, ?string $targetUuid): ?EntityInterface {
    if ($intent === 'event_create') {
      return NULL;
    }
    if ($intent !== 'event_update' || !$targetUuid) {
      return NULL;
    }
    $matches = $this->entityTypeManager->getStorage('aculta_event')->loadByProperties(['uuid' => $targetUuid]);
    $event = reset($matches);
    return $event instanceof Event ? $event : NULL;
  }

  /**
   * {@inheritdoc} */
  public function validatePayload(string $intent, array $payload, ?EntityInterface $target = NULL): array {
    if (!(($intent === 'event_create' && $target === NULL)
      || ($intent === 'event_update' && $target instanceof Event)) || $payload === []) {
      throw new UnprocessableEntityHttpException('A proposta de Evento não é válida.');
    }
    $normalized = [];
    foreach ($payload as $key => $value) {
      if (!is_string($key) || !in_array($key, self::FIELDS, TRUE)) {
        throw new UnprocessableEntityHttpException('A proposta contém um campo de Evento não permitido.');
      }
      if ($key === 'dates') {
        $normalized[$key] = $intent === 'event_update' && $value === []
          ? []
          : $this->normalizeDates($value);
        continue;
      }
      if (in_array($key, ['field_organizacao', 'field_coorganizadores'], TRUE)) {
        $normalized[$key] = $this->normalizeOrganizations($value, $key === 'field_coorganizadores');
        continue;
      }
      if (!is_string($value) || mb_strlen($value) > ($key === 'body' ? 20000 : 255)) {
        throw new UnprocessableEntityHttpException('Um dos campos do Evento é inválido.');
      }
      $value = trim($value);
      if ($key === 'title' && $value === '') {
        throw new UnprocessableEntityHttpException('O título do Evento não pode ficar vazio.');
      }
      if ($key === 'field_url' && $value !== '' && !WebsiteUrl::isValid($value)) {
        throw new UnprocessableEntityHttpException('O site do Evento precisa ser uma URL HTTP ou HTTPS válida.');
      }
      $normalized[$key] = $value;
    }
    $primaryOrganization = $normalized['field_organizacao'][0] ?? NULL;
    if ($primaryOrganization !== NULL
      && in_array($primaryOrganization, $normalized['field_coorganizadores'] ?? [], TRUE)) {
      throw new UnprocessableEntityHttpException('Uma organização não pode ser simultaneamente a organizadora principal e coorganizadora.');
    }
    if ($intent === 'event_create' && empty($normalized['title'])) {
      throw new UnprocessableEntityHttpException('A criação precisa incluir o título do Evento.');
    }
    if ($intent === 'event_create' && empty($normalized['dates'])) {
      throw new UnprocessableEntityHttpException('A criação precisa incluir ao menos uma Data.');
    }
    return $normalized;
  }

  /**
   * {@inheritdoc} */
  public function validateSourceData(string $intent, array $sourceData): array {
    if (!in_array($intent, ['event_create', 'event_update'], TRUE) || $sourceData !== []) {
      throw new UnprocessableEntityHttpException('A proposta contém contexto de origem inválido.');
    }
    return [];
  }

  /**
   * {@inheritdoc} */
  public function validateDecisionData(string $intent, array $selectedFields, array $decisionData): array {
    if (!in_array($intent, ['event_create', 'event_update'], TRUE) || $decisionData !== []) {
      throw new UnprocessableEntityHttpException('A decisão do Evento contém opções inválidas.');
    }
    return [];
  }

  /**
   * {@inheritdoc} */
  public function currentVersion(string $intent, ?EntityInterface $target): string {
    if ($intent === 'event_create' && $target === NULL) {
      return 'event:create:v1';
    }
    if ($intent !== 'event_update' || !$target instanceof Event) {
      return '';
    }
    $canonicalTarget = $this->entityTypeManager->getStorage('aculta_event')->loadUnchanged($target->id());
    if (!$canonicalTarget instanceof Event) {
      return '';
    }
    $target = $canonicalTarget;
    $dates = $this->entityTypeManager->getStorage('aculta_event_date')->loadByProperties(['field_evento' => $target->id()]);
    $dateValues = [];
    foreach ($dates as $date) {
      if ($date instanceof EventDate) {
        $dateValues[(int) $date->id()] = $date->toArray();
      }
    }
    ksort($dateValues);
    return hash('sha256', json_encode([$target->toArray(), $dateValues], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
  }

  /**
   * {@inheritdoc} */
  public function recordDecision(Contribution $proposal, string $outcome): void {
    $this->eventQueue->enqueue(new OrganizationContributionOutcomeRecordedEvent(
      'events_event',
      $proposal->uuid(),
      (int) $proposal->getRevisionId(),
      (int) $proposal->getOwnerId(),
      $outcome,
    ));
  }

  /**
   * Applies the proposal with its original author as organizer on creation.
   */
  public function applyContribution(Contribution $proposal, string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface {
    $author = $this->entityTypeManager->getStorage('user')->load((int) $proposal->getOwnerId());
    if ($author === NULL || !$author->isActive()) {
      throw new ConflictHttpException('O autor da proposta não está mais ativo.');
    }
    if ($intent === 'event_create') {
      if (!$this->permissionPolicy->canCreateEvent($author)) {
        throw new ConflictHttpException('O autor não tem mais autorização para criar Eventos.');
      }
      return $this->createEvent($proposal, $target, $expectedVersion, $selectedPayload, $decisionData);
    }
    if ($intent === 'event_update' && $target instanceof Event) {
      $accessHandler = $this->entityTypeManager->getAccessControlHandler('aculta_event');
      $accessHandler->resetCache();
      $freshTarget = $this->entityTypeManager->getStorage('aculta_event')->loadUnchanged($target->id());
      if (!$freshTarget instanceof Event || !$freshTarget->access('update', $author, TRUE)->isAllowed()) {
        throw new ConflictHttpException('O autor não tem mais autorização para atualizar este Evento.');
      }
    }
    return $this->apply($intent, $target, $expectedVersion, $selectedPayload, $reviewer, $decisionData);
  }

  /**
   * {@inheritdoc} */
  public function apply(string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface {
    if ($intent !== 'event_update' || !$target instanceof Event || $selectedPayload === [] || $decisionData !== []) {
      throw new ConflictHttpException('O Evento da proposta não está disponível.');
    }
    $values = $this->validatePayload($intent, $selectedPayload, $target);
    $eventId = (int) $target->id();
    $lockName = 'aculta_events.event.' . $eventId;
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new ConflictHttpException('O Evento está sendo atualizado. Tente novamente.');
    }
    $transaction = NULL;
    try {
      $transaction = $this->database->startTransaction();
      $select = $this->database->select('aculta_event', 'event');
      $select->fields('event', ['id'])->condition('id', $eventId)->forUpdate();
      $select->execute()->fetchField();
      $fresh = $this->entityTypeManager->getStorage('aculta_event')->loadUnchanged($eventId);
      if (!$fresh instanceof Event || !hash_equals($expectedVersion, $this->currentVersion($intent, $fresh))) {
        throw new ConflictHttpException('O Evento ou suas Datas mudaram desde o envio da proposta.');
      }
      $this->assertReferences($values, (int) $fresh->getOwnerId(), $fresh);
      $this->applyEventFields($fresh, $values);
      $fresh->save();
      $this->appendDates($fresh, $values['dates'] ?? []);
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
   * Creates a draft Event and its initial Dates under one transaction. */
  private function createEvent(Contribution $proposal, ?EntityInterface $target, string $expectedVersion, array $payload, array $decisionData): Event {
    if ($target !== NULL || $decisionData !== [] || !hash_equals('event:create:v1', $expectedVersion)) {
      throw new ConflictHttpException('A proposta de criação do Evento não está válida.');
    }
    $values = $this->validatePayload('event_create', $payload);
    $uid = (int) $proposal->getOwnerId();
    if ($uid < 1) {
      throw new ConflictHttpException('O autor da proposta não está disponível.');
    }
    $lockName = 'aculta_events.event.create';
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new ConflictHttpException('A fila de criação de Eventos está sendo processada.');
    }
    $transaction = NULL;
    try {
      $transaction = $this->database->startTransaction();
      $this->assertReferences($values, $uid);
      $this->assertNoDuplicate($values);
      $created = $this->entityTypeManager->getStorage('aculta_event')->create([
        'title' => $values['title'],
        'field_organizador' => $uid,
        'field_status' => 'rascunho',
      ]);
      if (!$created instanceof Event) {
        throw new \LogicException('The event storage returned an unexpected entity.');
      }
      $event = $created;
      $this->applyEventFields($event, $values);
      $event->set('field_status', 'rascunho');
      $event->save();
      $this->appendDates($event, $values['dates']);
      unset($transaction);
      return $event;
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
   * Validates referenced Organizations and Locations for the author. */
  private function assertReferences(array $values, int $authorId, ?Event $event = NULL): void {
    $account = $this->entityTypeManager->getStorage('user')->load($authorId);
    if ($account === NULL || !$account->isActive()) {
      throw new ConflictHttpException('O organizador do Evento não está mais ativo.');
    }
    $eventReferences = $event instanceof Event
      ? array_map(
        static fn ($entity): string => $entity instanceof EntityInterface ? (string) $entity->uuid() : '',
        $event->get('field_coorganizadores')->referencedEntities(),
      )
      : [];
    $references = [
      'field_organizacao' => $values['field_organizacao'] ?? ($event instanceof Event && !$event->get('field_organizacao')->isEmpty() ? [(string) $event->get('field_organizacao')->entity?->uuid()] : []),
      'field_coorganizadores' => $values['field_coorganizadores'] ?? $eventReferences,
    ];
    foreach (['field_organizacao' => FALSE, 'field_coorganizadores' => TRUE] as $field => $multiple) {
      $ids = $references[$field];
      if (!$multiple && $ids !== []) {
        $ids = [$ids[0]];
      }
      foreach ($ids as $uuid) {
        $matches = $this->entityTypeManager->getStorage('aculta_event_organization')->loadByProperties(['uuid' => $uuid]);
        $organization = reset($matches);
        if (!$organization instanceof EventOrganization || !$organization->isPublished()) {
          throw new ConflictHttpException('Uma Organização do Evento não está mais disponível.');
        }
        if ($organization->isClaimed() && ($this->organizationAccess === NULL
          || !$this->organizationAccess->canActOnBehalf($account, (string) $organization->id()))) {
          throw new ConflictHttpException('O organizador não tem mais autorização para usar uma Organização vinculada.');
        }
      }
    }
    $dates = $values['dates'] ?? [];
    if ($event instanceof Event) {
      $existingDates = $this->entityTypeManager->getStorage('aculta_event_date')->loadByProperties(['field_evento' => $event->id()]);
      foreach ($existingDates as $existingDate) {
        if (!$existingDate instanceof EventDate || $existingDate->get('field_local')->isEmpty()) {
          continue;
        }
        $locationId = (int) $existingDate->get('field_local')->target_id;
        $location = $this->entityTypeManager->getStorage('aculta_event_location')->loadUnchanged($locationId);
        $this->entityTypeManager->getAccessControlHandler('aculta_event_location')->resetCache();
        if (!$location instanceof EventLocation || !$location->access('view', $account, TRUE)->isAllowed()) {
          throw new ConflictHttpException('Um Local associado ao Evento não está mais disponível para o organizador.');
        }
      }
    }
    foreach ($dates as $date) {
      if (empty($date['location_uuid'])) {
        continue;
      }
      $matches = $this->entityTypeManager->getStorage('aculta_event_location')->loadByProperties(['uuid' => $date['location_uuid']]);
      $location = reset($matches);
      if ($location instanceof EventLocation) {
        $location = $this->entityTypeManager->getStorage('aculta_event_location')->loadUnchanged($location->id());
      }
      $this->entityTypeManager->getAccessControlHandler('aculta_event_location')->resetCache();
      if (!$location instanceof EventLocation || !$location->access('view', $account, TRUE)->isAllowed()) {
        throw new ConflictHttpException('Um Local de Data não está mais disponível para este organizador.');
      }
    }
  }

  /**
   * Normalizes known fields into Drupal entity item values. */
  private function applyEventFields(Event $event, array $values): void {
    foreach (['title', 'field_subtitulo', 'body', 'field_url'] as $field) {
      if (array_key_exists($field, $values)) {
        $event->set($field, $values[$field]);
      }
    }
    if (array_key_exists('field_organizacao', $values)) {
      $event->set('field_organizacao', $values['field_organizacao'] === [] ? NULL : $this->organizationId($values['field_organizacao'][0]));
    }
    if (array_key_exists('field_coorganizadores', $values)) {
      $event->set('field_coorganizadores', array_map(fn (string $uuid): array => ['target_id' => $this->organizationId($uuid)], $values['field_coorganizadores']));
    }
  }

  /**
   * Resolves an Organization UUID to its storage ID after reference checks. */
  private function organizationId(string $uuid): int {
    $matches = $this->entityTypeManager->getStorage('aculta_event_organization')->loadByProperties(['uuid' => $uuid]);
    $organization = reset($matches);
    if (!$organization instanceof EventOrganization) {
      throw new ConflictHttpException('A Organização do Evento não está mais disponível.');
    }
    return (int) $organization->id();
  }

  /**
   * Adds validated Dates; existing Dates are preserved. */
  private function appendDates(Event $event, array $dates): void {
    foreach ($dates as $index => $date) {
      $values = [
        'title' => $date['title'] !== '' ? $date['title'] : (string) $event->label(),
        'field_evento' => (int) $event->id(),
        'field_quando' => ['value' => $date['starts_at'], 'end_value' => $date['ends_at']],
        'field_peso' => $index,
      ];
      if ($date['location_uuid'] !== '') {
        $matches = $this->entityTypeManager->getStorage('aculta_event_location')->loadByProperties(['uuid' => $date['location_uuid']]);
        $location = reset($matches);
        if (!$location instanceof EventLocation) {
          throw new ConflictHttpException('O Local de uma Data não está mais disponível.');
        }
        $values['field_local'] = (int) $location->id();
      }
      $this->entityTypeManager->getStorage('aculta_event_date')->create($values)->save();
    }
  }

  /**
   * Normalizes UUID references to Organizations. */
  private function normalizeOrganizations(mixed $value, bool $multiple): array {
    if ($value === NULL || $value === '' || $value === []) {
      return [];
    }
    $values = $multiple ? $value : [$value];
    if (!is_array($values) || count($values) > 10) {
      throw new UnprocessableEntityHttpException('As Organizações do Evento são inválidas.');
    }
    $normalized = [];
    foreach ($values as $uuid) {
      if (!is_string($uuid) || !preg_match('/^[a-f0-9-]{36}$/iD', $uuid) || in_array($uuid, $normalized, TRUE)) {
        throw new UnprocessableEntityHttpException('Uma Organização do Evento é inválida ou está repetida.');
      }
      $matches = $this->entityTypeManager->getStorage('aculta_event_organization')->loadByProperties(['uuid' => $uuid]);
      $organization = reset($matches);
      if (!$organization instanceof EventOrganization || !$organization->isPublished()) {
        throw new UnprocessableEntityHttpException('Selecione uma Organização publicada e disponível.');
      }
      $normalized[] = $uuid;
    }
    return $normalized;
  }

  /**
   * Rejects an exact existing title/date duplicate on event creation. */
  private function assertNoDuplicate(array $values): void {
    $title = mb_strtolower(trim((string) ($values['title'] ?? '')));
    if ($title === '') {
      return;
    }
    $ids = $this->entityTypeManager->getStorage('aculta_event')->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', trim((string) $values['title']))
      ->range(0, 20)
      ->execute();
    foreach ($ids as $id) {
      $event = $this->entityTypeManager->getStorage('aculta_event')->load($id);
      if (!$event instanceof Event || mb_strtolower(trim((string) $event->label())) !== $title) {
        continue;
      }
      $existingDates = $this->entityTypeManager->getStorage('aculta_event_date')->loadByProperties(['field_evento' => $event->id()]);
      foreach ($existingDates as $existingDate) {
        if (!$existingDate instanceof EventDate) {
          continue;
        }
        $range = $existingDate->get('field_quando')->first()?->getValue() ?? [];
        foreach ($values['dates'] ?? [] as $candidate) {
          if (($range['value'] ?? NULL) === $candidate['starts_at']) {
            throw new ConflictHttpException('Já existe um Evento com este título e início de Data.');
          }
        }
      }
    }
  }

  /**
   * Normalizes date values and rejects malformed intervals/references. */
  private function normalizeDates(mixed $value): array {
    if (!is_array($value) || $value === [] || count($value) > 30) {
      throw new UnprocessableEntityHttpException('Inclua de uma a trinta Datas válidas.');
    }
    $dates = [];
    foreach ($value as $row) {
      if (!is_array($row) || array_diff(array_keys($row), ['title', 'starts_at', 'ends_at', 'location_uuid'])) {
        throw new UnprocessableEntityHttpException('Uma Data contém propriedades não permitidas.');
      }
      $title = $row['title'] ?? '';
      $start = $row['starts_at'] ?? '';
      $end = $row['ends_at'] ?? '';
      $location = $row['location_uuid'] ?? '';
      if (!is_string($title) || mb_strlen($title) > 255 || !is_string($start) || !is_string($end)
        || !is_string($location) || ($location !== '' && !preg_match('/^[a-f0-9-]{36}$/iD', $location))) {
        throw new UnprocessableEntityHttpException('Uma Data do Evento é inválida.');
      }
      try {
        // A validated proposal is normalized once at submission and again at
        // application. Treat already-normalized storage strings as UTC so the
        // second validation cannot shift the instant using the server zone.
        $startZone = preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/iD', $start) ? NULL : new \DateTimeZone('UTC');
        $endZone = preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/iD', $end) ? NULL : new \DateTimeZone('UTC');
        $startsAt = (new \DateTimeImmutable($start, $startZone))->setTimezone(new \DateTimeZone('UTC'));
        $endsAt = (new \DateTimeImmutable($end, $endZone))->setTimezone(new \DateTimeZone('UTC'));
      }
      catch (\Throwable) {
        throw new UnprocessableEntityHttpException('O início e o fim da Data são obrigatórios.');
      }
      if ($endsAt <= $startsAt) {
        throw new UnprocessableEntityHttpException('O fim da Data precisa ser posterior ao início.');
      }
      $dates[] = [
        'title' => trim($title),
        'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
        'ends_at' => $endsAt->format('Y-m-d\TH:i:s'),
        'location_uuid' => $location,
      ];
    }
    return $dates;
  }

}
