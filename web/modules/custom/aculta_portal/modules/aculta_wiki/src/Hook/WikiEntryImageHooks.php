<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\aculta_portal\Upload\UserUploadDialogBuilder;
use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\aculta_wiki\Upload\WikiLeadImageUploader;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Replaces the raw Media autocomplete with the Wiki lead image upload tab.
 *
 * The verbete form keeps the same routes and fields; only the widget for
 * field_wiki_image changes. The tab reuses the central upload policy (consumer
 * "wiki") through the same helper the Forum uses, so extensions, size limits
 * and the alt text rule stay in one place.
 */
final class WikiEntryImageHooks {

  use StringTranslationTrait;

  public function __construct(
    TranslationInterface $stringTranslation,
    private readonly UserUploadPolicy $uploadPolicy,
    private readonly UserUploadDialogBuilder $dialogBuilder,
    private readonly WikiLeadImageUploader $uploader,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Adds the "Imagem de destaque" tab to the wiki_entry node form.
   */
  #[Hook('form_node_wiki_entry_form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $field = WikiLeadImageUploader::FIELD;
    if (!isset($form[$field])) {
      return;
    }
    $access = $form[$field]['#access'] ?? TRUE;
    if ($access === FALSE || ($access instanceof AccessResultInterface && !$access->isAllowed())) {
      return;
    }

    // Without an enabled image extension the policy has nothing to offer; the
    // core autocomplete stays visible instead of locking the field.
    if ($this->uploadPolicy->imageExtensions(WikiLeadImageUploader::CONSUMER) === '') {
      return;
    }

    $form_object = $form_state->getFormObject();
    $node = $form_object instanceof ContentEntityFormInterface ? $form_object->getEntity() : NULL;
    $current = $node instanceof NodeInterface ? $this->currentImage($node) : NULL;

    $form[$field]['#access'] = FALSE;
    if (!isset($form['advanced'])) {
      $form['advanced'] = [
        '#type' => 'vertical_tabs',
        '#weight' => 99,
      ];
    }

    $tab = WikiLeadImageUploader::TAB;
    $form[$tab] = [
      '#type' => 'details',
      '#title' => $this->t('Imagem de destaque'),
      '#group' => 'advanced',
      '#weight' => 20,
      // Keeps the submitted values under the tab key, as the uploader reads.
      '#tree' => TRUE,
    ];
    $form[$tab]['introduction'] = [
      '#type' => 'item',
      '#markup' => $this->t('A imagem de destaque aparece no card da listagem e no topo do verbete. Tamanho e formatos seguem a política de upload da Wiki.'),
    ];
    if ($current !== NULL) {
      $form[$tab]['current'] = [
        '#type' => 'item',
        '#title' => $this->t('Imagem atual'),
        '#markup' => $current['alt'] === ''
          ? $this->t('@name (sem descrição)', ['@name' => $current['name']])
          : $this->t('@name — descrição: @alt', ['@name' => $current['name'], '@alt' => $current['alt']]),
      ];
    }

    $this->dialogBuilder->addFileControls($form[$tab], WikiLeadImageUploader::CONSUMER, WikiLeadImageUploader::DIRECTORY, WikiLeadImageUploader::FILE);
    $form[$tab][WikiLeadImageUploader::FILE]['#title'] = $this->t('Nova imagem de destaque');
    $form[$tab][WikiLeadImageUploader::ALT] = [
      '#type' => 'textfield',
      '#title' => $this->t('Descrição da imagem'),
      '#description' => $this->t('Obrigatória ao enviar uma nova imagem. Descreva o que ela mostra.'),
      '#maxlength' => WikiLeadImageUploader::ALT_MAX_LENGTH,
      '#default_value' => $current['alt'] ?? '',
    ];
    if ($current !== NULL) {
      $form[$tab][WikiLeadImageUploader::REMOVE] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Remover a imagem de destaque atual'),
      ];
    }

    $form['#validate'][] = 'aculta_wiki.wiki_lead_image_uploader:validateUpload';
    // The save button defines its own #submit list, which replaces the
    // form-level one; prepend so the Media exists before the node is saved.
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#submit'] = array_merge([
        'aculta_wiki.wiki_lead_image_uploader:applyUpload',
      ], $form['actions']['submit']['#submit'] ?? []);
    }
  }

  /**
   * Returns the label and alt text of the image currently referenced.
   */
  private function currentImage(NodeInterface $node): ?array {
    if (!$node->hasField(WikiLeadImageUploader::FIELD) || $node->get(WikiLeadImageUploader::FIELD)->isEmpty()) {
      return NULL;
    }
    $item = $node->get(WikiLeadImageUploader::FIELD)->first();
    $media = $item?->get('entity')->getValue();
    if (!$media instanceof MediaInterface) {
      return NULL;
    }
    $alt = '';
    if ($media->hasField('field_media_image')) {
      $alt = (string) ($media->get('field_media_image')->first()?->get('alt')->getValue() ?? '');
    }
    return ['name' => (string) $media->label(), 'alt' => $alt];
  }

}
