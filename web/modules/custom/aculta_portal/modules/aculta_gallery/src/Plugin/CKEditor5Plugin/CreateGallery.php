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
 * Abre o formulário num diálogo do Core e cria uma entidade media_gallery.
 * A incorporação usa insertEntityEmbed; a lista de imagens não fica no texto.
 * Consulte a seção 4.3 de docs/ARQUITETURA.md.
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
    $url = Url::fromRoute('aculta_gallery.ckeditor_dialog', ['filter_format' => $editor->getFilterFormat()->id()])->toString();
    $static_plugin_config['acultaGalleryCreate'] = [
      'dialogUrl' => $url,
      'label' => 'Criar galeria',
    ];
    return $static_plugin_config;
  }

}
