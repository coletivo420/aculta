<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Friendship;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\aculta_user\Event\FriendshipChangedEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adapts identity-owned friendship transitions to the shared notice API. */
final class FriendshipNoticeEventProducer implements EventSubscriberInterface {

  public const REQUESTED = 'aculta_user.friendship_requested';

  public const ACCEPTED = 'aculta_user.friendship_accepted';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerNoticePublisherInterface $publisher,
  ) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [FriendshipChangedEvent::NAME => 'publish'];
  }

  /**
   * Publishes only supported, source-verified transitions. */
  public function publish(FriendshipChangedEvent $event): void {
    if (!in_array($event->transition, [FriendshipChangedEvent::REQUESTED, FriendshipChangedEvent::ACCEPTED], TRUE)
      || $event->requesterId < 1 || $event->recipientId < 1 || $event->requesterId === $event->recipientId
      || $event->changed < 1) {
      return;
    }

    $friendship = $this->entityTypeManager->getStorage('aculta_friendship')->load($event->friendshipId);
    if (!$friendship instanceof AcultaFriendshipInterface
      || $friendship->getRequesterId() !== $event->requesterId
      || $friendship->getRecipientId() !== $event->recipientId) {
      return;
    }

    $recipientId = $event->transition === FriendshipChangedEvent::REQUESTED
      ? $event->recipientId
      : $event->requesterId;
    $eventType = $event->transition === FriendshipChangedEvent::REQUESTED ? self::REQUESTED : self::ACCEPTED;
    $uuid = $friendship->uuid();
    if (!is_string($uuid) || $uuid === '') {
      return;
    }

    $changed = (string) $event->changed;
    $this->publisher->publish(new MessengerNoticeEvent(
      $eventType,
      'aculta_user',
      'aculta_friendship',
      $uuid,
      $changed,
      $recipientId,
      'account',
      (new \DateTimeImmutable())->setTimestamp($event->changed),
      implode(':', ['user', 'friendship', $uuid, $event->transition, $changed, (string) $recipientId]),
      ['transition' => $event->transition],
    ));
  }

}
