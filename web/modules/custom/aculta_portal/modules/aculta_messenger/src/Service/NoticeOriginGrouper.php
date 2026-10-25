<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;

/**
 * Groups the central notice list by origin and the latest notice in each group.
 */
final class NoticeOriginGrouper {

  /**
   * Human-readable labels in the stable order used by the central filters.
   *
   * @var array<string, string>
   */
  private const LABELS = [
    'comunicado' => 'Comunicados',
    'forum' => 'Fórum',
    'alerta' => 'Alertas do site',
    'amizade' => 'Amizades',
    'aprendizagem' => 'Aprendizagem',
    'wiki' => 'Wiki',
    'loja' => 'Loja',
    'doacao' => 'Doações',
    'conta' => 'Conta',
    'outros' => 'Outros avisos',
  ];

  /**
   * Groups notice rows into labeled sections with separators between origins.
   *
   * Rows from one source are returned in recency order. Their creation
   * timestamps also determine the order of origin sections, newest section
   * first. Rows from older integrations without timestamp metadata retain
   * their original position after timestamped rows.
   *
   * @param array $items
   *   Notice render arrays with optional `data-aculta-notice-origin` metadata.
   *
   * @return array
   *   Renderable origin sections, with each source's related notices grouped
   *   inside its own section.
   */
  public static function group(array $items): array {
    $groups = [];
    foreach ($items as $item) {
      $attributes = is_array($item) ? ($item['#attributes'] ?? []) : [];
      $origin = is_array($attributes) ? ($attributes['data-aculta-notice-origin'] ?? NULL) : NULL;
      if (!is_string($origin) || !isset(self::LABELS[$origin]) || $origin === 'outros') {
        $origin = 'outros';
      }
      $groups[$origin][] = $item;
    }

    $origins = array_keys($groups);
    $stableOrder = array_flip(array_keys(self::LABELS));
    usort($origins, static function (string $a, string $b) use ($groups, $stableOrder): int {
      $aCreated = self::latestTimestamp($groups[$a]);
      $bCreated = self::latestTimestamp($groups[$b]);
      return ($bCreated <=> $aCreated) ?: (($stableOrder[$a] ?? PHP_INT_MAX) <=> ($stableOrder[$b] ?? PHP_INT_MAX));
    });

    $result = [];
    foreach ($origins as $origin) {
      $groups[$origin] = self::sortByRecency($groups[$origin]);
      $section = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['aculta-notice-origin-section'],
          'data-aculta-notice-origin-section' => $origin,
        ],
      ];
      if ($result !== []) {
        $section['separator'] = [
          '#type' => 'html_tag',
          '#tag' => 'hr',
          '#attributes' => [
            'class' => ['aculta-notice-origin-separator'],
            'aria-hidden' => 'true',
          ],
        ];
      }
      $section['heading'] = [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h3',
        '#element_attributes' => new Attribute(['class' => ['aculta-notice-origin-heading']]),
        '#value' => self::label($origin),
      ];
      $section['items'] = [
        '#theme' => 'item_list',
        '#items' => NoticeRelatedGrouper::group($groups[$origin]),
        '#attributes' => ['class' => ['aculta-notice-origin-items']],
      ];
      $result[] = $section;
    }

    return $result;
  }

  /** Finds the newest timestamp in one section; zero means legacy/unknown. */
  private static function latestTimestamp(array $items): int {
    $latest = 0;
    foreach ($items as $item) {
      $created = $item['#attributes']['data-aculta-notice-created'] ?? 0;
      if (is_numeric($created)) {
        $latest = max($latest, (int) $created);
      }
    }
    return $latest;
  }

  /** Sorts one section newest first, retaining input order for equal/unknown dates. */
  private static function sortByRecency(array $items): array {
    $indexed = [];
    foreach (array_values($items) as $index => $item) {
      $created = $item['#attributes']['data-aculta-notice-created'] ?? 0;
      $indexed[] = [
        'item' => $item,
        'created' => is_numeric($created) ? (int) $created : 0,
        'index' => $index,
      ];
    }
    usort($indexed, static fn(array $a, array $b): int =>
      ($b['created'] <=> $a['created']) ?: ($a['index'] <=> $b['index']));
    return array_column($indexed, 'item');
  }

  /**
   * Creates a translatable label from a fixed origin allowlist.
   */
  private static function label(string $origin): TranslatableMarkup {
    return match ($origin) {
      'comunicado' => new TranslatableMarkup('Comunicados'),
      'forum' => new TranslatableMarkup('Fórum'),
      'alerta' => new TranslatableMarkup('Alertas do site'),
      'amizade' => new TranslatableMarkup('Amizades'),
      'aprendizagem' => new TranslatableMarkup('Aprendizagem'),
      'wiki' => new TranslatableMarkup('Wiki'),
      'loja' => new TranslatableMarkup('Loja'),
      'doacao' => new TranslatableMarkup('Doações'),
      'conta' => new TranslatableMarkup('Conta'),
      default => new TranslatableMarkup('Outros avisos'),
    };
  }

}
