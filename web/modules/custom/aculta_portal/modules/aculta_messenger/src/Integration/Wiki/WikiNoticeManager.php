<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Wiki;

use Drupal\aculta_messenger\Aviso\AvisoDigestProviderIdentityInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
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
use Drupal\Core\Url;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores and resolves Wiki notices in Message, without a parallel inbox. */
final class WikiNoticeManager implements WikiNoticeManagerInterface, AvisoDigestProviderIdentityInterface {

  /**
   * {@inheritdoc}
   */
  public function digestProviderId(): string {
    return 'wiki';
  }

  use StringTranslationTrait;

  public const TEMPLATE = 'aculta_msg_wiki_notice';

  private const EVENT_FIELD = 'field_aculta_wiki_event_type';

  private const SOURCE_FIELD = 'field_aculta_wiki_source_uuid';

  private const VERSION_FIELD = 'field_aculta_wiki_event_version';

  private const READ_FIELD = 'field_aculta_wiki_read';

  private const DIGEST_FIELD = 'field_aculta_wiki_digest_sent';

  private const DELIVERY_FIELD = 'field_aculta_msg_delivery';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly QueueFactory $queueFactory,
    private readonly LockBackendInterface $lock,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc} */
  public function record(MessengerNoticeEvent $event): ?MessageInterface {
    if ($event->sourceModule() !== 'aculta_wiki'
      || !in_array($event->eventType(), ['wiki.review_requested', 'wiki.proposal_outcome'], TRUE)
      || ($event->metadata()['mail_policy'] ?? NULL) !== 'user_preference'
      || !in_array($event->metadata()['notice_type'] ?? NULL, ['review_requested', 'proposal_outcome'], TRUE)) {
      return NULL;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($event->recipientUid());
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return NULL;
    }

    $key = $event->idempotencyKey();
    if (!$this->lock->acquire('aculta_wiki_notice:' . hash('sha256', $key), 30)) {
      throw new \RuntimeException('Wiki notice is currently being recorded.');
    }
    try {
      $storage = $this->entityTypeManager->getStorage('message');
      $ids = $storage->getQuery()->accessCheck(FALSE)
        ->condition('template', self::TEMPLATE)
        ->condition('uid', $event->recipientUid())
        ->condition(self::EVENT_FIELD, $event->metadata()['notice_type'])
        ->condition(self::SOURCE_FIELD, $event->sourceEntityUuid())
        ->condition(self::VERSION_FIELD, $event->sourceRevisionOrVersion() ?? '1')
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

      $mode = $this->preferences->emailMode($event->recipientUid(), 'wiki');
      $digest = $mode === 'digest' ? 0 : 1;
      $direct = $mode === 'immediate';
      $message = $storage->create([
        'template' => self::TEMPLATE,
        'uid' => $event->recipientUid(),
        self::EVENT_FIELD => $event->metadata()['notice_type'],
        self::SOURCE_FIELD => $event->sourceEntityUuid(),
        self::VERSION_FIELD => $event->sourceRevisionOrVersion() ?? '1',
        self::READ_FIELD => 0,
        self::DIGEST_FIELD => $digest,
        self::DELIVERY_FIELD => $direct ? 'pending' : 'sent',
        self::ATTEMPTS_FIELD => 0,
      ]);
      $message->save();
      if ($direct) {
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
      $this->lock->release('aculta_wiki_notice:' . hash('sha256', $key));
    }
  }

  /**
   * {@inheritdoc} */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array {
    if (!$account->isAuthenticated() || (int) $message->getOwnerId() !== (int) $account->id()
      || $message->bundle() !== self::TEMPLATE) {
      return NULL;
    }
    $type = (string) $message->get(self::EVENT_FIELD)->value;
    $uuid = (string) $message->get(self::SOURCE_FIELD)->value;
    $revisionId = (int) $message->get(self::VERSION_FIELD)->value;
    return match ($type) {
      'review_requested' => $this->resolveReview($uuid, $revisionId, $account),
      'proposal_outcome' => $this->resolveProposal($uuid, $revisionId, $account),
      default => NULL,
    };
  }

  /**
   * {@inheritdoc} */
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void {
    if (!$account->isAuthenticated()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)->condition('uid', (int) $account->id());
    NoticeListFilter::applyReadCondition($query, self::READ_FIELD, $filters);
    $ids = $query
      ->sort('created', 'DESC')->range(0, 10)->execute();
    foreach ($storage->loadMultiple($ids) as $message) {
      if (!$message instanceof MessageInterface || ($notice = $this->resolve($message, $account)) === NULL) {
        continue;
      }
      $unread = !(bool) $message->get(self::READ_FIELD)->value;
      $heading = [
        '#type' => 'container',
        'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => $notice['title']],
        'excerpt' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => $notice['summary']],
        'state' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute([
            'data-portal-ui' => $unread ? 'read-label read-label-unread' : 'read-label',
          ]),
          '#value' => $unread ? $this->t('Não lido') : $this->t('Lido'),
        ],
      ];
      $items[] = [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'notice-summary notice-summary-persistent',
          'data-aculta-notice-origin' => 'wiki',
          'data-aculta-notice-read' => $unread ? 'unread' : 'read',
          'data-aculta-notice-message-id' => (string) $message->id(),
          ...NoticeRelatedGrouper::sourceAttribute('wiki', (string) $message->get(self::SOURCE_FIELD)->value),
        ],
        'link' => PortalLinkElement::build($heading, Url::fromRoute('aculta_messenger.wiki_notice_open', ['message' => $message->id()]), ['data-portal-ui' => 'notice-summary-link']),
      ];
    }
  }

  /**
   * {@inheritdoc} */
  public function unreadCount(int $uid): int {
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return 0;
    }
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)->condition('uid', $uid)->condition(self::READ_FIELD, 0)->execute();
    $count = 0;
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if ($message instanceof MessageInterface && $this->resolve($message, $account) !== NULL) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * {@inheritdoc}
   */
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
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface || !$account->isActive() || !$this->preferences->resumoEmailLigado($uid)) {
      $this->discardDigest($uid);
      return [];
    }
    $ids = $this->pendingIds($uid);
    $rows = [];
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if (!$message instanceof MessageInterface || ($notice = $this->resolve($message, $account)) === NULL) {
        if ($message instanceof MessageInterface) {
          $message->set(self::DIGEST_FIELD, 1)->save();
        }
        continue;
      }
      $rows[] = [
        'tipo' => 'wiki',
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
   * Marks all pending Wiki digest rows for a user complete. */
  private function setDigestSent(int $uid, ?array $deliveryIds = NULL): void {
    $ids = $deliveryIds ?? $this->pendingIds($uid);
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if ($message instanceof MessageInterface && (int) $message->getOwnerId() === $uid && $message->bundle() === self::TEMPLATE) {
        $message->set(self::DIGEST_FIELD, 1)->save();
      }
    }
  }

  /**
   * Returns pending digest Message IDs. */
  private function pendingIds(int $uid): array {
    return $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)->condition('uid', $uid)->condition(self::DIGEST_FIELD, 0)->execute();
  }

  /**
   * Queues a direct message email, allowing the event queue to retry failures. */
  private function queueDirectDelivery(MessageInterface $message): void {
    if ($this->queueFactory->get('aculta_wiki_messenger_email')->createItem(['message_id' => (int) $message->id()]) === FALSE) {
      $this->logger->error('Could not queue Wiki notice email for message @id.', ['@id' => $message->id()]);
      throw new \RuntimeException('Wiki notice email could not be queued.');
    }
  }

  /**
   * Resolves an editorial review, revalidating revision and reviewer access. */
  private function resolveReview(string $uuid, int $revisionId, AccountInterface $account): ?array {
    if (!$account->hasPermission('edit any wiki_entry content')
      || (!$account->hasPermission('use wiki_editorial transition review_to_draft')
        && !$account->hasPermission('use wiki_editorial transition review_to_published'))) {
      return NULL;
    }
    return $this->domainPurposes->runInPurpose('wiki', function () use ($uuid, $revisionId, $account): ?array {
      $storage = $this->entityTypeManager->getStorage('node');
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uuid', $uuid)->condition('type', 'wiki_entry')->range(0, 1)->execute();
      $node = $ids === [] ? NULL : $storage->loadRevision($revisionId);
      if (!$node || $node->uuid() !== $uuid || (int) $node->getRevisionId() !== $revisionId
        || !$node->isDefaultRevision() || !$node->hasField('moderation_state')
        || $node->get('moderation_state')->value !== 'review'
        || !$node->access('view', $account, TRUE)->isAllowed()
        || !$node->access('view revision', $account, TRUE)->isAllowed()) {
        return NULL;
      }
      $url = $this->domainPurposes->routeUrl('wiki', 'entity.node.canonical', ['node' => $node->id()]);
      if ($url === NULL) {
        return NULL;
      }
      return [
        'title' => $this->t('Verbete aguardando revisão'),
        'summary' => $this->t('Um verbete está disponível para análise editorial.'),
        'email_summary' => $this->t('Há um verbete aguardando revisão editorial.'),
        'url' => $url,
      ];
    });
  }

  /**
   * Resolves a proposal outcome only for its active author with current access. */
  private function resolveProposal(string $uuid, int $revisionId, AccountInterface $account): ?array {
    $storage = $this->entityTypeManager->getStorage('aculta_wiki_proposal');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uuid', $uuid)->range(0, 1)->execute();
    $proposal = $ids === [] ? NULL : $storage->loadRevision($revisionId);
    if (!$account->isAuthenticated() || !$proposal || $proposal->uuid() !== $uuid || (int) $proposal->getRevisionId() !== $revisionId
      || !$proposal->isDefaultRevision()
      || (int) $proposal->getOwnerId() !== (int) $account->id() || !$proposal->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    $url = $this->domainPurposes->canonicalRouteUrl('wiki', 'aculta_wiki.proposal_status', ['proposal' => $proposal->id()]);
    if ($url === NULL) {
      return NULL;
    }
    return [
      'title' => $this->t('Atualização de proposta editorial'),
      'summary' => $this->t('Há uma atualização privada na sua proposta para a Wiki.'),
      'email_summary' => $this->t('Sua proposta para a Wiki recebeu uma atualização.'),
      'url' => $url,
    ];
  }

}
