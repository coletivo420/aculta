<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Navigation;

use Drupal\Core\Url;

/**
 * Safe, read-only navigation skeleton for Green Stash.
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
    'pedidos' => 'orders',
    'estoque' => 'inventory',
    'envios' => 'shipping',
    'promocoes' => 'promotions',
    'clientes' => 'customers',
    'relatorios' => 'reports',
    'configuracoes' => 'settings',
  ];

  public function build(string $section = 'visao-geral'): array {
    $section = self::SLUGS[$section] ?? '';
    if (!isset(self::AREAS[$section])) {
      throw new \InvalidArgumentException('Unknown Green Stash area.');
    }

    $links = [];
    foreach (self::SLUGS as $slug => $key) {
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
          'aria-label' => 'Áreas do Green Stash',
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
