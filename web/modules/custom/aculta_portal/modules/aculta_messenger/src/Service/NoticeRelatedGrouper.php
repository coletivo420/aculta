<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Groups authorized notice render items that refer to one source entity.
 */
final class NoticeRelatedGrouper {

  /**
   * Creates an opaque key for notices tied to the same source entity.
   */
  public static function sourceKey(string $origin, string $sourceUuid): ?string {
    $sourceUuid = trim($sourceUuid);
    if (!in_array($origin, NoticeListFilter::ORIGINS, TRUE)
      || $sourceUuid === ''
      || preg_match('/[\x00-\x1F\x7F]/', $sourceUuid) === 1) {
      return NULL;
    }

    return hash('sha256', $origin . "\0" . $sourceUuid);
  }

  /**
   * Returns render attributes only when a source can be grouped safely.
   */
  public static function sourceAttribute(string $origin, string $sourceUuid): array {
    $key = self::sourceKey($origin, $sourceUuid);
    return $key === NULL ? [] : ['data-aculta-notice-group-key' => $key];
  }

  /**
   * Groups repeated source keys without dropping individual notice actions.
   *
   * The grouping key is removed before rendering. Source adapters opt in by
   * adding its opaque hash to `#attributes`; unrelated items remain flat.
   */
  public static function group(array $items): array {
    $ordered = [];
    $positions = [];
    foreach (array_values($items) as $item) {
      if (!is_array($item)) {
        $ordered[] = ['items' => [$item]];
        continue;
      }
      $attributes = $item['#attributes'] ?? [];
      $key = is_array($attributes) ? ($attributes['data-aculta-notice-group-key'] ?? NULL) : NULL;
      if (is_array($attributes)) {
        unset($attributes['data-aculta-notice-group-key']);
        $item['#attributes'] = $attributes;
      }
      if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) {
        $ordered[] = ['items' => [$item]];
        continue;
      }
      if (isset($positions[$key])) {
        $ordered[$positions[$key]]['items'][] = $item;
        continue;
      }
      $positions[$key] = count($ordered);
      $ordered[] = ['items' => [$item]];
    }

    $result = [];
    foreach ($ordered as $entry) {
      $members = $entry['items'];
      if (count($members) < 2) {
        $result[] = $members[0];
        continue;
      }
      $result[] = [
        '#type' => 'details',
        '#title' => new TranslatableMarkup('@count avisos relacionados', ['@count' => count($members)]),
        '#attributes' => ['class' => ['aculta-notice-related-group']],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $members,
          '#attributes' => ['class' => ['aculta-notice-related-group__items']],
        ],
      ];
    }
    return $result;
  }

}
