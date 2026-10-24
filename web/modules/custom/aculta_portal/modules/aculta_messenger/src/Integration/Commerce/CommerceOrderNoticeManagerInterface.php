<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Commerce;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Shared interface for private Commerce order notices. */
interface CommerceOrderNoticeManagerInterface {

  /**
   * Persists an eligible event for the order's active customer. */
  public function record(MessengerNoticeEvent $event): ?MessageInterface;

  /**
   * Resolves the order and current access for an account. */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Adds authorized Commerce notices to Messenger's personal list. */
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void;

  /**
   * Counts unread notices that remain accessible to the customer. */
  public function unreadCount(int $uid): int;

  /**
   * Sets the read state after revalidating ownership and order access.
   */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

}
