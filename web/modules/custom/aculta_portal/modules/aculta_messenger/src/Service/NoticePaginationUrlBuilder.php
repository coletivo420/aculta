<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Url;

/**
 * Builds account notice pagination URLs without dropping sibling state.
 */
final class NoticePaginationUrlBuilder {

  /**
   * Supported notice origins.
   *
   * @var string[]
   */
  private const ORIGINS = ['comunicado', 'forum', 'alerta', 'amizade', 'aprendizagem', 'wiki', 'loja', 'doacao'];

  /**
   * Builds a URL for one origin in the global, independently paged list.
   *
   * @param array<string, int> $currentOffsets
   *   Current offsets for all origins.
   * @param string $origin
   *   Source whose page changes.
   * @param int $targetOffset
   *   New source offset; zero removes that source's current offset.
   * @param array<string, string> $filters
   *   Normalized origin/read-state filters.
   */
  public static function globalPage(array $currentOffsets, string $origin, int $targetOffset, array $filters): Url {
    if (!in_array($origin, self::ORIGINS, TRUE)) {
      throw new \InvalidArgumentException('Unsupported notice pagination origin.');
    }
    $offsets = [];
    foreach ($currentOffsets as $key => $offset) {
      if (is_string($key) && in_array($key, self::ORIGINS, TRUE) && is_int($offset) && $offset > 0) {
        $offsets[$key] = $offset;
      }
    }
    if ($targetOffset > 0) {
      $offsets[$origin] = $targetOffset;
    }
    else {
      unset($offsets[$origin]);
    }

    $query = array_filter($filters, static fn(mixed $value): bool => is_string($value) && $value !== '');
    $query['notice_offsets'] = $offsets;
    return Url::fromRoute('aculta_messenger.avisos', ['aba' => 'avisos'], ['query' => $query]);
  }

  /**
   * Builds a URL for a source-filtered list with its legacy single offset.
   *
   * @param string $origin
   *   The source selected by the origin filter.
   * @param int $targetOffset
   *   Offset of the requested source page.
   * @param array<string, string> $filters
   *   Normalized origin/read-state filters.
   */
  public static function filteredPage(string $origin, int $targetOffset, array $filters): Url {
    if (!in_array($origin, self::ORIGINS, TRUE)) {
      throw new \InvalidArgumentException('Unsupported notice pagination origin.');
    }
    $query = array_filter($filters, static fn(mixed $value): bool => is_string($value) && $value !== '');
    $query[$origin === 'forum' ? 'forum_offset' : 'notice_offset'] = max(0, $targetOffset);
    return Url::fromRoute('aculta_messenger.avisos', ['aba' => 'avisos'], ['query' => $query]);
  }

}
