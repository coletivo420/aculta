<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Friendship;

use Drupal\aculta_user\Event\FriendshipChangedEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cria o aviso persistente de pedido e de aceite de amizade, deduplicado, e enfileira o e-mail mínimo.
 *
 * Pedido avisa quem recebeu; aceite avisa quem pediu. A chave de origem (amizade, evento e instante da transição)
 * impede aviso repetido para a mesma transição. O aviso não contém nome nem conteúdo: só o link para a conta.
 */
final class FriendshipNoticeSubscriber implements EventSubscriberInterface {

  public const TEMPLATE = 'aculta_msg_friendship';

  public const SOURCE_FIELD = 'field_aculta_messenger_source_fr';

  public const EVENT_FIELD = 'field_aculta_friendship_event';

  public const EMAIL_QUEUE = 'aculta_msg_friendship';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queueFactory,
  ) {}

  public static function getSubscribedEvents(): array {
    return [FriendshipChangedEvent::NAME => 'onFriendshipChanged'];
  }

  public function onFriendshipChanged(FriendshipChangedEvent $event): void {
    $recipientId = $event->transition === FriendshipChangedEvent::REQUESTED ? $event->recipientId : $event->requesterId;
    $account = $this->entityTypeManager->getStorage('user')->load($recipientId);
    if ($account === NULL || !$account->isActive()) {
      return;
    }

    $source = $event->friendshipId . ':' . $event->transition . ':' . $event->changed;
    $storage = $this->entityTypeManager->getStorage('message');
    $existing = $storage->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $recipientId)
      ->condition(self::SOURCE_FIELD, $source)
      ->range(0, 1)
      ->execute();
    if ($existing !== []) {
      return;
    }

    $message = $storage->create([
      'template' => self::TEMPLATE,
      'uid' => $recipientId,
      self::SOURCE_FIELD => $source,
      self::EVENT_FIELD => $event->transition,
      'field_aculta_msg_delivery' => 'pending',
      'field_aculta_messenger_attempts' => 0,
    ]);
    $message->save();
    $this->queueFactory->get(self::EMAIL_QUEUE)->createItem(['message_id' => (int) $message->id()]);
  }

}
