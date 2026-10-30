<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Plugin\Field\FieldWidget;

use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Route\EventRouteContext;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Widget com modal AJAX e resumo para seleção de organizações culturais.
 *
 * Suporta campos de referência única (organização principal) e múltipla
 * (coorganizadoras) com fechamento de popup, devolução de foco e validação.
 */
#[FieldWidget(
  id: 'aculta_event_organization_modal',
  label: new TranslatableMarkup('Organização do evento (Modal AJAX)'),
  field_types: ['entity_reference'],
  multiple_values: TRUE,
)]
final class EventOrganizationModalWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state): array {
    $fieldName = $this->fieldDefinition->getName();
    $isMultiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();
    $wrapperId = 'field-' . str_replace('_', '-', $fieldName) . '-wrapper';

    $element = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapperId,
        'data-portal-ui' => 'event-organization-widget',
        'data-field-name' => $fieldName,
      ],
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];

    if (!$isMultiple) {
      // Caso 1: Cardinalidade única (ex: field_organizacao).
      $targetId = $items[0]->target_id ?? NULL;
      $formValues = $form_state->getValue($fieldName);
      if (is_array($formValues) && isset($formValues[0]['target_id']) && $formValues[0]['target_id'] !== '') {
        $targetId = (int) $formValues[0]['target_id'];
      }

      $element += self::buildSingleCardOrEmpty((int) $targetId, $fieldName, $wrapperId);
    }
    else {
      // Caso 2: Cardinalidade múltipla (ex: field_coorganizadores).
      $targetIds = [];
      foreach ($items as $item) {
        if (!empty($item->target_id)) {
          $targetIds[] = (int) $item->target_id;
        }
      }

      $formValues = $form_state->getValue($fieldName);
      if (is_array($formValues)) {
        $targetIds = [];
        foreach ($formValues as $v) {
          if (is_array($v) && !empty($v['target_id'])) {
            $targetIds[] = (int) $v['target_id'];
          }
          elseif (is_numeric($v) && (int) $v > 0) {
            $targetIds[] = (int) $v;
          }
        }
      }

      $targetIds = array_values(array_unique(array_filter($targetIds)));
      $element += self::buildMultipleCards($targetIds, $fieldName, $wrapperId);
    }

    return $element;
  }

  /**
   * Constrói o card para referência única ou estado vazio.
   */
  public static function buildSingleCardOrEmpty(int $targetId, string $fieldName, string $wrapperId): array {
    $element = [];
    $targetInputId = $wrapperId . '-target-id';

    $element['target_id'] = [
      '#type' => 'hidden',
      '#value' => $targetId > 0 ? (string) $targetId : '',
      '#name' => $fieldName . '[0][target_id]',
      '#attributes' => [
        'id' => $targetInputId,
        'data-portal-ui' => 'event-organization-target-id',
      ],
    ];

    $org = NULL;
    if ($targetId > 0) {
      $entity = \Drupal::entityTypeManager()->getStorage('aculta_event_organization')->load($targetId);
      if ($entity instanceof EventOrganization) {
        $org = $entity;
      }
    }

    if ($org !== NULL) {
      $badgeText = $org->isClaimed() ? t('Reivindicada') : t('Não reivindicada');
      $badgeClass = $org->isClaimed() ? 'badge--success' : 'badge--warning';

      $localidadeStr = '';
      if (!$org->shouldHideLocation()) {
        $locParts = array_filter([(string) $org->get('localidade')->value, (string) $org->get('uf')->value]);
        if (!empty($locParts)) {
          $localidadeStr = implode(' - ', $locParts);
        }
      }

      $element['summary'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-org-summary-card', 'class' => ['event-org-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => (string) $org->label(),
          '#attributes' => ['data-portal-ui' => 'event-org-title'],
        ],
        'badge' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => (string) $badgeText,
          '#attributes' => ['class' => ['badge', $badgeClass], 'data-portal-ui' => 'event-org-badge'],
        ],
      ];

      if ($localidadeStr !== '') {
        $element['summary']['locality'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => (string) t('Localidade: @loc', ['@loc' => $localidadeStr]),
          '#attributes' => ['data-portal-ui' => 'event-org-locality'],
        ];
      }

      $modalUrl = Url::fromRoute(EventRouteContext::route('aculta_events.organization_modal', 'aculta_events.organizer_organization_modal'), [
        'field_name' => $fieldName,
        'wrapper_id' => $wrapperId,
        'is_multiple' => 0,
        'target_id' => $org->id(),
      ]);

      $element['actions'] = [
        '#type' => 'actions',
        'change' => [
          '#type' => 'link',
          '#title' => t('Trocar organização'),
          '#url' => $modalUrl,
          '#attributes' => [
            'class' => ['use-ajax', 'button'],
            'data-portal-ui' => 'event-org-button-secondary',
            'data-portal-trigger' => $wrapperId,
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 720]),
          ],
        ],
        'remove' => [
          '#type' => 'button',
          '#value' => t('Remover organização'),
          '#name' => 'remove_org_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $wrapperId),
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => [self::class, 'ajaxClearSingleWidget'],
            'wrapper' => $wrapperId,
          ],
          '#attributes' => [
            'class' => ['button', 'button--danger'],
            'data-portal-ui' => 'event-org-button-danger',
          ],
        ],
      ];
    }
    else {
      $modalUrl = Url::fromRoute(EventRouteContext::route('aculta_events.organization_modal', 'aculta_events.organizer_organization_modal'), [
        'field_name' => $fieldName,
        'wrapper_id' => $wrapperId,
        'is_multiple' => 0,
      ]);

      $element['empty_state'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-org-empty'],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => (string) t('Nenhuma organização principal selecionada.'),
        ],
        'select_button' => [
          '#type' => 'link',
          '#title' => t('Selecionar ou cadastrar organização'),
          '#url' => $modalUrl,
          '#attributes' => [
            'class' => ['use-ajax', 'button', 'button--primary'],
            'data-portal-ui' => 'event-org-button-primary',
            'data-portal-trigger' => $wrapperId,
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 720]),
          ],
        ],
        'fallback' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-muted', 'small']],
          '#value' => '<a href="' . Url::fromRoute(EventRouteContext::route('aculta_events.admin_organization_add', 'aculta_events.organizer_organization_add'))->toString() . '" target="_blank" rel="noopener">' . t('Cadastrar organização em nova aba (sem JS)') . '</a>',
        ],
      ];
    }

    return $element;
  }

  /**
   * Constrói a lista de cards para referência múltipla (coorganizadoras).
   */
  public static function buildMultipleCards(array $targetIds, string $fieldName, string $wrapperId): array {
    $element = [];
    $element['items'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['coorganizers-list']],
    ];

    if (!empty($targetIds)) {
      $storage = \Drupal::entityTypeManager()->getStorage('aculta_event_organization');
      $orgs = $storage->loadMultiple($targetIds);

      foreach ($targetIds as $delta => $id) {
        $org = $orgs[$id] ?? NULL;
        if (!$org instanceof EventOrganization) {
          continue;
        }

        $badgeText = $org->isClaimed() ? t('Reivindicada') : t('Não reivindicada');
        $badgeClass = $org->isClaimed() ? 'badge--success' : 'badge--warning';

        $localidadeStr = '';
        if (!$org->shouldHideLocation()) {
          $locParts = array_filter([(string) $org->get('localidade')->value, (string) $org->get('uf')->value]);
          if (!empty($locParts)) {
            $localidadeStr = implode(' - ', $locParts);
          }
        }

        $rowKey = 'row_' . $id;
        $element['items'][$rowKey] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['coorganizer-item', 'clearfix'], 'data-portal-ui' => 'coorganizer-card'],
          'target_id' => [
            '#type' => 'hidden',
            '#value' => (string) $id,
            '#name' => $fieldName . '[' . $delta . '][target_id]',
          ],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'strong',
            '#value' => (string) $org->label(),
            '#attributes' => ['data-portal-ui' => 'coorganizer-title'],
          ],
          'badge' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => ' (' . (string) $badgeText . ')',
            '#attributes' => ['class' => ['badge', $badgeClass]],
          ],
          'remove' => [
            '#type' => 'button',
            '#value' => t('Remover'),
            '#name' => 'remove_coorg_' . $id . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $wrapperId),
            '#limit_validation_errors' => [],
            '#org_id' => $id,
            '#ajax' => [
              'callback' => [self::class, 'ajaxRemoveMultipleItem'],
              'wrapper' => $wrapperId,
            ],
            '#attributes' => [
              'class' => ['button', 'button--small', 'button--danger'],
            ],
          ],
        ];

        if ($localidadeStr !== '') {
          $element['items'][$rowKey]['locality'] = [
            '#type' => 'html_tag',
            '#tag' => 'small',
            '#value' => ' — ' . $localidadeStr,
            '#attributes' => ['class' => ['text-muted']],
          ];
        }
      }
    }
    else {
      $element['items']['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => (string) t('Nenhuma organização coorganizadora vinculada.'),
        '#attributes' => ['class' => ['text-muted']],
      ];
    }

    $modalUrl = Url::fromRoute(EventRouteContext::route('aculta_events.organization_modal', 'aculta_events.organizer_organization_modal'), [
      'field_name' => $fieldName,
      'wrapper_id' => $wrapperId,
      'is_multiple' => 1,
    ]);

    $element['actions'] = [
      '#type' => 'actions',
      'add_coorg' => [
        '#type' => 'link',
        '#title' => t('+ Adicionar coorganizadora'),
        '#url' => $modalUrl,
        '#attributes' => [
          'class' => ['use-ajax', 'button', 'button--secondary'],
          'data-portal-ui' => 'event-coorg-button-add',
          'data-portal-trigger' => $wrapperId,
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 720]),
        ],
      ],
    ];

    return $element;
  }

  /**
   * AJAX callback para limpar a organização principal selecionada.
   */
  public static function ajaxClearSingleWidget(array &$form, FormStateInterface $form_state): array {
    $triggering = $form_state->getTriggeringElement();
    $parents = array_slice($triggering['#array_parents'], 0, -2);
    $widget = NestedArray::getValue($form, $parents);

    $fieldName = $parents[count($parents) - 1] ?? 'field_organizacao';
    $wrapperId = $widget['#attributes']['id'] ?? ('field-' . str_replace('_', '-', $fieldName) . '-wrapper');

    $widget['target_id']['#value'] = '';
    unset($widget['summary'], $widget['actions']);

    $widget += self::buildSingleCardOrEmpty(0, (string) $fieldName, (string) $wrapperId);
    return $widget;
  }

  /**
   * AJAX callback para remover uma coorganizadora da lista múltipla.
   */
  public static function ajaxRemoveMultipleItem(array &$form, FormStateInterface $form_state): array {
    $triggering = $form_state->getTriggeringElement();
    $removedOrgId = (int) ($triggering['#org_id'] ?? 0);
    $parents = array_slice($triggering['#array_parents'], 0, -3);
    $widget = NestedArray::getValue($form, $parents);

    $fieldName = $parents[count($parents) - 1] ?? 'field_coorganizadores';
    $wrapperId = $widget['#attributes']['id'] ?? ('field-' . str_replace('_', '-', $fieldName) . '-wrapper');

    $currentValues = $form_state->getValue($fieldName) ?? [];
    $remainingIds = [];
    if (is_array($currentValues)) {
      foreach ($currentValues as $val) {
        $tid = is_array($val) ? (int) ($val['target_id'] ?? 0) : (int) $val;
        if ($tid > 0 && $tid !== $removedOrgId) {
          $remainingIds[] = $tid;
        }
      }
    }

    $remainingIds = array_values(array_unique($remainingIds));
    unset($widget['items'], $widget['actions']);
    $widget += self::buildMultipleCards($remainingIds, (string) $fieldName, (string) $wrapperId);
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'aculta_event_organization';
  }

}
