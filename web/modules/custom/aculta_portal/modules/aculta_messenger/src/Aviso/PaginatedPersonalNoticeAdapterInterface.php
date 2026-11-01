<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\Core\Session\AccountInterface;

/**
 * Optional pagination contract for personal notice adapters.
 */
interface PaginatedPersonalNoticeAdapterInterface extends PersonalNoticeAdapterInterface {

  /**
   * Adds one authorized page and reports whether more candidates exist.
   *
   * @param array $items
   *   Render items contributed by the current page.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account whose accessible notices are requested.
   * @param array $filters
   *   Normalized source and read-state filters.
   * @param int $limit
   *   Maximum number of candidates to load.
   * @param int $offset
   *   Number of candidates to skip.
   *
   * @return bool
   *   Whether another candidate page is available.
   */
  public function addPersonalNoticesPage(array &$items, AccountInterface $account, array $filters, int $limit, int $offset): bool;

}
