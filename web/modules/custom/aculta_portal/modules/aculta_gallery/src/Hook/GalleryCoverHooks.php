<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Capa da galeria: sempre uma das imagens da própria galeria.
 *
 * Regra da Fase 3 (0.1.0-dev.4): a capa é escolhida entre as imagens da galeria. Se a capa não estiver nas
 * imagens (ou estiver vazia com imagens presentes), a primeira imagem vira capa. Sem imagens, a capa é limpa.
 * A regra roda no presave, então vale para formulário, importação e script.
 */
final class GalleryCoverHooks {

  /**
   * Implements hook_media_gallery_presave().
   */
  #[Hook('media_gallery_presave')]
  public function mediaGalleryPresave(EntityInterface $gallery): void {
    $images = array_column($gallery->get('images')->getValue(), 'target_id');
    $cover = $gallery->get('field_gallery_cover')->target_id ?? NULL;

    if ($images === []) {
      if ($cover !== NULL) {
        $gallery->set('field_gallery_cover', NULL);
      }
      return;
    }
    if ($cover === NULL || !in_array((int) $cover, array_map('intval', $images), TRUE)) {
      $gallery->set('field_gallery_cover', ['target_id' => $images[0]]);
    }
  }

}
