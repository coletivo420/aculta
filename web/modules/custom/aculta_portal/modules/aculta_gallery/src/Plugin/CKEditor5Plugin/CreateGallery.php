<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Attribute\CKEditor5Plugin;
use Drupal\ckeditor5\Attribute\DrupalAspectsOfCKEditor5Plugin;
use Drupal\ckeditor5\Attribute\CKEditor5AspectsOfCKEditor5Plugin;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;

/**
 * Botão "Criar galeria" do CKEditor 5 (formato da Revista).
 *
 * Abre o formulário de galeria num diálogo do Core, cria a entidade media_gallery e insere a
 * incorporação pelo comando insertEntityEmbed do Entity Embed. Não guarda lista de imagens no texto.
 * Ver docs/ARQUITETURA.md, §4.3.
 */
#[CKEditor5Plugin(
  id: 'aculta_gallery_create',
  ckeditor5: new CKEditor5AspectsOfCKEditor5Plugin(
    plugins: ['acultaGallery.AcultaGalleryCreateUI'],
  ),
  drupal: new DrupalAspectsOfCKEditor5Plugin(
    label: 'Criar galeria',
    library: 'aculta_gallery/ckeditor5.create',
    elements: FALSE,
    toolbar_items: [
      'acultaGalleryCreate' => ['label' => 'Criar galeria'],
    ],
    conditions: ['filter' => 'entity_embed'],
  ),
)]
final class CreateGallery extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $url = Url::fromRoute('aculta_gallery.ckeditor_dialog', ['filter_format' => $editor->getFilterFormatId()])->toString();
    $static_plugin_config['acultaGalleryCreate'] = [
      'dialogUrl' => $url,
      'label' => 'Criar galeria',
      'dialogSettings' => [
        'dialogClass' => 'aculta-gallery-dialog',
        'height' => 'auto',
        'width' => 'auto',
      ],
    ];
    return $static_plugin_config;
  }

}
