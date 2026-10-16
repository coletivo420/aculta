<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

/**
 * Catálogo de finalidades de imagem do ACULTA (aculta_image, seção 3.1 do plano).
 *
 * Única fonte de nomes de estilo de imagem do site. Quem precisa de uma imagem pede uma finalidade, não um estilo.
 * Os estilos listados em STYLES são os que existem em config/sync; o gate (scripts/validate-aculta-image.php) reprova
 * estilo exportado que não esteja aqui. Estilos `legado_*` são mantidos até a revisão da fase M3.
 */
final class ImageCatalog {

  /** Todos os estilos de imagem aceitos no projeto (espelho de config/sync/image.style.*.yml). */
  public const STYLES = [
    'aculta_avatar',
    'aculta_card_1x',
    'aculta_card_2x',
    'crop_thumbnail',
    'large',
    'max_1300x1300',
    'max_2600x2600',
    'max_325x325',
    'max_650x650',
    'media_gallery_image',
    'media_gallery_lightbox',
    'media_gallery_stage',
    'media_gallery_thumb',
    'media_library',
    'medium',
    'produto_galeria',
    'thumbnail',
    'wide',
  ];

  /** Finalidade => estilo. Novas finalidades entram aqui antes de qualquer uso. */
  public const PURPOSES = [
    'avatar' => 'aculta_avatar',
    'card' => 'aculta_card_1x',
    'card_2x' => 'aculta_card_2x',
    'galeria_miniatura' => 'media_gallery_thumb',
    'galeria_card' => 'media_gallery_image',
    'galeria_visualizacao' => 'media_gallery_stage',
    'galeria_lightbox' => 'media_gallery_lightbox',
    'biblioteca_midia' => 'media_library',
    'miniatura' => 'thumbnail',
    'media' => 'medium',
    'grande' => 'large',
    'largo' => 'wide',
    'recorte_quadrado' => 'crop_thumbnail',
    'produto_galeria' => 'produto_galeria',
    'legado_max_325' => 'max_325x325',
    'legado_max_650' => 'max_650x650',
    'legado_max_1300' => 'max_1300x1300',
    'legado_max_2600' => 'max_2600x2600',
  ];

  /** Estilo da finalidade, ou NULL se a finalidade não existe no catálogo. */
  public function styleFor(string $purpose): ?string {
    return self::PURPOSES[$purpose] ?? NULL;
  }

  /** @return string[] */
  public function purposes(): array {
    return array_keys(self::PURPOSES);
  }

}
