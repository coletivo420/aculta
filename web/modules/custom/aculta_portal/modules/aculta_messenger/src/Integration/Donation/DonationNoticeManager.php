<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface;
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
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores Donation notices in the shared Message entity and digest pipeline. */
final class DonationNoticeManager implements DonationNoticeManagerInterface, AvisoDigestProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function digestProviderId(): string {
    return 'donation';
  }

  use StringTranslationTrait;

  public const TEMPLATE = 'aculta_msg_donation_notice';

  public const DIRECT_EMAIL_QUEUE = 'aculta_messenger_donation_email';

  private const EVENT_FIELD = 'field_aculta_don_event';

  private const SOURCE_FIELD = 'field_aculta_don_src_uuid';

  private const ORDER_FIELD = 'field_aculta_don_ord_uuid';

  private const READ_FIELD = 'field_aculta_don_read';

  private const DIGEST_FIELD = 'field_aculta_don_digest';

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
    $type = $event->eventType();
    $sourceType = $type === 'donation.order_placed' ? 'commerce_order'
      : ($type === 'donation.payment_approved' ? 'commerce_payment' : '');
    $orderUuid = $event->metadata()['order_uuid'] ?? NULL;
    if ($event->sourceModule() !== 'aculta_donation' || $event->purpose() !== 'account'
      || $event->recipientUid() < 1 || $event->sourceEntityType() !== $sourceType
      || !is_string($orderUuid) || $orderUuid === '' || !$this->hasCanonicalKey($event)) {
      return NULL;
    }

    $account = $this->entityTypeManager->getStorage('user')->load($event->recipientUid());
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return NULL;
    }
    $orders = $this->entityTypeManager->getStorage('commerce_order')->loadByProperties(['uuid' => $orderUuid]);
    $order = reset($orders);
    if (!$order instanceof OrderInterface || !$this->isDonationOrder($order)
      || (int) $order->getCustomerId() !== $event->recipientUid()
      || !$order->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    if ($type === 'donation.order_placed' && $order->uuid() !== $event->sourceEntityUuid()) {
      return NULL;
    }
    if ($type === 'donation.payment_approved' && !$this->isApprovedPayment($event->sourceEntityUuid(), $order)) {
      return NULL;
    }
    $lockId = 'aculta_messenger:donation_notice:' . hash('sha256', $event->idempotencyKey());
    if (!$this->lock->acquire($lockId, 30.0)) {
      throw new \RuntimeException('Donation notice is currently being recorded.');
    }
    try {
      $storage = $this->entityTypeManager->getStorage('message');
      $ids = $storage->getQuery()->accessCheck(FALSE)
        ->condition('template', self::TEMPLATE)
        ->condition('uid', $event->recipientUid())
        ->condition(self::EVENT_FIELD, $type)
        ->condition(self::SOURCE_FIELD, $event->sourceEntityUuid())
        ->range(0, 1)
        ->execute();
      $message = $ids === [] ? NULL : $storage->load(reset($ids));
      if ($message instanceof MessageInterface) {
        if ($message->get(self::DELIVERY_FIELD)->value === 'failed'
          && (int) $message->get(self::ATTEMPTS_FIELD)->value === 0) {
          $message->set(self::DELIVERY_FIELD, 'pending')->save();
          $this->queueDirectDelivery($message);
        }
        return $message;
      }

      $mode = ($event->metadata()['mail_policy'] ?? NULL) === 'user_preference'
        ? $this->preferences->emailMode($event->recipientUid(), 'doacao')
        : AvisoPreferencias::EMAIL_OFF;
      $direct = $mode === AvisoPreferencias::EMAIL_IMMEDIATE;
      $message = $storage->create([
        'template' => self::TEMPLATE,
        'uid' => $event->recipientUid(),
        self::EVENT_FIELD => $type,
        self::SOURCE_FIELD => $event->sourceEntityUuid(),
        self::ORDER_FIELD => $orderUuid,
        self::READ_FIELD => 0,
        self::DIGEST_FIELD => $mode === AvisoPreferencias::EMAIL_DIGEST ? 0 : 1,
        self::DELIVERY_FIELD => $direct ? 'pending' : 'sent',
        self::ATTEMPTS_FIELD => 0,
      ]);
      $message->save();
      if ($direct) {
        $this->queueDirectDelivery($message);
      }
      return $message;
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  /**
   * {@inheritdoc} */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array {
    if (!$account->isAuthenticated()
      || (int) $message->getOwnerId() !== (int) $account->id()
      || $message->bundle() !== self::TEMPLATE) {
      return NULL;
    }
    $recipient = $this->entityTypeManager->getStorage('user')->load((int) $account->id());
    if (!$recipient instanceof UserInterface || !$recipient->isActive()) {
      return NULL;
    }
    $type = (string) $message->get(self::EVENT_FIELD)->value;
    if (!in_array($type, ['donation.order_placed', 'donation.payment_approved'], TRUE)) {
      return NULL;
    }
    $orders = $this->entityTypeManager->getStorage('commerce_order')->loadByProperties([
      'uuid' => (string) $message->get(self::ORDER_FIELD)->value,
    ]);
    $order = reset($orders);
    if (!$order instanceof OrderInterface || !$this->isDonationOrder($order)
      || (int) $order->getCustomerId() !== (int) $account->id()
      || !$order->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }

    if ($type === 'donation.payment_approved' && !$this->resolveApprovedPayment($message, $order)) {
      return NULL;
    }
    $url = $this->domainPurposes->routeUrl('shop', 'entity.commerce_order.user_view', [
      'commerce_order' => $order->id(),
    ]);
    if ($url === NULL) {
      return NULL;
    }
    $payment = $type === 'donation.payment_approved';
    $summary = $payment
      ? $this->t('O pagamento da sua doação foi aprovado.')
      : $this->t('Seu pedido de doação foi criado; o pagamento ainda pode estar pendente.');
    return [
      'title' => $payment ? $this->t('Pagamento de doação aprovado') : $this->t('Pedido de doação criado'),
      'summary' => $summary,
      'email_summary' => $summary,
      'url' => $url,
    ];
  }

  /**
   * {@inheritdoc} */
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void {
    if (!$account->isAuthenticated()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', (int) $account->id());
    NoticeListFilter::applyReadCondition($query, self::READ_FIELD, $filters);
    $ids = $query
      ->sort('created', 'DESC')
      ->range(0, 20)
      ->execute();
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
          '#element_attributes' => new Attribute(['data-portal-ui' => $unread ? 'read-label read-label-unread' : 'read-label']),
          '#value' => $unread ? $this->t('Não lido') : $this->t('Lido'),
        ],
      ];
      $items[] = [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'notice-summary notice-summary-persistent',
          'data-aculta-notice-origin' => 'doacao',
          'data-aculta-notice-read' => $unread ? 'unread' : 'read',
          'data-aculta-notice-message-id' => (string) $message->id(),
        ],
        'link' => PortalLinkElement::build($heading, Url::fromRoute('aculta_messenger.donation_notice_open', ['message' => $message->id()]), ['data-portal-ui' => 'notice-summary-link']),
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
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(self::READ_FIELD, 0)
      ->execute();
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
    $ids = $this->messageStorage()->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition(self::DIGEST_FIELD, 0)
      ->execute();
    $recipients = [];
    foreach ($this->messageStorage()->loadMultiple($ids) as $message) {
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
      || $this->preferences->emailMode($uid, 'doacao') !== AvisoPreferencias::EMAIL_DIGEST) {
      $this->discardDigest($uid);
      return [];
    }
    $rows = [];
    foreach ($this->pendingMessages($uid) as $message) {
      $notice = $this->resolve($message, $account);
      if ($notice === NULL) {
        $message->set(self::DIGEST_FIELD, 1)->save();
        continue;
      }
      $rows[] = [
        'tipo' => 'doacao',
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
    $this->setDigestDone($uid, $deliveryIds);
  }

  /**
   * {@inheritdoc} */
  public function discardDigest(int $uid): void {
    $this->setDigestDone($uid);
  }

  /**
   * Returns pending digest notices for an account. */
  public function pendingMessages(int $uid): array {
    $ids = $this->messageStorage()->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(self::DIGEST_FIELD, 0)
      ->execute();
    return array_values($this->messageStorage()->loadMultiple($ids));
  }

  /**
   * Marks an owned notice read after current source access is revalidated. */
  public function markRead(MessageInterface $message, AccountInterface $account): bool {
    return $this->setRead($message, $account, TRUE);
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
   * Loads the source payment for a payment-approved event. */
  public function resolveApprovedPayment(MessageInterface $message, OrderInterface $order): bool {
    return $this->isApprovedPayment((string) $message->get(self::SOURCE_FIELD)->value, $order);
  }

  /**
   * Confirms a source payment remains approved and belongs to the order. */
  private function isApprovedPayment(string $uuid, OrderInterface $order): bool {
    $payments = $this->entityTypeManager->getStorage('commerce_payment')->loadByProperties(['uuid' => $uuid]);
    $payment = reset($payments);
    return $payment instanceof PaymentInterface
      && (int) $payment->getOrderId() === (int) $order->id()
      && $payment->getState()->getId() === 'completed'
      && $payment->getRemoteState() === 'approved';
  }

  /**
   * Checks Donation owns this Commerce order. */
  private function isDonationOrder(OrderInterface $order): bool {
    if (!in_array($order->bundle(), ['doacao', 'assinatura'], TRUE)) {
      return FALSE;
    }
    foreach ($order->getItems() as $item) {
      if ($item->bundle() === 'donation') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks the canonical idempotency key emitted by Donation. */
  private function hasCanonicalKey(MessengerNoticeEvent $event): bool {
    $prefix = match ($event->eventType()) {
      'donation.order_placed' => 'donation.order_placed:' . $event->sourceEntityUuid(),
      'donation.payment_approved' => 'donation.payment_approved:' . $event->sourceEntityUuid(),
      default => '',
    };
    return $prefix !== '' && hash_equals($prefix . ':' . $event->recipientUid(), $event->idempotencyKey());
  }

  /**
   * Queues direct delivery after persisting the durable site notice. */
  private function queueDirectDelivery(MessageInterface $message): void {
    if ($this->queueFactory->get(self::DIRECT_EMAIL_QUEUE)->createItem(['message_id' => (int) $message->id()]) === FALSE) {
      $message->set(self::DELIVERY_FIELD, 'failed')->save();
      $this->logger->error('Could not queue Donation notice email for Message @id.', ['@id' => $message->id()]);
      throw new \RuntimeException('Donation notice email could not be queued.');
    }
  }

  /**
   * Marks all pending digest messages complete for one recipient. */
  private function setDigestDone(int $uid, ?array $deliveryIds = NULL): void {
    $messages = $deliveryIds === NULL
      ? $this->pendingMessages($uid)
      : array_values($this->messageStorage()->loadMultiple($deliveryIds));
    foreach ($messages as $message) {
      if ($message instanceof MessageInterface && (int) $message->getOwnerId() === $uid && $message->bundle() === self::TEMPLATE) {
        $message->set(self::DIGEST_FIELD, 1)->save();
      }
    }
  }

  /**
   * Returns the Message entity storage. */
  private function messageStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('message');
  }

}
