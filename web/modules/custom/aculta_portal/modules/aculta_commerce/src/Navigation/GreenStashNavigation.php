<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Navigation;

use Drupal\Core\Url;

/**
 * Safe, read-only contextual navigation for ACULTA Studio Green Stash.
 *
 * Operational data and actions belong to authorized Commerce providers,
 * not to the navigation layer.
 */
final class GreenStashNavigation {

  public const AREAS = [
    'overview' => 'Visão geral',
    'products' => 'Produtos',
    'orders' => 'Pedidos',
    'inventory' => 'Estoque',
    'shipping' => 'Envios',
    'promotions' => 'Promoções',
    'customers' => 'Clientes',
    'reports' => 'Relatórios',
    'settings' => 'Configurações',
  ];

  public const SLUGS = [
    'visao-geral' => 'overview',
    'produtos' => 'products',
    'envios' => 'shipping',
  ];

  /**
   * Navigation sections whose real Studio consumers are implemented.
   *
   * Sections without a controller/Form API consumer remain undiscoverable and
   * their routes return 404 until the operation and its access gates exist.
   */
  public const AVAILABLE = ['overview', 'products', 'shipping'];

  /**
   * Builds safe navigation and a non-operational notice for the selected area.
   *
   * @param string $section
   *   Public area slug.
   *
   * @return array
   *   Navigation render array.
   */
  public function build(string $section = 'visao-geral'): array {
    $section = self::SLUGS[$section] ?? '';
    if (!isset(self::AREAS[$section])) {
      throw new \InvalidArgumentException('Unknown Green Stash area.');
    }

    $links = [];
    foreach (self::SLUGS as $slug => $key) {
      if (!in_array($key, self::AVAILABLE, TRUE)) {
        continue;
      }
      $label = self::AREAS[$key];
      $links[$slug] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => Url::fromRoute('aculta_commerce.green_stash_area', ['secao' => $slug]),
        '#attributes' => [
          'data-portal-ui' => 'green-stash-navigation-link',
          'aria-current' => $section === $key ? 'page' : 'false',
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'green-stash-entry'],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => self::AREAS[$section],
      ],
      'navigation' => [
        '#type' => 'container',
        '#attributes' => [
          'role' => 'navigation',
          'aria-label' => 'Áreas do ACULTA STUDIO GREEN STASH',
          'data-portal-ui' => 'green-stash-navigation',
        ],
        'items' => $links,
      ],
      'notice' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Esta área está em preparação. Nenhuma operação comercial está disponível nesta tela.',
      ],
      '#cache' => ['contexts' => ['user.permissions', 'url.path', 'url.site']],
    ];
  }

}
