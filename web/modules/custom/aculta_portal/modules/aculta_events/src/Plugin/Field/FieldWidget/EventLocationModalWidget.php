<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Plugin\Field\FieldWidget;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\address\AddressInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\aculta_events\Route\EventRouteContext;

/**
 * Widget com modal AJAX e resumo para seleção de local reutilizável.
 */
#[FieldWidget(
  id: 'aculta_event_location_modal',
  label: new TranslatableMarkup('Local do evento (Modal AJAX)'),
  field_types: ['entity_reference'],
)]
final class EventLocationModalWidget extends WidgetBase {

  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

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
    $element['#attributes']['data-portal-ui'] = 'event-location-widget';
    $element['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $element['#attached']['library'][] = 'aculta_events/events-portal-ui';

    $element += $this->buildCardOrEmpty((int) $targetId, $fieldName, $wrapperId);

    return $element;
  }

  /**
   * Constrói a estrutura do card de resumo ou o estado vazio.
   */
  public function buildCardOrEmpty(int $targetId, string $fieldName, string $wrapperId): array {
    $element = [];

    $targetInputId = $wrapperId === 'field-event-location-wrapper'
      ? 'field-event-location-target-id'
      : $wrapperId . '-target-id';

    // Campo oculto com target_id para submissão regular do formulário pai.
    $element['target_id'] = [
      '#type' => 'hidden',
      '#value' => $targetId > 0 ? (string) $targetId : '',
      '#attributes' => [
        'id' => $targetInputId,
        'data-portal-ui' => 'event-location-target-id',
      ],
    ];

    $location = NULL;
    if ($targetId > 0) {
      $entity = $this->entityTypeManager->getStorage('aculta_event_location')->load($targetId);
      if ($entity instanceof EventLocation) {
        $location = $entity;
      }
    }

    if ($location !== NULL) {
      $hasAccess = $location->access('view', $this->currentUser);

      if ($hasAccess) {
        $enderecoStr = '';
        if ($location->hasField('field_endereco') && !$location->get('field_endereco')->isEmpty()) {
          $addr = $location->get('field_endereco')->first();
          if ($addr instanceof AddressInterface) {
            $cidadeUf = array_filter([$addr->getLocality(), $addr->getAdministrativeArea()]);
            $postalCode = (string) $addr->getPostalCode();
            $parts = array_filter([
              $addr->getAddressLine1(),
              $addr->getAddressLine2(),
              $addr->getDependentLocality(),
              implode(' - ', $cidadeUf),
              $postalCode !== '' ? 'CEP ' . $postalCode : '',
            ]);
            $enderecoStr = implode(', ', $parts);
          }
        }

        $isPublic = ($location->get('field_status')->value ?? 'rascunho') === 'publicado';
        $statusLabel = $isPublic ? $this->t('Publicado') : $this->t('Rascunho');

        $element['summary'] = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'event-location-summary-card'],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h4',
            '#value' => (string) $location->label(),
            '#attributes' => ['data-portal-ui' => 'event-location-title'],
          ],
          'address' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $enderecoStr !== '' ? $enderecoStr : (string) $this->t('Endereço não informado.'),
            '#attributes' => ['data-portal-ui' => 'event-location-address'],
          ],
          'badge' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => (string) $statusLabel,
            '#attributes' => ['data-portal-ui' => 'event-location-badge ' . ($isPublic ? 'event-location-badge-public' : 'event-location-badge-draft')],
          ],
        ];

        $modalUrl = Url::fromRoute(EventRouteContext::route('aculta_events.location_modal', 'aculta_events.organizer_location_modal'), [
          'field_name' => $fieldName,
          'wrapper_id' => $wrapperId,
          'target_id' => $location->id(),
        ]);

        $element['actions'] = [
          '#type' => 'actions',
          'change' => [
            '#type' => 'link',
            '#title' => $this->t('Trocar local'),
            '#url' => $modalUrl,
            '#attributes' => [
              'class' => ['use-ajax'],
              'data-portal-ui' => 'event-location-button-secondary',
              'data-dialog-type' => 'modal',
              'data-dialog-options' => json_encode(['width' => 720]),
            ],
          ],
          'remove' => [
            '#type' => 'button',
            '#value' => $this->t('Remover local'),
            '#name' => 'remove_location_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $wrapperId),
            '#limit_validation_errors' => [],
            '#ajax' => [
              'callback' => [$this, 'ajaxClearWidget'],
              'wrapper' => $wrapperId,
            ],
            '#attributes' => [
              'data-portal-ui' => 'event-location-button-danger',
            ],
          ],
        ];
      }
      else {
        $element['summary'] = [
          '#markup' => '<div class="messages messages--warning">' . $this->t('O local selecionado anteriormente não é acessível pela sua conta.') . '</div>',
        ];
        $modalUrl = Url::fromRoute(EventRouteContext::route('aculta_events.location_modal', 'aculta_events.organizer_location_modal'), [
          'field_name' => $fieldName,
          'wrapper_id' => $wrapperId,
        ]);
        $element['select'] = [
          '#type' => 'link',
          '#title' => $this->t('Selecionar outro local'),
          '#url' => $modalUrl,
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-portal-ui' => 'event-location-button-primary',
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 720]),
          ],
        ];
      }
    }
    else {
      $modalUrl = Url::fromRoute(EventRouteContext::route('aculta_events.location_modal', 'aculta_events.organizer_location_modal'), [
        'field_name' => $fieldName,
        'wrapper_id' => $wrapperId,
      ]);

      $element['empty_state'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-location-empty'],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => (string) $this->t('Nenhum local selecionado para este evento.'),
        ],
        'select_button' => [
          '#type' => 'link',
          '#title' => $this->t('Selecionar ou cadastrar local'),
          '#url' => $modalUrl,
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-portal-ui' => 'event-location-button-primary',
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 720]),
          ],
        ],
        'fallback' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['data-portal-ui' => 'event-location-fallback'],
          '#value' => '<a href="' . Url::fromRoute(EventRouteContext::route('aculta_events.location_add', 'aculta_events.organizer_location_add'))->toString() . '" target="_blank" rel="noopener">' . $this->t('Adicionar local em uma nova aba (sem JS)') . '</a>',
        ],
      ];
    }

    return $element;
  }

  /**
   * AJAX callback para limpar a seleção do local no widget pai.
   */
  public function ajaxClearWidget(array &$form, FormStateInterface $form_state): array {
    $triggering = $form_state->getTriggeringElement();
    $parents = array_slice($triggering['#array_parents'], 0, -2);
    $widget = NestedArray::getValue($form, $parents);

    $fieldName = $parents[count($parents) - 1] ?? 'field_event_location';
    $wrapperId = $widget['#attributes']['id'] ?? ('field-' . str_replace('_', '-', $fieldName) . '-wrapper');

    $widget['target_id']['#value'] = '';
    unset($widget['summary'], $widget['actions']);

    $widget += $this->buildCardOrEmpty(0, (string) $fieldName, (string) $wrapperId);
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'aculta_event_location';
  }

}
