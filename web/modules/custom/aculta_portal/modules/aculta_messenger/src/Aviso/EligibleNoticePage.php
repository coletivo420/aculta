<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Builds notice pages after the source-specific access check has run.
 */
final class EligibleNoticePage {

  /**
   * Collects a page by offsetting only items accepted by the resolver.
   *
   * @param callable(int, int): array $queryIds
   *   Queries one stable batch of candidate IDs for scan offset and batch size.
   * @param callable(array): array $loadMultiple
   *   Loads the candidates returned by the query callback.
   * @param callable(mixed): mixed $resolve
   *   Revalidates the candidate and returns its display data, or NULL when it
   *   is no longer eligible for this account.
   * @param int $limit
   *   Number of eligible items in the page.
   * @param int $offset
   *   Number of eligible items to skip.
   *
   * @return array{items: array, has_next: bool}
   *   Eligible entity/display-data pairs and whether another eligible item
   *   exists after the page.
   */
  public static function collect(callable $queryIds, callable $loadMultiple, callable $resolve, int $limit, int $offset): array {
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $batchSize = max(20, $limit);
    $scanOffset = 0;
    $eligibleSkipped = 0;
    $items = [];
    $hasNext = FALSE;

    do {
      $ids = $queryIds($scanOffset, $batchSize);
      if ($ids === []) {
        break;
      }
      $scanOffset += count($ids);
      foreach ($loadMultiple($ids) as $entity) {
        $resolved = $resolve($entity);
        if ($resolved === NULL) {
          continue;
        }
        if ($eligibleSkipped < $offset) {
          $eligibleSkipped++;
          continue;
        }
        if (count($items) >= $limit) {
          $hasNext = TRUE;
          break 2;
        }
        $items[] = ['entity' => $entity, 'resolved' => $resolved];
      }
    } while (count($ids) === $batchSize);

    return ['items' => $items, 'has_next' => $hasNext];
  }

}
