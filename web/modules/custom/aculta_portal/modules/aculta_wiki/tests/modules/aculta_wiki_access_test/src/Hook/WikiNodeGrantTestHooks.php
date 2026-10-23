<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki_access_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Supplies one private Wiki grant per account for integration tests.
 */
final class WikiNodeGrantTestHooks {

  /**
   * Implements hook_node_grants().
   *
   * @return array<string, array<int, string>>
   *   A grant matching only this account's UID.
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, string $operation): array {
    if ($operation !== 'view') {
      return [];
    }
    return ['wiki_test_author' => [(string) $account->id()]];
  }

  /**
   * Implements hook_node_access_records().
   *
   * @return array<int, array<string, int|string>>
   *   The owner-only view grant for Wiki entries.
   */
  #[Hook('node_access_records')]
  public function nodeAccessRecords(NodeInterface $node): array {
    if ($node->bundle() !== 'wiki_entry' || !$node->hasField('field_wiki_test_grant_uid') || $node->get('field_wiki_test_grant_uid')->isEmpty()) {
      return [];
    }
    return [[
      'realm' => 'wiki_test_author',
      'gid' => (int) $node->get('field_wiki_test_grant_uid')->value,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ],
    ];
  }

}
