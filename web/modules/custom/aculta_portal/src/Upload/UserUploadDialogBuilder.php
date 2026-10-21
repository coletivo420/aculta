<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Upload;

use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;

/** Builds the shared upload dialog shell and Managed File controls. */
final class UserUploadDialogBuilder {

  public function __construct(
    private readonly UserUploadPolicy $policy,
    private readonly TranslationInterface $translation,
  ) {}

  /** Applies the common dialog wrapper and Drupal editor AJAX libraries. */
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

  /** Adds the shared image/document controls using a consumer policy. */
  public function addFileControls(
    array &$form,
    string $consumer,
    string $directory,
    string $image_key,
    ?string $document_key = NULL,
    bool $multiple_images = FALSE,
  ): void {
    $image_extensions = $this->policy->imageExtensions($consumer);
    if ($image_extensions !== '') {
      $form[$image_key] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Imagens'),
        '#description' => $this->translation->translate('Extensões: @extensions. Máximo por arquivo: @size.', [
          '@extensions' => $image_extensions,
          '@size' => ByteSizeMarkup::create($this->policy->maxBytes($consumer)),
        ]),
        '#multiple' => $multiple_images,
        '#upload_location' => $directory,
        '#upload_validators' => $this->policy->imageValidators($consumer),
      ];
    }

    $document_extensions = $this->policy->documentExtensions($consumer);
    if ($document_key !== NULL && $document_extensions !== '') {
      $form[$document_key] = [
        '#type' => 'managed_file',
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
