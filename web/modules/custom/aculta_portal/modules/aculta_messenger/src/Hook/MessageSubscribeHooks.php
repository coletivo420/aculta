<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\flag\FlagServiceInterface;
use Drupal\message\MessageInterface;
use Drupal\message_subscribe\Subscribers\DeliveryCandidate;
use Drupal\node\NodeInterface;

/** Adapts Message Subscribe to the existing Portuguese forum follow flag. */
final class MessageSubscribeHooks {

  private const TEMPLATE = 'aculta_messenger_forum_topic_published';

  private const SOURCE_FIELD = 'field_aculta_messenger_source_node';

  public function __construct(
    private readonly FlagServiceInterface $flagService,
  ) {}

  /**
   * Returns users following this forum topic through `seguir_topico`.
   *
   * Message Subscribe applies account status and entity view access after this
   * hook, then adds configured native notifiers.
   *
   * @return \Drupal\message_subscribe\Subscribers\DeliveryCandidate[]
   *   Candidates keyed by account ID.
   */
  #[Hook('message_subscribe_get_subscribers')]
  public function getSubscribers(MessageInterface $message, array $subscribeOptions = [], array $context = []): array {
    if ($message->bundle() !== self::TEMPLATE || !$message->hasField(self::SOURCE_FIELD)) {
      return [];
    }

    $node = $message->get(self::SOURCE_FIELD)->entity;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'forum') {
      return [];
    }

    $flag = $this->flagService->getFlagById('seguir_topico');
    if ($flag === NULL) {
      return [];
    }

    $candidates = [];
    foreach ($this->flagService->getEntityFlaggings($flag, $node) as $flagging) {
      $uid = (int) $flagging->getOwnerId();
      if ($uid > 0) {
        $candidates[$uid] = new DeliveryCandidate(['seguir_topico'], [], $uid);
      }
    }

    return $candidates;
  }

}
