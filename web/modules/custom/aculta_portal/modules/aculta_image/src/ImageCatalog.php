<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

/**
 * Provides the canonical image styles, purposes, and text models.
 *
 * Única fonte de nomes de estilo de imagem do site. Quem precisa de uma imagem
 * pede uma finalidade, não um estilo.
 * Os estilos listados em STYLES são os que existem em config/sync; o gate
 * (scripts/validate-aculta-image.php) reprova
 * estilo exportado que não esteja aqui. Nomes antigos só podem permanecer
 * enquanto houver configuração consumidora
 * identificada e registrada no plano de migração (R8).
 */
final class ImageCatalog {

  /**
   * Lists the image style machine names accepted by the project.
   *
   * @todo Keep this list aligned with config/sync/image.style.*.yml.
   */
  public const STYLES = [
    'aculta_avatar',
    'aculta_avatar_pequeno',
    'aculta_card_1x',
    'aculta_card_2x',
    'aculta_compartilhamento_1200x630',
    'aculta_card_16x9',
    'aculta_destaque_16x9',
    'aculta_corpo_1024',
    'aculta_corpo_1160',
    'aculta_corpo_1200',
    'aculta_corpo_320',
    'aculta_corpo_480',
    'aculta_corpo_640',
    'aculta_corpo_800',
    'crop_thumbnail',
    'large',
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

  /**
   * Finalidade => estilo. Novas finalidades entram aqui antes de qualquer uso. */
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
    'avatar_pequeno' => 'aculta_avatar_pequeno',
    'card_16x9' => 'aculta_card_16x9',
    'destaque' => 'aculta_destaque_16x9',
    'compartilhamento' => 'aculta_compartilhamento_1200x630',
  ];

  /**
   * Image models for rich text images.
   *
   * The first style is the common-screen size; the second is the retina or
   * largest available size for the model.
   */
  public const MODELOS = [
    'pequena' => ['aculta_corpo_320', 'aculta_corpo_640'],
    'padrao' => ['aculta_corpo_480', 'aculta_corpo_1160'],
    'larga' => ['aculta_corpo_800', 'aculta_corpo_1200'],
    'destaque' => ['aculta_corpo_1024', 'aculta_corpo_1200'],
  ];

  /**
   * Returns srcset styles for a rich text image model.
   *
   * @param string $modelo
   *   Model machine name.
   *
   * @return array<int, string>|null
   *   Styles for the model, or NULL when it is not defined.
   */
  public function estilosDoModelo(string $modelo): ?array {
    return self::MODELOS[$modelo] ?? NULL;
  }

  /**
   * Estilo da finalidade, ou NULL se a finalidade não existe no catálogo. */
  public function styleFor(string $purpose): ?string {
    return self::PURPOSES[$purpose] ?? NULL;
  }

  /**
   * Returns the machine names of supported image purposes.
   *
   * @return string[]
   *   Purpose machine names.
   */
  public function purposes(): array {
    return array_keys(self::PURPOSES);
  }

}
