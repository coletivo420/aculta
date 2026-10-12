<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Textfield;

/**
 * CPF text input with a service-resolved validation callback.
 */
#[FormElement('aculta_cpf')]
class Cpf extends Textfield {

  public function getInfo() {
    $info = parent::getInfo();
    $info['#maxlength'] = 64;
    $info['#aculta_document_type'] = 'cpf';
    $info['#element_validate'][] = 'aculta_id_cpf.element_validation:validate';
    $info['#attributes'] = [
      'data-aculta-document' => 'cpf',
      'inputmode' => 'numeric',
      'autocomplete' => 'off',
      'spellcheck' => 'false',
    ];
    $info['#attached']['library'][] = 'aculta_id_cpf/document_mask';
    return $info;
  }

  /**
   * Preserves input for strict validation instead of removing CR/LF like Core.
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE || $input === NULL) {
      return NULL;
    }
    if (!is_string($input)) {
      $element['#aculta_invalid_input'] = TRUE;
      return '';
    }
    return $input;
  }

}
