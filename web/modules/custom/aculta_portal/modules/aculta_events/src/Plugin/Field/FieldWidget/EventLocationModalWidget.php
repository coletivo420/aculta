<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Plugin\Field\FieldWidget;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\address\AddressInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\aculta_events\Route\EventRouteContext;
use Drupal\aculta_events\Controller\EventLocationWidgetController;

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

  /**
   * Constructs this service or plugin.
   *
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array<mixed> $settings
   *   Widget settings.
   * @param array<mixed> $third_party_settings
   *   Third-party widget settings.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param AccountProxyInterface $currentUser
   *   Current user account.
   * @param EventRouteContext $eventRouteContext
   *   Event route context service.
   */
  public function __construct(
    string $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'aculta_events.route_context')]
    protected EventRouteContext $eventRouteContext,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   Field item list.
   * @param mixed $delta
   *   Field item delta.
   * @param array<array-key, mixed> $element
   *   Field element.
   * @param array<array-key, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   *
   * @return array<array-key, mixed>
   *   Render array returned by the method.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $fieldName = $this->fieldDefinition->getName();
    $item = $items->get($delta);
    $itemValues = $item?->getValue() ?? [];
    $targetId = is_array($itemValues) ? ($itemValues['target_id'] ?? NULL) : NULL;

    // Se houve submissão ou rebuild do form, prioriza o valor do form_state.
    $formValues = $form_state->getValue($fieldName);
    $submittedItem = is_array($formValues) ? ((is_int($delta) || is_string($delta) ? ($formValues[$delta] ?? NULL) : NULL)) : NULL;
    $submittedId = is_array($submittedItem) ? ($submittedItem['target_id'] ?? NULL) : NULL;
    if (is_int($submittedId) || (is_string($submittedId) && ctype_digit($submittedId))) {
      $targetId = (int) $submittedId;
    }
    $targetId = is_int($targetId) || (is_string($targetId) && ctype_digit($targetId)) ? (int) $targetId : 0;

    $wrapperId = 'field-event-location-wrapper';

    $element['#type'] = 'container';
    $attributes = $element['#attributes'] ?? [];
    if (!is_array($attributes)) {
      $attributes = [];
    }
    $attributes['id'] = $wrapperId;
    $attributes['data-portal-ui'] = 'event-location-widget';
    $element['#attributes'] = $attributes;

    $attached = $element['#attached'] ?? [];
    if (!is_array($attached)) {
      $attached = [];
    }
    $libraries = $attached['library'] ?? [];
    if (!is_array($libraries)) {
      $libraries = [];
    }
    $libraries[] = 'core/drupal.dialog.ajax';
    $libraries[] = 'aculta_events/events-portal-ui';
    $attached['library'] = $libraries;
    $element['#attached'] = $attached;

    $element += self::buildCardOrEmpty($targetId, $fieldName, $wrapperId, $this->entityTypeManager, $this->currentUser, $this->eventRouteContext, $this->getStringTranslation());

    return $element;
  }

  /**
   * Constrói a estrutura do card de resumo ou o estado vazio.
   *
   * @param int $targetId
   *   Value used for $targetId.
   * @param string $fieldName
   *   Value used for $fieldName.
   * @param string $wrapperId
   *   Value used for $wrapperId.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param AccountInterface $currentUser
   *   Current user account.
   * @param EventRouteContext $eventRouteContext
   *   Event route context service.
   * @param TranslationInterface $translation
   *   Value used for $translation.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public static function buildCardOrEmpty(int $targetId, string $fieldName, string $wrapperId, EntityTypeManagerInterface $entityTypeManager, AccountInterface $currentUser, EventRouteContext $eventRouteContext, TranslationInterface $translation): array {
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
      $entity = $entityTypeManager->getStorage('aculta_event_location')->load($targetId);
      if ($entity instanceof EventLocation) {
        $location = $entity;
      }
    }

    if ($location !== NULL) {
      $hasAccess = $location->access('view', $currentUser);

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
        $statusLabel = $isPublic ? $translation->translate('Publicado') : $translation->translate('Rascunho');

        $element['summary'] = [
          '#theme' => 'portal_component',
          '#name' => 'surface-panel',
          '#props' => ['tone' => 'raised', 'radius' => 'md', 'spacing' => 'compact', 'elevation' => 'none'],
          '#attributes' => ['data-portal-ui' => 'location-summary-card'],
          '#slots' => ['content' => [
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => (string) $location->label(),
              '#attributes' => ['data-portal-ui' => 'event-location-title'],
            ],
            'address' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#value' => $enderecoStr !== '' ? $enderecoStr : (string) $translation->translate('Endereço não informado.'),
              '#attributes' => ['data-portal-ui' => 'event-location-address'],
            ],
            'badge' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => (string) $statusLabel,
              '#attributes' => ['data-portal-ui' => 'event-location-badge ' . ($isPublic ? 'event-location-badge-public' : 'event-location-badge-draft')],
            ],
          ]],
        ];

        $modalUrl = Url::fromRoute($eventRouteContext->route('aculta_events.location_modal', 'aculta_events.organizer_location_modal'), [
          'field_name' => $fieldName,
          'wrapper_id' => $wrapperId,
          'target_id' => $location->id(),
        ]);

        $element['actions'] = [
          '#type' => 'actions',
          'change' => [
            '#type' => 'link',
            '#title' => $translation->translate('Trocar local'),
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
            '#value' => $translation->translate('Remover local'),
            '#name' => 'remove_location_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $wrapperId),
            '#limit_validation_errors' => [],
            '#ajax' => [
              'callback' => EventLocationWidgetController::class . ':clearLocationFormWidget',
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
          '#theme' => 'portal_component',
          '#name' => 'status-messages',
          '#props' => [
            'type' => 'warning',
            'messages' => [$translation->translate('O local selecionado anteriormente não é acessível pela sua conta.')],
          ],
        ];
        $modalUrl = Url::fromRoute($eventRouteContext->route('aculta_events.location_modal', 'aculta_events.organizer_location_modal'), [
          'field_name' => $fieldName,
          'wrapper_id' => $wrapperId,
        ]);
        $element['select'] = [
          '#type' => 'link',
          '#title' => $translation->translate('Selecionar outro local'),
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
      $modalUrl = Url::fromRoute($eventRouteContext->route('aculta_events.location_modal', 'aculta_events.organizer_location_modal'), [
        'field_name' => $fieldName,
        'wrapper_id' => $wrapperId,
      ]);

      $element['empty_state'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-location-empty'],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => (string) $translation->translate('Nenhum local selecionado para este evento.'),
        ],
        'select_button' => [
          '#type' => 'link',
          '#title' => $translation->translate('Selecionar ou cadastrar local'),
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
          '#value' => '<a href="' . Url::fromRoute($eventRouteContext->route('aculta_events.location_add', 'aculta_events.organizer_location_add'))->toString() . '" target="_blank" rel="noopener">' . $translation->translate('Adicionar local em uma nova aba (sem JS)') . '</a>',
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'aculta_event_location';
  }

}
