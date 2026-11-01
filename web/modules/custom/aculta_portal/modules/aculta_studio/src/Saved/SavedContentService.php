<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Saved;

use Drupal\aculta_portal\Saved\SavedContentFilter;
use Drupal\aculta_portal\Saved\SavedContentQueryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Adapter providing access to saved content via SavedContentQueryInterface.
 *
 * Reuses transversal SavedContentQuery without direct SQL queries on flagging.
 */
class SavedContentService {

  /**
   * Constructs a SavedContentService instance.
   *
   * @param \Drupal\aculta_portal\Saved\SavedContentQueryInterface $savedContentQuery
   *   The transversal saved content query service.
   */
  public function __construct(
    protected readonly SavedContentQueryInterface $savedContentQuery,
  ) {}

  /**
   * Gets saved items flagged by the given account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to inspect.
   * @param string|null $category
   *   Optional category filter: 'saved', 'following', or NULL.
   * @param int $limit
   *   Maximum number of items to return.
   * @param int $offset
   *   Query offset.
   *
   * @return array<int, array{
   *   entity_type: string,
   *   entity_id: string|int,
   *   entity: \Drupal\Core\Entity\EntityInterface,
   *   flag_id: string,
   *   created: int,
   *   category: string,
   *   purpose: string|null
   *   }>
   *   Authorized saved items, ordered newest first.
   */
  public function getSavedItems(
    AccountInterface $account,
    ?string $category = 'saved',
    int $limit = 20,
    int $offset = 0,
  ): array {
    if (!$account->isAuthenticated()) {
      return [];
    }

    $filter = new SavedContentFilter(
      account: $account,
      category: $category,
      limit: $limit,
      offset: $offset,
    );

    $result = $this->savedContentQuery->query($filter);
    $items = [];
    foreach ($result->getItems() as $item) {
      $items[] = [
        'entity_type' => $item->getEntityTypeId(),
        'entity_id' => $item->getEntityId(),
        'entity' => $item->getEntity(),
        'flag_id' => $item->getFlagId(),
        'created' => $item->getCreatedTime(),
        'category' => $item->getCategory(),
        'purpose' => $item->getPurpose(),
      ];
    }

    return $items;
  }

  /**
   * Returns count of saved and followed items for an account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to inspect.
   *
   * @return array{saved: int, following: int, total: int}
   *   Aggregated counts.
   */
  public function getSavedCounts(AccountInterface $account): array {
    return $this->savedContentQuery->getCounts($account);
  }

  /**
   * Checks if a specific entity is flagged as saved by the user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string $entityType
   *   Entity type ID.
   * @param int|string $entityId
   *   Entity ID.
   *
   * @return bool
   *   TRUE if saved.
   */
  public function isSaved(AccountInterface $account, string $entityType, int|string $entityId): bool {
    return $this->savedContentQuery->isSaved($account, $entityType, $entityId);
  }

  /**
   * Gets cache tags for saved content queries for an account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return string[]
   *   Cache tags.
   */
  public function getCacheTags(AccountInterface $account): array {
    return [
      'flagging_list',
      'user:' . $account->id(),
    ];
  }

  /**
   * Gets the underlying transversal query service.
   *
   * @return \Drupal\aculta_portal\Saved\SavedContentQueryInterface
   *   The saved content query service.
   */
  public function getQueryService(): SavedContentQueryInterface {
    return $this->savedContentQuery;
  }

}
