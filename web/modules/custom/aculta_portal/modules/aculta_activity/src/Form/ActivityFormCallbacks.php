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
    private readonly ?EntityTypeManagerInterface $entityTypeManager = NULL,
    private readonly ?AccountInterface $currentUser = NULL,
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
    $mode = (string) ($node->get('field_modality')->value ?? '');

    if (in_array($mode, ['presencial', 'hibrido'], TRUE)) {
      $hasLocation = ($node->hasField('field_event_location') && !$node->get('field_event_location')->isEmpty())
        || ($node->hasField('field_place_name') && !$node->get('field_place_name')->isEmpty());

      if (!$hasLocation) {
        $fieldName = $node->hasField('field_event_location') ? 'field_event_location' : 'field_place_name';
        $formState->setErrorByName(
          $fieldName,
          $this->translation->translate('Informe o local da atividade presencial.'),
        );
      }
    }

    if ($node->hasField('field_event_location') && !$node->get('field_event_location')->isEmpty()) {
      $locationId = (int) $node->get('field_event_location')->target_id;
      $entityTypeManager = $this->entityTypeManager ?? (\Drupal::hasService('entity_type.manager') ? \Drupal::entityTypeManager() : NULL);
      $currentUser = $this->currentUser ?? (\Drupal::hasService('current_user') ? \Drupal::currentUser() : NULL);

      if ($entityTypeManager !== NULL && $entityTypeManager->hasDefinition('aculta_event_location')) {
        $location = $entityTypeManager->getStorage('aculta_event_location')->load($locationId);
        if ($location === NULL || ($currentUser !== NULL && !$location->access('view', $currentUser))) {
          $formState->setErrorByName(
            'field_event_location',
            $this->translation->translate('O local selecionado é inválido ou não pode ser acessado.'),
          );
        }
      }
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
