<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\Core\Session\AccountInterface;

/**
 * Shared contract for adapters that contribute personal notices.
 */
interface PersonalNoticeAdapterInterface {

  /**
   * Adds notices currently accessible to this account to the central list.
   *
   * @param array $items
   *   Render items in the shared Messenger notice list.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account whose authorized notices are requested.
   * @param array $filters
   *   Normalized central source and read-state filters.
   */
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void;

  /**
   * Counts currently accessible unread notices for one account.
   */
  public function unreadCount(int $uid): int;

}
