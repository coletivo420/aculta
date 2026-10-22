<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Query;

use Drupal\Core\Session\AccountInterface;

/**
 * Selects a bounded page and revalidates source Entity Access.
 */
interface ContentIndexQueryInterface {

  /**
   * Queries catalog candidates and returns only currently authorized entities.
   *
   * @param array<string, mixed> $filters
   *   Allowlisted catalog filters.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account for access checks and cursor ownership.
   * @param string|null $cursor
   *   Opaque continuation token from a prior page.
   * @param int $limit
   *   Maximum candidates to scan, from 1 through 100.
   *
   * @return \Drupal\aculta_index\Query\ContentIndexQueryResult
   *   Authorized page and continuation token.
   */
  public function query(array $filters, AccountInterface $account, ?string $cursor = NULL, int $limit = 25): ContentIndexQueryResult;

}
