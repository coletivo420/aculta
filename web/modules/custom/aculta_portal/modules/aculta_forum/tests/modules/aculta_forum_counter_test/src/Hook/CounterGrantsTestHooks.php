<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_counter_test\Hook;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutable;

/**
 * Provides the restricted Forum grant realm for counter coverage.
 */
final class CounterGrantsTestHooks {

  /**
   * Realm used by the fixture's node_access records.
   */
  public const REALM = 'aculta_forum_counter_test';

  /**
   * State key that adds the node access tag to the counter View.
   */
  public const ADD_TAG_STATE = 'aculta_forum_counter_test.add_node_access_tag';

  /**
   * Implements hook_views_query_alter().
   *
   * EntityReverse does not copy the node access tag of its base table, so a
   * taxonomy-based counter over nodes must add it explicitly.
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(ViewExecutable $view): void {
    if ($view->id() === 'test_forum_category_counter'
      && (bool) \Drupal::state()->get(self::ADD_TAG_STATE, FALSE)) {
      $view->getQuery()->addTag('node_access');
    }
  }

  /**
   * Implements hook_node_grants().
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, string $op): array {
    if ($op === 'view' && $account->hasPermission('view restricted forum counter topics')) {
      return [self::REALM => [1]];
    }
    return [];
  }

}
