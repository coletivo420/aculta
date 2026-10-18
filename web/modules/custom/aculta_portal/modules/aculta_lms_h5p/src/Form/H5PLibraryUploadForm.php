<?php

// ACULTA fork of Drupal H5P 2.0.0-beta1; upload path disabled pending image ingestion.

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Explains why direct third-party H5P package uploads are currently disabled.
 */
final class H5PLibraryUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_h5p_library_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Library package upload unavailable'),
      '#description' => $this->t('Upload will be enabled after embedded images are ingested and tracked by the ACULTA image system.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
