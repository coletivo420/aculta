<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Lms\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface;

/**
 * Integrates personal LMS notices into the existing multidomain Messenger.
 */
final class LearningNoticeHooks {

  public function __construct(
    private readonly LearningNoticeManagerInterface $notices,
  ) {}

  /**
 * Adds notices to both Messenger drawer and account avisos through its existing alter. */
  #[Hook('aculta_messenger_personal_notices_alter')]
  public function personalNoticesAlter(array &$items, AccountInterface $account, array $filters = []): void {
    $this->notices->addPersonalNotices($items, $account, $filters);
  }

  /**
 * Adds currently accessible unread LMS records to Messenger counters. */
  #[Hook('aculta_messenger_personal_notice_count_alter')]
  public function unreadCountAlter(int &$count, int $uid): void {
    $count += $this->notices->unreadCount($uid);
  }

}
