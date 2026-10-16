<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

/**
 * Catálogo de finalidades de imagem do ACULTA (aculta_image, seção 3.1 do plano).
 *
 * Única fonte de nomes de estilo de imagem do site. Quem precisa de uma imagem pede uma finalidade, não um estilo.
 * Os estilos listados em STYLES são os que existem em config/sync; o gate (scripts/validate-aculta-image.php) reprova
 * estilo exportado que não esteja aqui. Nomes antigos só podem permanecer enquanto houver configuração consumidora
 * identificada e registrada no plano de migração (R8).
 */
final class ImageCatalog {

  /** Todos os estilos de imagem aceitos no projeto (espelho de config/sync/image.style.*.yml). */
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
    'avatar_pequeno' => 'aculta_avatar_pequeno',
    'card_16x9' => 'aculta_card_16x9',
    'destaque' => 'aculta_destaque_16x9',
    'compartilhamento' => 'aculta_compartilhamento_1200x630',
  ];

  /**
   * Modelos de imagem do texto (padrão de jornais digitais, ver docs/MODELOS-DE-IMAGEM.md): nome => estilos do srcset.
   * Primeiro estilo: 1x (tela comum). Segundo: 2x (tela retina) ou o maior tamanho disponível para o modelo.
   */
  public const MODELOS = [
    'pequena' => ['aculta_corpo_320', 'aculta_corpo_640'],
    'padrao' => ['aculta_corpo_480', 'aculta_corpo_1160'],
    'larga' => ['aculta_corpo_800', 'aculta_corpo_1200'],
    'destaque' => ['aculta_corpo_1024', 'aculta_corpo_1200'],
  ];

  /** Estilos do srcset de um modelo de imagem do texto, ou NULL se o modelo não existe. */
  public function estilosDoModelo(string $modelo): ?array {
    return self::MODELOS[$modelo] ?? NULL;
  }

  /** Estilo da finalidade, ou NULL se a finalidade não existe no catálogo. */
  public function styleFor(string $purpose): ?string {
    return self::PURPOSES[$purpose] ?? NULL;
  }

  /** @return string[] */
  public function purposes(): array {
    return array_keys(self::PURPOSES);
  }

}
