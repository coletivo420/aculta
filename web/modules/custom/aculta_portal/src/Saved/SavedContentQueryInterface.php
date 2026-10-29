<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Saved;

use Drupal\Core\Session\AccountInterface;

/**
 * Universal query contract for user-saved content across ACULTA modules.
 *
 * Reuses existing Flag entity storage without parallel tables. Enforces
 * strict Entity Access checks prior to exposing any candidate entity.
 */
interface SavedContentQueryInterface {

  /**
   * Known flag IDs categorized as bookmarks/saved.
   */
  public const CATEGORY_SAVED = 'saved';

  /**
   * Known flag IDs categorized as followed items.
   */
  public const CATEGORY_FOLLOWING = 'following';

  /**
   * Moderation and abuse flags excluded from personal bookmarks.
   */
  public const EXCLUDED_FLAGS = [
    'abuse',
    'sinalizar_topico',
    'sinalizar_comentario',
    'sinalizar_post_forum',
    'claim_ownership',
    'aculta_notice_read',
    'aculta_site_alert_read',
    'aculta_site_alert_dismissed',
  ];

  /**
   * Queries saved or followed items matching the given criteria.
   *
   * @param \Drupal\aculta_portal\Saved\SavedContentFilter $filter
   *   Filter criteria including account, pagination, category and purpose.
   *
   * @return \Drupal\aculta_portal\Saved\SavedContentQueryResult
   *   Authorized results with cacheability metadata.
   */
  public function query(SavedContentFilter $filter): SavedContentQueryResult;

  /**
   * Returns aggregated counts of saved and followed items for an account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to inspect.
   *
   * @return array{saved: int, following: int, total: int}
   *   Array containing counts for saved, following, and total items.
   */
  public function getCounts(AccountInterface $account): array;

  /**
   * Checks whether a specific entity is flagged as saved by the account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string $entityTypeId
   *   Entity type ID.
   * @param string|int $entityId
   *   Entity ID.
   *
   * @return bool
   *   TRUE if saved.
   */
  public function isSaved(AccountInterface $account, string $entityTypeId, string|int $entityId): bool;

}
