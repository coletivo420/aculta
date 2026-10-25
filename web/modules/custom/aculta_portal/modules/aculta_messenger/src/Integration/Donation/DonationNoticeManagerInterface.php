<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Drupal\aculta_messenger\Aviso\PaginatedPersonalNoticeAdapterInterface;

/**
 * Persists, resolves and delivers personal Donation notices. */
interface DonationNoticeManagerInterface extends PaginatedPersonalNoticeAdapterInterface {

  /**
   * Records one order-created or payment-approved event idempotently. */
  public function record(MessengerNoticeEvent $event): ?MessageInterface;

  /**
   * Resolves an owned Donation notice against its current source access. */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Marks an authorized, currently accessible notice as read. */
  public function markRead(MessageInterface $message, AccountInterface $account): bool;

  /**
   * Sets the read state after revalidating ownership and donation access.
   */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

}
