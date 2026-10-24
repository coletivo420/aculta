<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Wiki\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;

/**
 * Adds authorized Wiki notices to the shared drawer and unread count. */
final class WikiNoticeHooks {

  public function __construct(
    private readonly WikiNoticeManagerInterface $notices,
  ) {}

  /**
 * Adds personal Wiki records to the existing notices list. */
  #[Hook('aculta_messenger_personal_notices_alter')]
  public function noticesAlter(array &$items, AccountInterface $account, array $filters = []): void {
    $this->notices->addPersonalNotices($items, $account, $filters);
  }

  /**
 * Adds currently authorized unread Wiki records to the shared count. */
  #[Hook('aculta_messenger_personal_notice_count_alter')]
  public function countAlter(int &$count, int $uid): void {
    $count += $this->notices->unreadCount($uid);
  }

}
