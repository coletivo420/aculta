<?php

declare(strict_types=1);

namespace Drupal\aculta_activity\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/** Form validation callbacks owned by the Activity module. */
final class ActivityFormCallbacks {

  public function __construct(
    private readonly TranslationInterface $translation,
  ) {}

  /** Validates the conditional Activity fields. */
  public function validateActivity(array &$form, FormStateInterface $formState): void {
    $node = $formState->getFormObject()->buildEntity($form, $formState);
    $mode = $node->get('field_modality')->value;

    if (in_array($mode, ['presencial', 'hibrido'], TRUE)
      && $node->get('field_place_name')->isEmpty()) {
      $formState->setErrorByName(
        'field_place_name',
        $this->translation->translate('Informe o local da atividade presencial.'),
      );
    }

    if (in_array($mode, ['online', 'hibrido'], TRUE)
      && $node->get('field_online_url')->isEmpty()) {
      $formState->setErrorByName(
        'field_online_url',
        $this->translation->translate('Informe o endereço online da atividade.'),
      );
    }

    if (!$node->get('field_event_end')->isEmpty()
      && $node->get('field_event_end')->value < $node->get('field_event_start')->value) {
      $formState->setErrorByName(
        'field_event_end',
        $this->translation->translate('O término deve ocorrer após o início.'),
      );
    }
  }

}
