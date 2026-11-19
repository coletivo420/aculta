<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Support;

/**
 * Identifica as rotas administrativas da UI Patterns Library.
 *
 * A biblioteca é uma exceção de apresentação: não recebe container nem
 * variáveis de purpose, e usa a variante de largura total.
 */
final class ComponentLibraryRoutes {

  /**
   * Rotas da UI Patterns Library.
   *
   * @var list<string>
   */
  private const ROUTES = [
    'ui_patterns_library.overview',
    'ui_patterns_library.provider',
    'ui_patterns_library.single',
  ];

  /**
   * Informa se a rota pertence à UI Patterns Library.
   */
  public static function isComponentLibraryRoute(?string $routeName): bool {
    return $routeName !== NULL && in_array($routeName, self::ROUTES, TRUE);
  }

}
