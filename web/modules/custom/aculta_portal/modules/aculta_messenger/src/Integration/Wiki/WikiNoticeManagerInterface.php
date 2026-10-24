<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Wiki;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Public adapter contract for Wiki notices in the Messenger. */
interface WikiNoticeManagerInterface {

  /**
   * Persists an eligible Wiki event for its recipient. */
  public function record(MessengerNoticeEvent $event): ?MessageInterface;

  /**
   * Resolves a current, accessible source and its safe presentation. */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Adds accessible Wiki notices to the shared Messenger list. */
  public function addPersonalNotices(array &$items, AccountInterface $account): void;

  /**
   * Counts only unread notices whose source remains accessible. */
  public function unreadCount(int $uid): int;

}
