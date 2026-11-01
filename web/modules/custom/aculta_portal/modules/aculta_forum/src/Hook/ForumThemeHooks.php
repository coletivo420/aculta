<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registro do agrupamento de categorias do fórum como tema.
 */
class ForumThemeHooks {

  /**
   * Implements hook_theme().
   *
   * Registra o agrupamento da página como tema próprio para temas sem override
   * (como Olivero), usando o template do módulo com H2 em vez do H3 do Core.
   * O ACULTA420 sobrescreve o mesmo hook com seu template de grade.
   *
   * @return array<string, array<string, mixed>>
   *   Definições de tema para o agrupamento de categorias.
   */
  #[Hook('theme')]
  public function theme(mixed $existing, string $type, string $theme, string $path): array {
    return [
      'views_view_unformatted__aculta_forum_categories__page_categories' => [
        'template' => 'views-view-unformatted--aculta-forum-categories--page-categories',
        'path' => $path . '/templates',
        'base hook' => 'views_view_unformatted',
      ],
    ];
  }

}
