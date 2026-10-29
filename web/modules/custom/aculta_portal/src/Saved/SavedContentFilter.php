<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Saved;

use Drupal\Core\Session\AccountInterface;

/**
 * Filter criteria for saved content queries.
 */
final class SavedContentFilter {

  /**
   * Maximum allowed items per page.
   */
  public const MAX_LIMIT = 100;

  /**
   * Constructs a SavedContentFilter instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account whose saved items are queried.
   * @param string|null $category
   *   Filter category ('saved', 'following', or NULL).
   * @param string|null $entityTypeId
   *   Optional entity type ID filter.
   * @param string|null $bundle
   *   Optional bundle filter.
   * @param string|null $purpose
   *   Optional canonical purpose filter.
   * @param int $limit
   *   Number of items per page.
   * @param int $offset
   *   Query offset.
   */
  public function __construct(
    private readonly AccountInterface $account,
    private readonly ?string $category = NULL,
    private readonly ?string $entityTypeId = NULL,
    private readonly ?string $bundle = NULL,
    private readonly ?string $purpose = NULL,
    private readonly int $limit = 20,
    private readonly int $offset = 0,
  ) {
    if ($limit < 1 || $limit > self::MAX_LIMIT) {
      throw new \InvalidArgumentException(sprintf('Query limit must be between 1 and %d.', self::MAX_LIMIT));
    }
    if ($offset < 0) {
      throw new \InvalidArgumentException('Query offset must not be negative.');
    }
  }

  /**
   * Gets the account.
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

  /**
   * Gets the category filter.
   */
  public function getCategory(): ?string {
    return $this->category;
  }

  /**
   * Gets the entity type ID filter.
   */
  public function getEntityTypeId(): ?string {
    return $this->entityTypeId;
  }

  /**
   * Gets the bundle filter.
   */
  public function getBundle(): ?string {
    return $this->bundle;
  }

  /**
   * Gets the purpose filter.
   */
  public function getPurpose(): ?string {
    return $this->purpose;
  }

  /**
   * Gets the limit.
   */
  public function getLimit(): int {
    return $this->limit;
  }

  /**
   * Gets the offset.
   */
  public function getOffset(): int {
    return $this->offset;
  }

}
