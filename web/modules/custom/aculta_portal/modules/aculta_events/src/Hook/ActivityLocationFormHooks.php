<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Hook;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Hooks de integração entre node:activity e a entidade aculta_event_location.
 */
final class ActivityLocationFormHooks {

  /**
   * Altera formulários de atividade via form_alter universal.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if ($formId === 'node_activity_form' || $formId === 'node_activity_edit_form') {
      $this->alterActivityForm($form, $formState);
    }
  }

  /**
   * Altera o formulário de atividade para usar o widget de local e ocultar campos legados.
   */
  #[Hook('form_node_activity_form_alter')]
  public function formNodeActivityFormAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $this->alterActivityForm($form, $formState);
  }

  /**
   * Altera o formulário de edição de atividade.
   */
  #[Hook('form_node_activity_edit_form_alter')]
  public function formNodeActivityEditFormAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $this->alterActivityForm($form, $formState);
  }

  /**
   * Lógica comum de alteração do formulário de node:activity.
   */
  private function alterActivityForm(array &$form, FormStateInterface $formState): void {
    // 1. Oculta incondicionalmente os campos soltos de endereço da UI do nó activity.
    foreach ([
      'field_place_name',
      'field_event_street',
      'field_event_postal',
      'field_event_region',
      'field_event_city',
      'field_event_country',
    ] as $fieldName) {
      if (isset($form[$fieldName])) {
        $form[$fieldName]['#access'] = FALSE;
      }
    }

    // 2. Garante que field_event_location esteja presente com o widget modal.
    $formObject = $formState->getFormObject();
    $node = ($formObject && method_exists($formObject, 'getEntity')) ? $formObject->getEntity() : NULL;

    if ($node instanceof NodeInterface && $node->hasField('field_event_location')) {
      if (!isset($form['field_event_location']) || !isset($form['field_event_location']['widget'])) {
        $items = $node->get('field_event_location');
        /** @var \Drupal\Core\Field\WidgetPluginManager $widgetManager */
        $widgetManager = \Drupal::service('plugin.manager.field.widget');
        $widget = $widgetManager->getInstance([
          'field_definition' => $items->getFieldDefinition(),
          'configuration' => [
            'type' => 'aculta_event_location_modal',
            'settings' => [],
            'third_party_settings' => [],
          ],
        ]);
        if ($widget) {
          $form['field_event_location'] = $widget->form($items, $form, $formState);
          $form['field_event_location']['#weight'] = 15;
        }
      }
    }

    // 3. Callback de validação da modalidade presencial/híbrida.
    $form['#validate'][] = [self::class, 'validateActivityLocation'];
  }

  /**
   * Validação do local na submissão de node:activity.
   */
  public static function validateActivityLocation(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    $node = ($formObject && method_exists($formObject, 'getEntity')) ? $formObject->getEntity() : NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'activity') {
      return;
    }

    $modality = $formState->getValue('field_modality');
    $mode = is_array($modality) ? (string) ($modality[0]['value'] ?? '') : (string) $modality;
    if ($mode === '' && $node->hasField('field_modality') && !$node->get('field_modality')->isEmpty()) {
      $mode = (string) $node->get('field_modality')->value;
    }

    if (in_array($mode, ['presencial', 'hibrido'], TRUE)) {
      $locationValues = $formState->getValue('field_event_location');
      $targetId = is_array($locationValues) ? (string) ($locationValues[0]['target_id'] ?? '') : '';
      if ($targetId === '' && $node->hasField('field_event_location') && !$node->get('field_event_location')->isEmpty()) {
        $targetId = (string) $node->get('field_event_location')->target_id;
      }
      $hasLegacyPlace = $node->hasField('field_place_name') && !$node->get('field_place_name')->isEmpty();

      if ($targetId === '' && !$hasLegacyPlace) {
        $formState->setErrorByName(
          'field_event_location',
          (string) t('Informe o local da atividade presencial.'),
        );
      }

      if ($targetId !== '' && is_numeric($targetId)) {
        $loc = \Drupal::entityTypeManager()->getStorage('aculta_event_location')->load((int) $targetId);
        $currentUser = \Drupal::currentUser();
        if ($loc === NULL || !$loc->access('view', $currentUser)) {
          $formState->setErrorByName(
            'field_event_location',
            (string) t('O local selecionado é inválido ou não pode ser acessado.'),
          );
        }
      }
    }
  }

  /**
   * Sincroniza campos de endereço legado a partir de field_event_location no presave.
   */
  #[Hook('node_presave')]
  public function nodePresave(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'activity') {
      return;
    }

    if ($entity->hasField('field_event_location') && !$entity->get('field_event_location')->isEmpty()) {
      $location = $entity->get('field_event_location')->entity;
      if ($location instanceof EventLocation) {
        if ($entity->hasField('field_place_name')) {
          $entity->set('field_place_name', $location->label());
        }
        if ($location->hasField('field_endereco') && !$location->get('field_endereco')->isEmpty()) {
          $addr = $location->get('field_endereco')->first();
          if ($addr) {
            if ($entity->hasField('field_event_street')) {
              $entity->set('field_event_street', (string) $addr->address_line1);
            }
            if ($entity->hasField('field_event_city')) {
              $entity->set('field_event_city', (string) $addr->locality);
            }
            if ($entity->hasField('field_event_region')) {
              $entity->set('field_event_region', (string) $addr->administrative_area);
            }
            if ($entity->hasField('field_event_postal')) {
              $entity->set('field_event_postal', (string) $addr->postal_code);
            }
            if ($entity->hasField('field_event_country')) {
              $entity->set('field_event_country', (string) ($addr->country_code ?: 'BR'));
            }
          }
        }
      }
    }
  }

  /**
   * Renderiza o local referenciado em node:activity na exibição pública.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $viewMode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'activity') {
      return;
    }

    if ($entity->hasField('field_event_location') && !$entity->get('field_event_location')->isEmpty()) {
      $location = $entity->get('field_event_location')->entity;
      if ($location instanceof EventLocation) {
        $currentUser = \Drupal::currentUser();
        if ($location->access('view', $currentUser)) {
          $build['field_event_location'] = [
            '#theme' => 'item_list',
            '#title' => t('Local do evento'),
            '#items' => [
              [
                '#type' => 'link',
                '#title' => $location->label(),
                '#url' => $location->toUrl(),
              ],
            ],
            '#weight' => $display->getComponent('field_event_location')['weight'] ?? 10,
          ];
        }
      }
    }
  }

}
