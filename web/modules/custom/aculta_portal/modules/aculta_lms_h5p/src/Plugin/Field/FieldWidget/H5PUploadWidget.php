<?php

// ACULTA fork of Drupal H5P 2.0.0-beta1; upload path disabled pending image ingestion.

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_lms_h5p\Plugin\Field\H5PWidgetBase;

/** Field widget placeholder for unavailable legacy content-package uploads. */
#[FieldWidget(
  id: 'h5p_upload',
  label: new TranslatableMarkup('Envio de pacote H5P indisponível'),
  field_types: ['h5p'],
)]
final class H5PUploadWidget extends H5PWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    return [
      'h5p_content' => [
        '#type' => 'item',
        '#title' => $this->t('Envio de pacote indisponível'),
        '#description' => $this->t('Para criar uma atividade, use o editor do curso. Imagens enviadas serão gerenciadas pelo sistema de imagens da ACULTA.'),
      ],
    ];
  }

}
