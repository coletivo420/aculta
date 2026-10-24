<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Commerce;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
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
 * Stores and resolves Commerce order notices in Message, without a parallel inbox. */
final class CommerceOrderNoticeManager implements CommerceOrderNoticeManagerInterface, AvisoDigestProviderInterface {

  use StringTranslationTrait;

  public const TEMPLATE = 'aculta_msg_commerce_order_notice';

  private const EVENT_FIELD = 'field_aculta_commerce_event_type';

  private const SOURCE_FIELD = 'field_aculta_commerce_uuid';

  private const VERSION_FIELD = 'field_aculta_commerce_version';

  private const READ_FIELD = 'field_aculta_commerce_read';

  private const DIGEST_FIELD = 'field_aculta_commerce_digest';

  private const DELIVERY_FIELD = 'field_aculta_msg_delivery';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly CheckoutSettings $checkoutSettings,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly QueueFactory $queueFactory,
    private readonly LockBackendInterface $lock,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc} */
  public function record(MessengerNoticeEvent $event): ?MessageInterface {
    if ($event->sourceModule() !== 'aculta_commerce'
      || $event->eventType() !== 'store.order_placed'
      || ($event->metadata()['mail_policy'] ?? NULL) !== 'user_preference'
      || ($event->metadata()['notice_type'] ?? NULL) !== 'order_placed') {
      return NULL;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($event->recipientUid());
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return NULL;
    }

    $key = $event->idempotencyKey();
    if (!$this->lock->acquire('aculta_commerce_notice:' . hash('sha256', $key), 30)) {
      throw new \RuntimeException('Commerce order notice is currently being recorded.');
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

      $mode = ($event->metadata()['delivery_override'] ?? NULL) === 'off'
        ? AvisoPreferencias::EMAIL_OFF
        : $this->preferences->emailMode($event->recipientUid(), 'loja');
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
      $this->lock->release('aculta_commerce_notice:' . hash('sha256', $key));
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
    return $type === 'order_placed'
      ? $this->resolveOrder($uuid, $account)
      : NULL;
  }

  /**
   * {@inheritdoc} */
  public function addPersonalNotices(array &$items, AccountInterface $account): void {
    if (!$account->isAuthenticated()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)->condition('uid', (int) $account->id())
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
        '#attributes' => ['data-portal-ui' => 'notice-summary notice-summary-persistent'],
        'link' => PortalLinkElement::build($heading, Url::fromRoute('aculta_messenger.commerce_notice_open', ['message' => $message->id()]), ['data-portal-ui' => 'notice-summary-link']),
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
    if (!$account instanceof UserInterface || !$account->isActive()
      || !$this->preferences->resumoEmailLigado($uid)
      || $this->preferences->emailMode($uid, 'loja') !== AvisoPreferencias::EMAIL_DIGEST) {
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
      $rows[] = ['tipo' => 'loja', 'titulo' => $notice['email_summary'], 'quantidade' => 1];
    }
    return $rows;
  }

  /**
   * {@inheritdoc} */
  public function markDigestSent(int $uid): void {
    $this->setDigestSent($uid);
  }

  /**
   * {@inheritdoc} */
  public function discardDigest(int $uid): void {
    $this->setDigestSent($uid);
  }

  /**
   * Marks all pending Loja digest rows for a user complete. */
  private function setDigestSent(int $uid): void {
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($this->pendingIds($uid)) as $message) {
      if ($message instanceof MessageInterface) {
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
    if ($this->queueFactory->get('aculta_commerce_messenger_email')->createItem(['message_id' => (int) $message->id()]) === FALSE) {
      $this->logger->error('Could not queue Commerce order notice email for message @id.', ['@id' => $message->id()]);
      throw new \RuntimeException('Commerce order notice email could not be queued.');
    }
  }

  /**
   * Resolves an order only for its customer with current order access. */
  private function resolveOrder(string $uuid, AccountInterface $account): ?array {
    $orders = $this->entityTypeManager->getStorage('commerce_order')->loadByProperties(['uuid' => $uuid]);
    $order = reset($orders);
    if (!$order instanceof OrderInterface
      || $order->bundle() !== $this->checkoutSettings->shopOrderType()
      || (int) $order->getCustomerId() !== (int) $account->id()
      || !$order->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    $url = $this->domainPurposes->routeUrl('shop', 'entity.commerce_order.user_view', [
      'commerce_order' => $order->id(),
      'user' => $account->id(),
    ]);
    if ($url === NULL) {
      return NULL;
    }
    return [
      'title' => $this->t('Pedido recebido'),
      'summary' => $this->t('Seu pedido foi recebido pela Loja ACULTA.'),
      'email_summary' => $this->t('Seu pedido foi recebido pela Loja ACULTA.'),
      'url' => $url,
    ];
  }

}
