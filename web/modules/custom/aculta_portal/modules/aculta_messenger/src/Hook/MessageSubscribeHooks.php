<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\message\MessageInterface;

/** Adapts Message Subscribe to the existing Portuguese forum follow flag. */
final class MessageSubscribeHooks {

  /**
   * Forum subscriptions remain notices, never Message Subscribe messages (F-D21).
   */
  #[Hook('message_subscribe_get_subscribers')]
  public function getSubscribers(MessageInterface $message, array $subscribeOptions = [], array $context = []): array {
    return [];
  }

}
