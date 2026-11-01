<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Attribute\CKEditor5AspectsOfCKEditor5Plugin;
use Drupal\ckeditor5\Attribute\CKEditor5Plugin;
use Drupal\ckeditor5\Attribute\DrupalAspectsOfCKEditor5Plugin;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;

/**
 * Adds the contextual Forum attachment upload modal to CKEditor 5.
 */
#[CKEditor5Plugin(
  id: 'aculta_forum_attachment',
  ckeditor5: new CKEditor5AspectsOfCKEditor5Plugin(
    plugins: ['acultaForumAttachment.AcultaForumAttachmentUI'],
  ),
  drupal: new DrupalAspectsOfCKEditor5Plugin(
    label: 'Adicionar anexo à discussão',
    library: 'aculta_forum/ckeditor5.attachment',
    elements: FALSE,
    toolbar_items: [
      'acultaForumAttachment' => ['label' => 'Adicionar anexo à discussão'],
      'acultaForumGallery' => ['label' => 'Criar galeria de imagens'],
    ],
    conditions: ['filter' => 'entity_embed'],
  ),
)]
final class ForumAttachment extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['acultaForumAttachment'] = [
      'dialogUrl' => Url::fromRoute('aculta_forum.attachment_dialog', [
        'filter_format' => $editor->getFilterFormat()->id(),
      ])->toString(),
      'label' => 'Adicionar anexo à discussão',
      'galleryDialogUrl' => Url::fromRoute('aculta_forum.gallery_dialog', [
        'filter_format' => $editor->getFilterFormat()->id(),
      ])->toString(),
      'galleryLabel' => 'Criar galeria de imagens',
    ];
    return $static_plugin_config;
  }

}
