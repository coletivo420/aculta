<?php

// ACULTA fork of Drupal H5P 2.0.0-beta1; upload path disabled pending image ingestion.

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\aculta_lms_h5p\Plugin\Field\H5PWidgetBase;

/**
 * Field widget placeholder while secure package ingestion is implemented.
 *
 * @FieldWidget(
 *   id = "h5p_upload",
 *   label = @Translation("H5P Upload"),
 *   field_types = {"h5p"}
 * )
 */
final class H5PUploadWidget extends H5PWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    return [
      'h5p_content' => [
        '#type' => 'item',
        '#title' => $this->t('Package upload unavailable'),
        '#description' => $this->t('H5P package upload will be enabled after its embedded images are integrated with the ACULTA image system.'),
      ],
    ];
  }

}
