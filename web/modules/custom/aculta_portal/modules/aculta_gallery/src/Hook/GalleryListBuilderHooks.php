<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Hook;

use Drupal\aculta_gallery\ListBuilder\AcultaGalleryListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Troca a coleção administrativa de galerias pela versão que aponta para a Revista.
 */
final class GalleryListBuilderHooks {

  /**
   * Implements hook_entity_type_alter().
   *
   * @param array<string, \Drupal\Core\Entity\EntityTypeInterface> $entity_types
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $type = $entity_types['media_gallery'] ?? NULL;
    if ($type instanceof EntityTypeInterface) {
      $type->setHandlerClass('list_builder', AcultaGalleryListBuilder::class);
    }
  }

}
