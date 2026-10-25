<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps Private Message's inbox marker aligned with the Messenger counter. */
final class PrivateMessageUnreadIndicatorHooks {

  public function __construct(
    #[Autowire(service: 'aculta_messenger.unread_thread_counter')]
    private readonly UnreadThreadCounter $unreadThreadCounter,
  ) {}

  /**
   * Removes the contrib marker when Messenger's canonical rule says read.
   *
   * Private Message uses `last_access <= message.created`, while the shared
   * Messenger counter uses `message.created > last_access`. At second-level
   * timestamp precision those rules disagree exactly at equality. Keep the
   * inbox decoration consistent with the counter without changing contrib.
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof PrivateMessageThreadInterface || $display->getMode() !== 'inbox') {
      return;
    }
    $prefix = $build['#prefix'] ?? NULL;
    if (!is_string($prefix)
      || preg_match('/class=["\'][^"\']*\bunread-thread\b[^"\']*["\']/', $prefix) !== 1
      || $this->unreadThreadCounter->isUnread($entity)) {
      return;
    }
    $build['#prefix'] = preg_replace_callback(
      '/(class=["\'])([^"\']*)(["\'])/',
      static function (array $matches): string {
        $classes = preg_split('/\s+/', trim($matches[2])) ?: [];
        $classes = array_values(array_filter($classes, static fn(string $class): bool => $class !== 'unread-thread'));
        return $matches[1] . implode(' ', $classes) . $matches[3];
      },
      $prefix,
      1,
    ) ?? $prefix;
  }

}
