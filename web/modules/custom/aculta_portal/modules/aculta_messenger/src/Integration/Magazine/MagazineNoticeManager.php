<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Magazine;

use Drupal\node\NodeInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Aviso\EligibleNoticePage;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\aculta_messenger\Service\NoticeRelatedGrouper;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\flag\FlagServiceInterface;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores and resolves Revista notices in the shared Message entity. */
final class MagazineNoticeManager implements MagazineNoticeManagerInterface {

  use StringTranslationTrait;

  public const TEMPLATE = 'aculta_msg_magazine_notice';

  private const EVENT_FIELD = 'field_aculta_mag_event_type';

  private const SOURCE_FIELD = 'field_aculta_mag_source_uuid';

  private const VERSION_FIELD = 'field_aculta_mag_event_version';

  private const READ_FIELD = 'field_aculta_mag_read';

  private const DIGEST_FIELD = 'field_aculta_mag_digest_sent';

  private const DELIVERY_FIELD = 'field_aculta_msg_delivery';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly QueueFactory $queueFactory,
    private readonly LockBackendInterface $lock,
    private readonly FlagServiceInterface $flagService,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc} */
  public function digestProviderId(): string {
    return 'magazine';
  }

  /**
   * {@inheritdoc} */
  public function record(MessengerNoticeEvent $event): ?MessageInterface {
    if ($event->sourceModule() !== 'aculta_magazine'
      || $event->sourceEntityType() !== 'node'
      || $event->purpose() !== 'magazine'
      || !in_array($event->eventType(), ['magazine.publication_published', 'magazine.publication_updated'], TRUE)
      || ($event->metadata()['mail_policy'] ?? NULL) !== 'user_preference'
      || !in_array($event->metadata()['notice_type'] ?? NULL, ['published', 'updated'], TRUE)) {
      return NULL;
    }
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($event->recipientUid());
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return NULL;
    }
    $node = $this->resolveSource($event->sourceEntityUuid(), (int) $event->sourceRevisionOrVersion(), $account);
    if ($node === NULL || !$this->isRecipient($node, $event->recipientUid())) {
      return NULL;
    }

    $key = $event->idempotencyKey();
    $lockKey = 'aculta_magazine_notice:' . hash('sha256', $key);
    if (!$this->lock->acquire($lockKey, 30)) {
      throw new \RuntimeException('Magazine notice is currently being recorded.');
    }
    try {
      $storage = $this->entityTypeManager->getStorage('message');
      $ids = $storage->getQuery()->accessCheck(FALSE)
        ->condition('template', self::TEMPLATE)
        ->condition('uid', $event->recipientUid())
        ->condition(self::EVENT_FIELD, $event->metadata()['notice_type'])
        ->condition(self::SOURCE_FIELD, $event->sourceEntityUuid())
        ->condition(self::VERSION_FIELD, (string) $event->sourceRevisionOrVersion())
        ->range(0, 1)->execute();
      $message = $ids === [] ? NULL : $storage->load(reset($ids));
      if ($message instanceof MessageInterface) {
        if ($message->get(self::DELIVERY_FIELD)->value === 'failed'
          && (int) $message->get(self::ATTEMPTS_FIELD)->value === 0) {
          $message->set(self::DELIVERY_FIELD, 'pending')->save();
          try {
            $this->queueDirectDelivery($message);
          }
          catch (\RuntimeException $exception) {
            $message->set(self::DELIVERY_FIELD, 'failed')->save();
            throw $exception;
          }
        }
        return $message;
      }

      $mode = $this->preferences->emailMode($event->recipientUid(), 'revista');
      $message = $storage->create([
        'template' => self::TEMPLATE,
        'uid' => $event->recipientUid(),
        self::EVENT_FIELD => $event->metadata()['notice_type'],
        self::SOURCE_FIELD => $event->sourceEntityUuid(),
        self::VERSION_FIELD => (string) $event->sourceRevisionOrVersion(),
        self::READ_FIELD => 0,
        self::DIGEST_FIELD => $mode === AvisoPreferencias::EMAIL_DIGEST ? 0 : 1,
        self::DELIVERY_FIELD => $mode === AvisoPreferencias::EMAIL_IMMEDIATE ? 'pending' : 'sent',
        self::ATTEMPTS_FIELD => 0,
      ]);
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
      return $message;
    }
    finally {
      $this->lock->release($lockKey);
    }
  }

  /**
   * {@inheritdoc} */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array {
    if (!$account->isAuthenticated() || $message->bundle() !== self::TEMPLATE
      || (int) $message->getOwnerId() !== (int) $account->id()) {
      return NULL;
    }
    return $this->resolveMessages([$message], $account)[(int) $message->id()] ?? NULL;
  }

  /**
   * Resolves a batch of notices while loading source nodes in one query.
   *
   * @param \Drupal\message\MessageInterface[] $messages
   *   Messages to resolve.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current recipient.
   *
   * @return array<int, array{title: string, summary: string, email_summary: string, url: string}>
   *   Resolved notices keyed by Message ID.
   */
  private function resolveMessages(array $messages, AccountInterface $account): array {
    if (!$account->isAuthenticated()) {
      return [];
    }
    $valid = [];
    $uuids = [];
    foreach ($messages as $message) {
      if (!$message instanceof MessageInterface || $message->bundle() !== self::TEMPLATE
        || (int) $message->getOwnerId() !== (int) $account->id()) {
        continue;
      }
      $type = (string) $message->get(self::EVENT_FIELD)->value;
      $uuid = (string) $message->get(self::SOURCE_FIELD)->value;
      $revision = (int) $message->get(self::VERSION_FIELD)->value;
      if (in_array($type, ['published', 'updated'], TRUE) && $uuid !== '' && $revision > 0) {
        $valid[(int) $message->id()] = [$message, $type, $uuid, $revision];
        $uuids[$uuid] = $uuid;
      }
    }
    if ($uuids === []) {
      return [];
    }
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()->accessCheck(FALSE)
      ->condition('uuid', array_values($uuids), 'IN')->condition('type', 'revista')->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
    $nodesByUuid = [];
    foreach ($nodes as $node) {
      if ($node instanceof NodeInterface) {
        $nodesByUuid[$node->uuid()] = $node;
      }
    }
    $resolved = [];
    foreach ($valid as $id => [, $type, $uuid, $revision]) {
      $node = $nodesByUuid[$uuid] ?? NULL;
      if (!$node instanceof NodeInterface || !$node->isPublished() || !$node->isDefaultRevision()
        || (int) $node->getRevisionId() !== $revision
        || ($node->hasField('moderation_state') && $node->get('moderation_state')->value !== 'published')
        || !$this->domainPurposes->runInPurpose('magazine', static fn(): bool => $node->access('view', $account, TRUE)->isAllowed())
        || !$this->isRecipient($node, (int) $account->id())) {
        continue;
      }
      $url = $this->domainPurposes->routeUrl('magazine', 'entity.node.canonical', ['node' => $node->id()]);
      if ($url === NULL) {
        continue;
      }
      $title = $type === 'published'
      ? $this->t('Nova publicação na Revista')
      : $this->t('Publicação atualizada na Revista');
      $resolved[$id] = [
        'title' => $title,
        'summary' => $type === 'published'
          ? $this->t('Uma publicação da Revista que você acompanha está disponível.')
          : $this->t('Uma publicação da Revista que você acompanha recebeu uma nova revisão.'),
        'email_summary' => $type === 'published'
          ? $this->t('Há uma nova publicação da Revista disponível.')
          : $this->t('Uma publicação da Revista recebeu uma nova revisão.'),
        'url' => $url,
      ];
    }
    return $resolved;
  }

  /**
   * {@inheritdoc} */
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void {
    $this->addPersonalNoticesPage($items, $account, $filters, 10, 0);
  }

  /**
   * {@inheritdoc} */
  public function addPersonalNoticesPage(array &$items, AccountInterface $account, array $filters, int $limit, int $offset): bool {
    if (!$account->isAuthenticated()) {
      return FALSE;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $resolved = [];
    $page = EligibleNoticePage::collect(
      function (int $scanOffset, int $batchSize) use ($storage, $account, $filters): array {
        $query = $storage->getQuery()->accessCheck(FALSE)
          ->condition('template', self::TEMPLATE)
          ->condition('uid', (int) $account->id());
        NoticeListFilter::applyReadCondition($query, self::READ_FIELD, $filters);
        return $query->sort('created', 'DESC')->sort('id', 'DESC')->range($scanOffset, $batchSize)->execute();
      },
      function (array $ids) use ($storage, $account, &$resolved): array {
        $messages = array_values($storage->loadMultiple($ids));
        $resolved += $this->resolveMessages($messages, $account);
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
          'data-aculta-notice-origin' => 'revista',
          'data-aculta-notice-recency' => (string) $message->getCreatedTime(),
          'data-aculta-notice-read' => $unread ? 'unread' : 'read',
          'data-aculta-notice-message-id' => (string) $message->id(),
          ...NoticeRelatedGrouper::sourceAttribute('revista', (string) $message->get(self::SOURCE_FIELD)->value),
        ],
        'link' => PortalLinkElement::build($heading, $notice['url'], ['data-portal-ui' => 'notice-summary-link']),
      ];
    }
    return $page['has_next'];
  }

  /**
   * {@inheritdoc} */
  public function unreadCount(int $uid): int {
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return 0;
    }
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)->condition('uid', $uid)->condition(self::READ_FIELD, 0)->execute();
    $messages = $this->entityTypeManager->getStorage('message')->loadMultiple($ids);
    return count($this->resolveMessages(array_values($messages), $account));
  }

  /**
   * {@inheritdoc} */
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
   * {@inheritdoc} */
  public function pendingRecipientIds(): array {
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
   * {@inheritdoc} */
  public function digestRowsFor(int $uid): array {
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$account instanceof UserInterface || !$account->isActive()
      || $this->preferences->emailMode($uid, 'revista') !== AvisoPreferencias::EMAIL_DIGEST) {
      $this->discardDigest($uid);
      return [];
    }
    $ids = $this->pendingIds($uid);
    $messages = $this->entityTypeManager->getStorage('message')->loadMultiple($ids);
    $rows = [];
    $notices = $this->resolveMessages(array_values($messages), $account);
    foreach ($messages as $message) {
      $notice = $message instanceof MessageInterface ? ($notices[(int) $message->id()] ?? NULL) : NULL;
      if ($notice === NULL) {
        if ($message instanceof MessageInterface) {
          $message->set(self::DIGEST_FIELD, 1)->save();
        }
        continue;
      }
      $rows[] = [
        'tipo' => 'revista',
        'titulo' => $notice['email_summary'],
        'quantidade' => 1,
        'delivery_id' => (int) $message->id(),
      ];
    }
    return $rows;
  }

  /**
   * {@inheritdoc} */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    $this->setDigestSent($uid, $deliveryIds);
  }

  /**
   * {@inheritdoc} */
  public function discardDigest(int $uid): void {
    $this->setDigestSent($uid);
  }

  /**
   * {@inheritdoc} */
  public function discardDigestItems(int $uid, array $deliveryIds): void {
    $this->setDigestSent($uid, $deliveryIds);
  }

  /**
   * Resolves the exact current published node version and recipient access. */
  private function resolveSource(string $uuid, int $revisionId, AccountInterface $account): ?NodeInterface {
    if ($uuid === '' || $revisionId < 1 || !$account->isAuthenticated()) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uuid', $uuid)->condition('type', 'revista')->range(0, 1)->execute();
    $node = $ids === [] ? NULL : $storage->load(reset($ids));
    if (!$node instanceof NodeInterface || !$node->isPublished() || !$node->isDefaultRevision()
      || (int) $node->getRevisionId() !== $revisionId
      || ($node->hasField('moderation_state') && $node->get('moderation_state')->value !== 'published')) {
      return NULL;
    }
    $allowed = $this->domainPurposes->runInPurpose('magazine', static fn(): bool =>
      $node->access('view', $account, TRUE)->isAllowed()
    );
    return $allowed === TRUE ? $node : NULL;
  }

  /**
   * Revalidates author, coauthor or current publication-following status. */
  private function isRecipient(NodeInterface $node, int $uid): bool {
    if ((int) $node->getOwnerId() === $uid) {
      return TRUE;
    }
    if ($node->hasField('co_authors')) {
      foreach ($node->get('co_authors')->getValue() as $item) {
        if ((int) ($item['target_id'] ?? 0) === $uid) {
          return TRUE;
        }
      }
    }
    $flag = $this->flagService->getFlagById('seguir_publicacao');
    if ($flag === NULL) {
      return FALSE;
    }
    foreach ($this->flagService->getEntityFlaggings($flag, $node) as $flagging) {
      if ((int) $flagging->getOwnerId() === $uid) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Marks pending digest lines complete, optionally restricted to their IDs. */
  private function setDigestSent(int $uid, ?array $deliveryIds = NULL): void {
    $ids = $deliveryIds ?? $this->pendingIds($uid);
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if ($message instanceof MessageInterface && (int) $message->getOwnerId() === $uid && $message->bundle() === self::TEMPLATE) {
        $message->set(self::DIGEST_FIELD, 1)->save();
      }
    }
  }

  /**
   * Returns pending digest Message IDs for one recipient. */
  private function pendingIds(int $uid): array {
    return $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)->condition('uid', $uid)->condition(self::DIGEST_FIELD, 0)->execute();
  }

  /**
   * Queues direct email through the shared Messenger/Mailer delivery worker. */
  private function queueDirectDelivery(MessageInterface $message): void {
    if ($this->queueFactory->get('aculta_magazine_messenger_email')->createItem(['message_id' => (int) $message->id()]) === FALSE) {
      $this->logger->error('Could not queue Magazine notice email for Message @id.', ['@id' => $message->id()]);
      throw new \RuntimeException('Magazine notice email could not be queued.');
    }
  }

}
