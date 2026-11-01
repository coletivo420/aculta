<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Upload;

use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Monta a casca do diálogo de upload e os controles de arquivo.
 *
 * Os controles usam o elemento aculta_upload_dropzone do aculta_file_uploader,
 * com a política por integração do mesmo módulo.
 */
final class UserUploadDialogBuilder {

  public function __construct(
    private readonly ConsumerUploadPolicy $policy,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Aplica o invólucro comum do diálogo e as bibliotecas AJAX do editor.
   */
  public function wrap(array &$form, string $dialog_id, string $consumer, ?string $context_token = NULL): void {
    $ui_tokens = ['user-upload-dialog'];
    if ($context_token !== NULL && preg_match('/^[a-z][a-z0-9-]*$/', $context_token)) {
      $ui_tokens[] = $context_token;
    }
    $form['#parents'] = [];
    $form['#prefix'] = '<div id="' . htmlspecialchars($dialog_id, ENT_QUOTES, 'UTF-8') . '" data-portal-ui="' . implode(' ', $ui_tokens) . '" data-upload-consumer="' . htmlspecialchars($consumer, ENT_QUOTES, 'UTF-8') . '">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
  }

  /**
   * Adiciona os controles de imagem e, se pedido, de documento da integração.
   */
  public function addFileControls(
    array &$form,
    string $consumer,
    string $directory,
    string $image_key,
    ?string $document_key = NULL,
    bool $multiple_images = FALSE,
  ): void {
    $image_extensions = implode(' ', $this->policy->imageExtensions($consumer));
    if ($image_extensions !== '') {
      $form[$image_key] = [
        '#type' => 'aculta_upload_dropzone',
        '#title' => $this->translation->translate('Imagens'),
        '#description' => $this->translation->translate('Extensões: @extensions. Máximo por arquivo: @size.', [
          '@extensions' => $image_extensions,
          '@size' => ByteSizeMarkup::create($this->policy->maxBytes($consumer)),
        ]),
        '#multiple' => $multiple_images,
        '#upload_location' => $directory,
        '#upload_validators' => $this->policy->imageValidators($consumer),
      ];
    }

    $document_extensions = implode(' ', $this->policy->documentExtensions($consumer));
    if ($document_key !== NULL && $document_extensions !== '') {
      $form[$document_key] = [
        '#type' => 'aculta_upload_dropzone',
        '#title' => $this->translation->translate('PDF ou documento Office'),
        '#description' => $this->translation->translate('Extensões: @extensions. Máximo por arquivo: @size.', [
          '@extensions' => $document_extensions,
          '@size' => ByteSizeMarkup::create($this->policy->maxBytes($consumer)),
        ]),
        '#upload_location' => $directory,
        '#upload_validators' => $this->policy->documentValidators($consumer),
      ];
    }
  }

}
