<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Persists, resolves and delivers personal Donation notices. */
interface DonationNoticeManagerInterface {

  /**
   * Records one order-created or payment-approved event idempotently. */
  public function record(MessengerNoticeEvent $event): ?MessageInterface;

  /**
   * Resolves an owned Donation notice against its current source access. */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Adds authorized Donation notices to the shared personal notices list. */
  public function addPersonalNotices(array &$items, AccountInterface $account): void;

  /**
   * Counts unread notices that remain accessible to this recipient. */
  public function unreadCount(int $uid): int;

  /**
   * Marks an authorized, currently accessible notice as read. */
  public function markRead(MessageInterface $message, AccountInterface $account): bool;

}
