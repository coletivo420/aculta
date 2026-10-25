<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Mutable per-request pagination result shared with a selected notice adapter.
 */
final class PersonalNoticePageState {

  public const PAGE_SIZE = 100;

  /**
   * Whether the selected adapter has more candidate rows.
   *
   * @var bool
   */
  private bool $hasNext = FALSE;

  /**
   * Constructs page state for one selected notice origin.
   *
   * @param string $origin
   *   Normalized notice origin.
   * @param int $offset
   *   Number of candidates to skip.
   */
  public function __construct(
    private readonly string $origin,
    private readonly int $offset,
  ) {}

  /**
   * Returns the selected notice origin.
   */
  public function origin(): string {
    return $this->origin;
  }

  /**
   * Returns the current page offset.
   */
  public function offset(): int {
    return $this->offset;
  }

  /**
   * Whether the adapter found another candidate page.
   */
  public function hasNext(): bool {
    return $this->hasNext;
  }

  /**
   * Records whether the adapter found another candidate page.
   */
  public function setHasNext(bool $hasNext): void {
    $this->hasNext = $hasNext;
  }

  /**
   * Slices already-authorized and filtered rows for this page.
   *
   * @param array $items
   *   Rows with access and read-state filters already applied.
   * @param int $limit
   *   Maximum number of rows to return.
   *
   * @return array
   *   The requested page. `hasNext()` reports whether an extra row existed.
   */
  public function slice(array $items, int $limit): array {
    if ($limit < 1) {
      throw new \InvalidArgumentException('The page size must be positive.');
    }
    $page = array_slice($items, $this->offset, $limit + 1);
    $this->hasNext = count($page) > $limit;
    return array_slice($page, 0, $limit);
  }

}
