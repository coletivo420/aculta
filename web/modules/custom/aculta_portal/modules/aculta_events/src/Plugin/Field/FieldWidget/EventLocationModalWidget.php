<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Plugin\Field\FieldWidget;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Widget com modal AJAX e resumo para seleção de local reutilizável.
 */
#[FieldWidget(
  id: 'aculta_event_location_modal',
  label: new TranslatableMarkup('Local do evento (Modal AJAX)'),
  field_types: ['entity_reference'],
)]
final class EventLocationModalWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $fieldName = $this->fieldDefinition->getName();
    $targetId = $items[$delta]->target_id ?? NULL;

    // Se houve submissão ou rebuild do form, prioriza o valor do form_state.
    $formValues = $form_state->getValue($fieldName);
    if (is_array($formValues) && isset($formValues[$delta]['target_id']) && $formValues[$delta]['target_id'] !== '') {
      $targetId = (int) $formValues[$delta]['target_id'];
    }

    $wrapperId = 'field-event-location-wrapper';

    $element['#type'] = 'container';
    $element['#attributes']['id'] = $wrapperId;
    $element['#attributes']['class'][] = 'aculta-event-location-widget';
    $element['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $element += self::buildCardOrEmpty((int) $targetId, $fieldName, $wrapperId);

    return $element;
  }

  /**
   * Constrói a estrutura do card de resumo ou o estado vazio.
   */
  public static function buildCardOrEmpty(int $targetId, string $fieldName, string $wrapperId): array {
    $element = [];

    // Campo oculto que guarda o target_id para a submissão regular do formulário pai.
    $element['target_id'] = [
      '#type' => 'hidden',
      '#value' => $targetId > 0 ? (string) $targetId : '',
      '#attributes' => [
        'id' => 'field-event-location-target-id',
      ],
    ];

    $location = NULL;
    if ($targetId > 0) {
      $entity = \Drupal::entityTypeManager()->getStorage('aculta_event_location')->load($targetId);
      if ($entity instanceof EventLocation) {
        $location = $entity;
      }
    }

    if ($location !== NULL) {
      $currentUser = \Drupal::currentUser();
      $hasAccess = $location->access('view', $currentUser);

      if ($hasAccess) {
        $enderecoStr = '';
        if ($location->hasField('field_endereco') && !$location->get('field_endereco')->isEmpty()) {
          $addr = $location->get('field_endereco')->first();
          if ($addr) {
            $parts = array_filter([
              $addr->address_line1,
              $addr->dependent_locality,
              $addr->locality,
              $addr->administrative_area,
              $addr->postal_code,
            ]);
            $enderecoStr = implode(', ', $parts);
          }
        }

        $isPublic = ($location->get('field_status')->value ?? 'rascunho') === 'publicado';
        $statusLabel = $isPublic ? t('Publicado') : t('Rascunho');

        $element['summary'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['aculta-location-summary-card', 'card', 'p-3', 'my-2']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h4',
            '#value' => (string) $location->label(),
            '#attributes' => ['class' => ['aculta-location-title', 'h5', 'mb-1']],
          ],
          'address' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $enderecoStr !== '' ? $enderecoStr : (string) t('Endereço não informado.'),
            '#attributes' => ['class' => ['aculta-location-address', 'text-muted', 'mb-2']],
          ],
          'badge' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => (string) $statusLabel,
            '#attributes' => ['class' => ['aculta-location-badge', 'badge', $isPublic ? 'badge--success' : 'badge--warning']],
          ],
        ];

        $modalUrl = Url::fromRoute('aculta_events.location_modal', [
          'field_name' => $fieldName,
          'target_id' => $location->id(),
        ]);

        $element['actions'] = [
          '#type' => 'actions',
          'change' => [
            '#type' => 'link',
            '#title' => t('Trocar local'),
            '#url' => $modalUrl,
            '#attributes' => [
              'class' => ['button', 'button--secondary', 'use-ajax'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 720]),
            ],
          ],
          'remove' => [
            '#type' => 'button',
            '#value' => t('Remover local'),
            '#name' => 'remove_location_button',
            '#ajax' => [
              'callback' => [self::class, 'ajaxClearWidget'],
              'wrapper' => $wrapperId,
            ],
            '#attributes' => [
              'class' => ['button', 'button--danger'],
            ],
          ],
        ];
      }
      else {
        $element['summary'] = [
          '#markup' => '<div class="messages messages--warning">' . t('O local selecionado anteriormente não é acessível pela sua conta.') . '</div>',
        ];
        $modalUrl = Url::fromRoute('aculta_events.location_modal', [
          'field_name' => $fieldName,
        ]);
        $element['select'] = [
          '#type' => 'link',
          '#title' => t('Selecionar outro local'),
          '#url' => $modalUrl,
          '#attributes' => [
            'class' => ['button', 'button--primary', 'use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 720]),
          ],
        ];
      }
    }
    else {
      $modalUrl = Url::fromRoute('aculta_events.location_modal', [
        'field_name' => $fieldName,
      ]);

      $element['empty_state'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-location-empty', 'my-2']],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => (string) t('Nenhum local selecionado para esta atividade.'),
        ],
        'select_button' => [
          '#type' => 'link',
          '#title' => t('Selecionar ou cadastrar local'),
          '#url' => $modalUrl,
          '#attributes' => [
            'class' => ['button', 'button--primary', 'use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 720]),
          ],
        ],
        'fallback' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['description', 'mt-1']],
          '#value' => '<a href="/locais/adicionar" target="_blank" rel="noopener">' . t('Adicionar local em uma nova aba (sem JS)') . '</a>',
        ],
      ];
    }

    return $element;
  }

  /**
   * AJAX callback para limpar a seleção do local no widget pai.
   */
  public static function ajaxClearWidget(array &$form, FormStateInterface $form_state): array {
    $triggering = $form_state->getTriggeringElement();
    $parents = array_slice($triggering['#array_parents'], 0, -2);
    $widget = NestedArray::getValue($form, $parents);

    $fieldName = $parents[0] ?? 'field_event_location';
    $wrapperId = 'field-event-location-wrapper';

    $widget['target_id']['#value'] = '';
    unset($widget['summary'], $widget['actions']);

    $widget += self::buildCardOrEmpty(0, $fieldName, $wrapperId);
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'aculta_event_location';
  }

}
