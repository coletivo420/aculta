<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManagerInterface;

/**
 * Adds Donation notices to the shared account list and unread count. */
final class DonationNoticeHooks {

  public function __construct(private readonly DonationNoticeManagerInterface $notices) {}

  /**
 * Adds authorized Donation records to the shared notices list. */
  #[Hook('aculta_messenger_personal_notices_alter')]
  public function noticesAlter(array &$items, AccountInterface $account): void {
    $this->notices->addPersonalNotices($items, $account);
  }

  /**
 * Adds accessible unread Donation records to the shared count. */
  #[Hook('aculta_messenger_personal_notice_count_alter')]
  public function countAlter(int &$count, int $uid): void {
    $count += $this->notices->unreadCount($uid);
  }

}
