<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Events;

use Drupal\aculta_contributions\Entity\Contribution;
use Drupal\aculta_messenger\Aviso\EligibleNoticePage;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores and resolves private Events contribution outcome notices. */
final class EventsContributionNoticeManager implements EventsContributionNoticeManagerInterface {

  use StringTranslationTrait;

  public const TEMPLATE = 'aculta_msg_events_contrib_notice';

  private const EVENT_FIELD = 'field_aculta_events_outcome';

  private const SOURCE_FIELD = 'field_aculta_events_prop_uuid';

  private const VERSION_FIELD = 'field_aculta_events_prop_rev';

  private const READ_FIELD = 'field_aculta_events_notice_read';

  private const DIGEST_FIELD = 'field_aculta_events_digest_sent';

  private const DELIVERY_FIELD = 'field_aculta_msg_delivery';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  /**
   * Whether the optional notice bundle and fields are installed. */
  private ?bool $ready = NULL;

  /**
   * Whether the additive delivery fields are installed.
   */
  private ?bool $deliveryReady = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly LockBackendInterface $lock,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly QueueFactory $queueFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function digestProviderId(): string {
    return 'events_contributions';
  }

  /**
   * {@inheritdoc} */
  public function record(MessengerNoticeEvent $event): ?MessageInterface {
    if (!$this->storageReady() || !$this->isEventsEvent($event)) {
      return NULL;
    }
    $recipient = $this->entityTypeManager->getStorage('user')->loadUnchanged($event->recipientUid());
    if (!$recipient instanceof UserInterface || !$recipient->isActive()) {
      return NULL;
    }
    $proposal = $this->loadAuthorizedProposal($event->sourceEntityUuid(), (int) $event->sourceRevisionOrVersion(), $recipient, $event->metadata()['notice_type'] ?? NULL);
    if ($proposal === NULL) {
      return NULL;
    }
    $key = $event->idempotencyKey();
    $lockName = 'aculta_events_notice:' . hash('sha256', $key);
    if (!$this->lock->acquire($lockName, 30)) {
      throw new \RuntimeException('Events proposal notice is currently being recorded.');
    }
    try {
      $storage = $this->entityTypeManager->getStorage('message');
      $ids = $storage->getQuery()->accessCheck(FALSE)
        ->condition('template', self::TEMPLATE)
        ->condition('uid', $event->recipientUid())
        ->condition(self::EVENT_FIELD, $event->metadata()['notice_type'])
        ->condition(self::SOURCE_FIELD, $proposal->uuid())
        ->condition(self::VERSION_FIELD, (string) $proposal->getRevisionId())
        ->range(0, 1)->execute();
      if ($ids !== []) {
        $existing = $storage->load(reset($ids));
        if ($existing instanceof MessageInterface && $this->deliveryStorageReady()
          && $existing->get(self::DELIVERY_FIELD)->value === 'failed'
          && (int) $existing->get(self::ATTEMPTS_FIELD)->value === 0) {
          $existing->set(self::DELIVERY_FIELD, 'pending')->save();
          try {
            $this->queueDirectDelivery($existing);
          }
          catch (\RuntimeException $exception) {
            $existing->set(self::DELIVERY_FIELD, 'failed')->save();
            throw $exception;
          }
        }
        return $existing instanceof MessageInterface ? $existing : NULL;
      }
      $values = [
        'template' => self::TEMPLATE,
        'uid' => $event->recipientUid(),
        self::EVENT_FIELD => $event->metadata()['notice_type'],
        self::SOURCE_FIELD => $proposal->uuid(),
        self::VERSION_FIELD => (string) $proposal->getRevisionId(),
        self::READ_FIELD => 0,
      ];
      $mode = AvisoPreferencias::EMAIL_OFF;
      if ($this->deliveryStorageReady()) {
        $mode = $this->preferences->emailMode($event->recipientUid(), AvisoPreferencias::EMAIL_EVENTS_CONTRIBUTIONS);
        $values[self::DIGEST_FIELD] = $mode === AvisoPreferencias::EMAIL_DIGEST ? 0 : 1;
        $values[self::DELIVERY_FIELD] = $mode === AvisoPreferencias::EMAIL_IMMEDIATE ? 'pending' : 'sent';
        $values[self::ATTEMPTS_FIELD] = 0;
      }
      $message = $storage->create($values);
      $message->save();
      if ($mode === AvisoPreferencias::EMAIL_IMMEDIATE) {
        try {
          $this->queueDirectDelivery($message);
        }
        catch (\RuntimeException $exception) {
          $message->set(self::DELIVERY_FIELD, 'failed')->save();
          throw $exception;
        }
      }
      return $message instanceof MessageInterface ? $message : NULL;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * {@inheritdoc} */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array {
    if (!$account->isAuthenticated() || $message->bundle() !== self::TEMPLATE
      || (int) $message->getOwnerId() !== (int) $account->id()) {
      return NULL;
    }
    $noticeType = (string) $message->get(self::EVENT_FIELD)->value;
    $proposalUuid = (string) $message->get(self::SOURCE_FIELD)->value;
    $revisionId = (int) $message->get(self::VERSION_FIELD)->value;
    $proposal = $this->loadAuthorizedProposal($proposalUuid, $revisionId, $account, $noticeType);
    if ($proposal === NULL) {
      return NULL;
    }
    $url = $this->domainPurposes->routeUrl('eventos', 'aculta_events.organizer_contribution_status', [
      'proposal_uuid' => $proposal->uuid(),
    ]);
    if ($url === NULL || !$this->domainPurposes->runInPurpose('eventos', static fn(): bool => $url->access($account, TRUE)->isAllowed())) {
      return NULL;
    }
    return [
      'title' => $this->outcomeTitle($noticeType),
      'summary' => $this->outcomeSummary($noticeType),
      'email_summary' => $noticeType === 'submitted'
        ? (string) $this->t('Sua proposta de Eventos foi registrada no ACULTA.')
        : (string) $this->t('Há uma atualização sobre uma proposta sua de Eventos no ACULTA.'),
      'url' => $url,
    ];
  }

  /**
   * {@inheritdoc} */
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void {
    $this->addPersonalNoticesPage($items, $account, $filters, 10, 0);
  }

  /**
   * {@inheritdoc} */
  public function addPersonalNoticesPage(array &$items, AccountInterface $account, array $filters, int $limit, int $offset): bool {
    if (!$account->isAuthenticated() || !$this->storageReady()) {
      return FALSE;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $resolved = [];
    $page = EligibleNoticePage::collect(
      function (int $scanOffset, int $batchSize) use ($storage, $account, $filters): array {
        $query = $storage->getQuery()->accessCheck(FALSE)
          ->condition('template', self::TEMPLATE)->condition('uid', (int) $account->id());
        NoticeListFilter::applyReadCondition($query, self::READ_FIELD, $filters);
        return $query->sort('created', 'DESC')->sort('mid', 'DESC')->range($scanOffset, $batchSize)->execute();
      },
      function (array $ids) use ($storage, $account, &$resolved): array {
        $messages = array_values($storage->loadMultiple($ids));
        foreach ($messages as $message) {
          if ($message instanceof MessageInterface && ($notice = $this->resolve($message, $account)) !== NULL) {
            $resolved[(int) $message->id()] = $notice;
          }
        }
        return $messages;
      },
      static fn(mixed $message): ?array => $message instanceof MessageInterface ? ($resolved[(int) $message->id()] ?? NULL) : NULL,
      $limit,
      $offset,
    );
    foreach ($page['items'] as $entry) {
      $message = $entry['entity'];
      $notice = $entry['resolved'];
      $unread = !(bool) $message->get(self::READ_FIELD)->value;
      $heading = [
        '#type' => 'container',
        'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => $notice['title']],
        'excerpt' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => $notice['summary']],
        'state' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['data-portal-ui' => $unread ? 'read-label read-label-unread' : 'read-label']),
          '#value' => $unread ? $this->t('Não lido') : $this->t('Lido'),
        ],
      ];
      $items[] = [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'notice-summary notice-summary-persistent',
          'data-aculta-notice-origin' => 'eventos',
          'data-aculta-notice-recency' => (string) $message->getCreatedTime(),
          'data-aculta-notice-read' => $unread ? 'unread' : 'read',
          'data-aculta-notice-message-id' => (string) $message->id(),
        ],
        'link' => PortalLinkElement::build($heading, Url::fromRoute('aculta_messenger.events_contribution_notice_open', ['message' => $message->id()]), ['data-portal-ui' => 'notice-summary-link']),
      ];
    }
    return $page['has_next'];
  }

  /**
   * {@inheritdoc} */
  public function unreadCount(int $uid): int {
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$account instanceof UserInterface || !$account->isActive() || !$this->storageReady()) {
      return 0;
    }
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)->condition('uid', $uid)->condition(self::READ_FIELD, 0)->execute();
    $messages = $this->entityTypeManager->getStorage('message')->loadMultiple($ids);
    return count(array_filter($messages, fn(mixed $message): bool => $message instanceof MessageInterface && $this->resolve($message, $account) !== NULL));
  }

  /**
   * Sets read state only after current source access has been revalidated. */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool {
    if ($this->resolve($message, $account) === NULL) {
      return FALSE;
    }
    if ((bool) $message->get(self::READ_FIELD)->value !== $read) {
      $message->set(self::READ_FIELD, $read ? 1 : 0)->save();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function pendingRecipientIds(): array {
    if (!$this->deliveryStorageReady()) {
      return [];
    }
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)->condition(self::DIGEST_FIELD, 0)->execute();
    $recipients = [];
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if ($message instanceof MessageInterface) {
        $recipients[(int) $message->getOwnerId()] = TRUE;
      }
    }
    return array_keys($recipients);
  }

  /**
   * {@inheritdoc}
   */
  public function digestRowsFor(int $uid): array {
    if (!$this->deliveryStorageReady()) {
      return [];
    }
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$account instanceof UserInterface || !$account->isActive()
      || $this->preferences->emailMode($uid, AvisoPreferencias::EMAIL_EVENTS_CONTRIBUTIONS) !== AvisoPreferencias::EMAIL_DIGEST) {
      $this->discardDigest($uid);
      return [];
    }
    $ids = $this->pendingDigestIds($uid);
    $rows = [];
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if (!$message instanceof MessageInterface) {
        continue;
      }
      $notice = $this->resolve($message, $account);
      if ($notice === NULL) {
        $message->set(self::DIGEST_FIELD, 1)->save();
        continue;
      }
      $rows[] = [
        'tipo' => 'eventos_contribuicoes',
        'titulo' => $notice['email_summary'],
        'quantidade' => 1,
        'delivery_id' => (int) $message->id(),
      ];
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    $this->setDigestSent($uid, $deliveryIds);
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {
    $this->setDigestSent($uid);
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigestItems(int $uid, array $deliveryIds): void {
    $this->setDigestSent($uid, $deliveryIds);
  }

  /**
   * Reloads the exact immutable contribution revision and rechecks access. */
  private function loadAuthorizedProposal(string $uuid, int $revisionId, AccountInterface $recipient, mixed $outcome): ?Contribution {
    if ($uuid === '' || $revisionId < 1 || !in_array($outcome, ['submitted', 'needs_changes', 'declined', 'accepted'], TRUE)) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_contribution');
    if (!method_exists($storage, 'loadRevision')) {
      return NULL;
    }
    $proposal = $storage->loadRevision($revisionId);
    if (!$proposal instanceof Contribution || $proposal->uuid() !== $uuid
      || (int) $proposal->getRevisionId() !== $revisionId
      || (int) $proposal->getOwnerId() !== (int) $recipient->id()
      || $proposal->get('state')->value !== $outcome
      || !in_array($proposal->get('source')->value, ['events_organization', 'events_location', 'events_event'], TRUE)) {
      return NULL;
    }
    $allowed = $this->domainPurposes->runInPurpose('eventos', static fn(): bool => $proposal->access('view', $recipient, TRUE)->isAllowed());
    return $allowed ? $proposal : NULL;
  }

  /**
   * Checks the precise Messenger event contract before storing it. */
  private function isEventsEvent(MessengerNoticeEvent $event): bool {
    $metadataKeys = array_keys($event->metadata());
    sort($metadataKeys);
    return $event->sourceModule() === 'aculta_events'
      && $event->sourceEntityType() === 'aculta_contribution'
      && $event->purpose() === 'eventos'
      && $event->eventType() === 'events.organization_proposal_outcome'
      && $metadataKeys === ['mail_policy', 'notice_type', 'proposal_source']
      && ($event->metadata()['mail_policy'] ?? NULL) === 'user_preference'
      && in_array(
        $event->metadata()['proposal_source'] ?? NULL,
        ['events_organization', 'events_location', 'events_event'],
        TRUE,
      )
      && in_array($event->metadata()['notice_type'] ?? NULL, ['submitted', 'needs_changes', 'declined', 'accepted'], TRUE);
  }

  /**
   * Checks whether the optional notice bundle and fields are installed. */
  private function storageReady(): bool {
    if ($this->ready !== NULL) {
      return $this->ready;
    }
    if ($this->entityTypeManager->getStorage('message_template')->load(self::TEMPLATE) === NULL) {
      return $this->ready = FALSE;
    }
    $fields = [
      self::EVENT_FIELD,
      self::SOURCE_FIELD,
      self::VERSION_FIELD,
      self::READ_FIELD,
    ];
    $storageIds = array_map(static fn(string $field): string => 'message.' . $field, $fields);
    $configIds = array_map(static fn(string $field): string => 'message.' . self::TEMPLATE . '.' . $field, $fields);
    return $this->ready = count($this->entityTypeManager->getStorage('field_storage_config')->loadMultiple($storageIds)) === count($fields)
      && count($this->entityTypeManager->getStorage('field_config')->loadMultiple($configIds)) === count($fields);
  }

  /**
   * Returns pending digest message IDs for one recipient.
   */
  private function pendingDigestIds(int $uid): array {
    return $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)->condition('uid', $uid)->condition(self::DIGEST_FIELD, 0)->execute();
  }

  /**
   * Marks pending digest messages sent, optionally limited to exact IDs.
   */
  private function setDigestSent(int $uid, ?array $deliveryIds = NULL): void {
    if (!$this->deliveryStorageReady()) {
      return;
    }
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($deliveryIds ?? $this->pendingDigestIds($uid)) as $message) {
      if ($message instanceof MessageInterface && (int) $message->getOwnerId() === $uid && $message->bundle() === self::TEMPLATE) {
        $message->set(self::DIGEST_FIELD, 1)->save();
      }
    }
  }

  /**
   * Queues direct e-mail through the shared Messenger delivery worker.
   */
  private function queueDirectDelivery(MessageInterface $message): void {
    if ($this->queueFactory->get('aculta_events_contribution_messenger_email')->createItem(['message_id' => (int) $message->id()]) === FALSE) {
      $this->logger->error('Could not queue Events contribution notice email for Message @id.', ['@id' => $message->id()]);
      throw new \RuntimeException('Events contribution notice email could not be queued.');
    }
  }

  /**
   * Checks whether additive digest and e-mail fields are installed.
   */
  private function deliveryStorageReady(): bool {
    if ($this->deliveryReady !== NULL) {
      return $this->deliveryReady;
    }
    if (!$this->storageReady()) {
      return $this->deliveryReady = FALSE;
    }
    $fields = [
      self::DIGEST_FIELD,
      self::DELIVERY_FIELD,
      self::ATTEMPTS_FIELD,
    ];
    $storageIds = array_map(static fn(string $field): string => 'message.' . $field, $fields);
    $configIds = array_map(static fn(string $field): string => 'message.' . self::TEMPLATE . '.' . $field, $fields);
    return $this->deliveryReady = count($this->entityTypeManager->getStorage('field_storage_config')->loadMultiple($storageIds)) === count($fields)
      && count($this->entityTypeManager->getStorage('field_config')->loadMultiple($configIds)) === count($fields);
  }

  /**
   * Returns a generic title that does not reveal private proposal content. */
  private function outcomeTitle(string $outcome): string {
    return match ($outcome) {
      'submitted' => (string) $this->t('Sua proposta de Evento foi enviada para revisão'),
      'needs_changes' => (string) $this->t('Sua proposta de Eventos precisa de ajustes'),
      'declined' => (string) $this->t('Sua proposta de Eventos foi recusada'),
      default => (string) $this->t('Sua proposta de Eventos foi aprovada'),
    };
  }

  /**
   * Returns a generic summary that does not reveal private proposal content. */
  private function outcomeSummary(string $outcome): string {
    if ($outcome === 'submitted') {
      return (string) $this->t('A proposta foi registrada. Abra a notificação para acompanhar o andamento no ACULTA Studio Culturas.');
    }
    return (string) $this->t('A equipe revisora registrou uma decisão. Abra a notificação para acompanhar sua proposta no ACULTA Studio Culturas.');
  }

}
