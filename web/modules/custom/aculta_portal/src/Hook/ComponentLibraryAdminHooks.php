<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Estilos de colunas da biblioteca de componentes, também no tema administrativo (GIN).
 */
final class ComponentLibraryAdminHooks {

  /**
   * Rotas da biblioteca de componentes do UI Patterns.
   */
  private const ROUTES = [
    'ui_patterns_library.overview',
    'ui_patterns_library.provider',
    'ui_patterns_library.single',
  ];

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (in_array(\Drupal::routeMatch()->getRouteName(), self::ROUTES, TRUE)) {
      $attachments['#attached']['library'][] = 'aculta420_v2/ui-library-admin';
    }
  }

}
