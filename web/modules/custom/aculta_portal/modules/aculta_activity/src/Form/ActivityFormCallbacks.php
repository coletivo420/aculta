<?php

declare(strict_types=1);

namespace Drupal\aculta_activity\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;

/**
 * Form validation callbacks owned by the Activity module.
 */
final class ActivityFormCallbacks {

  public function __construct(
    private readonly TranslationInterface $translation,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Validates the conditional Activity fields.
   */
  public function validateActivity(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    $node = ($formObject && method_exists($formObject, 'getEntity')) ? $formObject->getEntity() : NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'activity') {
      return;
    }
    $mode = (string) $this->fieldValue($node, $formState, 'field_modality');

    if (in_array($mode, ['presencial', 'hibrido'], TRUE)) {
      $hasLocation = ($node->hasField('field_event_location') && $this->fieldHasValue($node, $formState, 'field_event_location'))
        || ($node->hasField('field_place_name') && $this->fieldHasValue($node, $formState, 'field_place_name'));

      if (!$hasLocation) {
        $fieldName = $node->hasField('field_event_location') ? 'field_event_location' : 'field_place_name';
        $formState->setErrorByName(
          $fieldName,
          $this->translation->translate('Informe o local da atividade presencial.'),
        );
      }
    }

    if ($node->hasField('field_event_location') && $this->fieldHasValue($node, $formState, 'field_event_location')) {
      $locationId = (int) $this->fieldValue($node, $formState, 'field_event_location', 'target_id');
      if ($this->entityTypeManager->hasDefinition('aculta_event_location')) {
        $location = $this->entityTypeManager->getStorage('aculta_event_location')->load($locationId);
        if ($location === NULL || !$location->access('view', $this->currentUser)) {
          $formState->setErrorByName(
            'field_event_location',
            $this->translation->translate('O local selecionado é inválido ou não pode ser acessado.'),
          );
        }
      }
    }

    if (in_array($mode, ['online', 'hibrido'], TRUE)
      && $node->hasField('field_online_url')
      && !$this->fieldHasValue($node, $formState, 'field_online_url')) {
      $formState->setErrorByName(
        'field_online_url',
        $this->translation->translate('Informe o endereço online da atividade.'),
      );
    }

    $start = $node->hasField('field_event_start') ? $this->fieldValue($node, $formState, 'field_event_start') : '';
    $end = $node->hasField('field_event_end') ? $this->fieldValue($node, $formState, 'field_event_end') : '';
    if ($start !== '' && $end !== '' && $end < $start) {
      $formState->setErrorByName(
        'field_event_end',
        $this->translation->translate('O término deve ocorrer após o início.'),
      );
    }
  }

  /**
   * Returns the submitted field property, falling back to the entity item.
   */
  private function fieldValue(NodeInterface $node, FormStateInterface $formState, string $fieldName, string $property = 'value'): mixed {
    $values = $formState->getValues();
    if (array_key_exists($fieldName, $values)) {
      $submitted = $values[$fieldName];
      if (is_array($submitted)) {
        $item = reset($submitted);
        if (is_array($item)) {
          return $item[$property] ?? '';
        }
        return $submitted[$property] ?? '';
      }
      return $submitted;
    }

    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return '';
    }
    return $node->get($fieldName)->first()?->get($property) ?? '';
  }

  /**
   * Checks a submitted value when present, otherwise the current entity item.
   */
  private function fieldHasValue(NodeInterface $node, FormStateInterface $formState, string $fieldName): bool {
    $values = $formState->getValues();
    if (array_key_exists($fieldName, $values)) {
      return $this->fieldValue($node, $formState, $fieldName, 'target_id') !== ''
        || $this->fieldValue($node, $formState, $fieldName, 'uri') !== ''
        || $this->fieldValue($node, $formState, $fieldName) !== '';
    }
    return $node->hasField($fieldName) && !$node->get($fieldName)->isEmpty();
  }

}
