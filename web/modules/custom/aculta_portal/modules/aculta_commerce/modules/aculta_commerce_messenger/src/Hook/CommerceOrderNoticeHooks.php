<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_commerce_messenger\CommerceOrderNoticeManagerInterface;

/**
 * Adds authorized Commerce order notices to the shared drawer and unread count. */
final class CommerceOrderNoticeHooks {

  public function __construct(
    private readonly CommerceOrderNoticeManagerInterface $notices,
  ) {}

  /**
 * Adds personal Loja records to the existing notices list. */
  #[Hook('aculta_messenger_personal_notices_alter')]
  public function noticesAlter(array &$items, AccountInterface $account): void {
    $this->notices->addPersonalNotices($items, $account);
  }

  /**
 * Adds currently authorized unread Loja records to the shared count. */
  #[Hook('aculta_messenger_personal_notice_count_alter')]
  public function countAlter(int &$count, int $uid): void {
    $count += $this->notices->unreadCount($uid);
  }

}
