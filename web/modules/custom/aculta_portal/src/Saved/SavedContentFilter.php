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

  public function getAccount(): AccountInterface {
    return $this->account;
  }

  public function getCategory(): ?string {
    return $this->category;
  }

  public function getEntityTypeId(): ?string {
    return $this->entityTypeId;
  }

  public function getBundle(): ?string {
    return $this->bundle;
  }

  public function getPurpose(): ?string {
    return $this->purpose;
  }

  public function getLimit(): int {
    return $this->limit;
  }

  public function getOffset(): int {
    return $this->offset;
  }

}
