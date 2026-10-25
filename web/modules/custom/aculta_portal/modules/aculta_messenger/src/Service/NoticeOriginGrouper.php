<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;

/**
 * Groups the central notice list by origin, preserving each origin's order.
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
   * The rows from a source are already returned in that source's recency
   * order. This method groups by origin without changing that order.
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

    $result = [];
    foreach (array_keys(self::LABELS) as $origin) {
      if (!isset($groups[$origin])) {
        continue;
      }
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
