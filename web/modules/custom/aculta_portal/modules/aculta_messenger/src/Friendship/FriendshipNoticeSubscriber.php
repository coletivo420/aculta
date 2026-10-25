<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Friendship;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\aculta_user\Event\FriendshipChangedEvent;
use Drupal\aculta_user\Social\FriendshipStatus;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\message\MessageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cria aviso persistente de pedido e aceite de amizade e enfileira e-mail.
 *
 * Pedido avisa quem recebeu; aceite avisa quem pediu. A chave de origem evita
 * avisos repetidos. O aviso não contém nome ou conteúdo, somente link da conta.
 */
final class FriendshipNoticeSubscriber implements EventSubscriberInterface {

  public const TEMPLATE = 'aculta_msg_friendship';

  public const SOURCE_FIELD = 'field_aculta_messenger_source_fr';

  public const EVENT_FIELD = 'field_aculta_friendship_event';

  public const READ_FIELD = 'field_aculta_friendship_read';

  public const EMAIL_QUEUE = 'aculta_msg_friendship';

  private const REQUESTED_EVENT = 'aculta_user.friendship_requested';

  private const ACCEPTED_EVENT = 'aculta_user.friendship_accepted';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LockBackendInterface $lock,
    private readonly FriendshipNoticeQueueInterface $noticeQueue,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [MessengerNoticeEvent::EVENT_NAME => 'consume'];
  }

  /**
   * Consumes a friendship notice after revalidating its source and recipient.
   */
  public function consume(MessengerNoticeEvent $event): void {
    if ($event->sourceModule() !== 'aculta_user' || $event->sourceEntityType() !== 'aculta_friendship'
      || $event->purpose() !== 'account'
      || !in_array($event->eventType(), [self::REQUESTED_EVENT, self::ACCEPTED_EVENT], TRUE)
      || $event->sourceRevisionOrVersion() === NULL || !ctype_digit($event->sourceRevisionOrVersion())
      || !$this->hasCanonicalKey($event)) {
      return;
    }
    $transition = $event->eventType() === self::REQUESTED_EVENT
      ? FriendshipChangedEvent::REQUESTED
      : FriendshipChangedEvent::ACCEPTED;
    if (($event->metadata()['transition'] ?? NULL) !== $transition) {
      return;
    }
    $matches = $this->entityTypeManager->getStorage('aculta_friendship')
      ->loadByProperties(['uuid' => $event->sourceEntityUuid()]);
    $friendship = reset($matches);
    if (!$friendship instanceof AcultaFriendshipInterface
      || (int) $friendship->get('changed')->value !== (int) $event->sourceRevisionOrVersion()
      || ($transition === FriendshipChangedEvent::REQUESTED
        && ($friendship->getStatus() !== FriendshipStatus::Pending->value
          || $event->recipientUid() !== $friendship->getRecipientId()))
      || ($transition === FriendshipChangedEvent::ACCEPTED
        && ($friendship->getStatus() !== FriendshipStatus::Accepted->value
          || $event->recipientUid() !== $friendship->getRequesterId()))) {
      return;
    }

    $recipientId = $event->recipientUid();
    $account = $this->entityTypeManager->getStorage('user')->load($recipientId);
    if ($account === NULL || !$account->isActive()) {
      return;
    }

    // Keep the persisted legacy source key so replays remain compatible with
    // notices created before this producer used the shared event envelope.
    $source = $friendship->id() . ':' . $transition . ':' . $event->sourceRevisionOrVersion();
    $storage = $this->entityTypeManager->getStorage('message');
    $lockId = 'aculta_messenger:friendship_notice_record:' . hash('sha256', $event->idempotencyKey());
    if (!$this->lock->acquire($lockId, 30.0)) {
      $this->lock->wait($lockId, 30);
      if (!$this->lock->acquire($lockId, 30.0)) {
        throw new \RuntimeException('Friendship notice is currently being recorded.');
      }
    }
    try {
      $existing = $storage->getQuery()->accessCheck(FALSE)
        ->condition('template', self::TEMPLATE)
        ->condition('uid', $recipientId)
        ->condition(self::SOURCE_FIELD, $source)
        ->range(0, 1)
        ->execute();
      if ($existing !== []) {
        $message = $storage->load(reset($existing));
        if ($message instanceof MessageInterface
          && $message->get('field_aculta_msg_delivery')->value === 'failed'
          && (int) $message->get('field_aculta_messenger_attempts')->value === 0) {
          $this->noticeQueue->enqueue($message);
        }
        return;
      }

      $message = $storage->create([
        'template' => self::TEMPLATE,
        'uid' => $recipientId,
        self::SOURCE_FIELD => $source,
        self::EVENT_FIELD => $transition,
        self::READ_FIELD => 0,
        'field_aculta_msg_delivery' => 'pending',
        'field_aculta_messenger_attempts' => 0,
        'field_aculta_enqueue_attempts' => 0,
        'field_aculta_mailer_managed' => TRUE,
      ]);
      $message->save();
      $this->noticeQueue->enqueue($message);
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  /**
   * Validates that the idempotency key binds source, transition and recipient.
   */
  private function hasCanonicalKey(MessengerNoticeEvent $event): bool {
    $transition = $event->metadata()['transition'] ?? NULL;
    $changed = $event->sourceRevisionOrVersion();
    if (!in_array($transition, [FriendshipChangedEvent::REQUESTED, FriendshipChangedEvent::ACCEPTED], TRUE)
      || $changed === NULL) {
      return FALSE;
    }
    return hash_equals(implode(':', [
      'user', 'friendship', $event->sourceEntityUuid(), $transition, $changed, (string) $event->recipientUid(),
    ]), $event->idempotencyKey());
  }

}
