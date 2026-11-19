<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\aculta_events\Plugin\Field\FieldWidget\EventOrganizationModalWidget;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\aculta_events\Route\EventRouteContext;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Fornece endpoints AJAX auxiliares para o widget de seleção de local.
 */
final class EventLocationWidgetController extends ControllerBase {

  /**
   * The current account used for location view access checks.
   */
  protected AccountProxyInterface $currentUserAccount;

  /**
   * The service selecting routes for the active Events purpose.
   */
  protected EventRouteContext $eventRouteContext;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountProxyInterface $currentUserAccount, #[Autowire(service: 'aculta_events.route_context')] EventRouteContext $eventRouteContext) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUserAccount = $currentUserAccount;
    $this->eventRouteContext = $eventRouteContext;
  }

  private const FIELD_NAME = 'field_event_location';

  private const WRAPPER_ID = 'field-event-location-wrapper';

  /**
   * Limpa a seleção do widget de local no formulário pai via AJAX.
   */
  public function clear(Request $request): AjaxResponse {
    [$fieldName, $wrapperId] = $this->validatedWidgetParameters($request);

    $cardArray = EventLocationModalWidget::buildCardOrEmpty(0, $fieldName, $wrapperId, $this->entityTypeManager, $this->currentUserAccount, $this->eventRouteContext, $this->getStringTranslation());
    $cardArray['#prefix'] = '<div id="' . self::WRAPPER_ID . '" class="aculta-event-location-widget">';
    $cardArray['#suffix'] = '</div>';

    $targetInputId = '#field-event-location-target-id';

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . self::WRAPPER_ID, $cardArray));
    $response->addCommand(new InvokeCommand($targetInputId, 'val', ['']));
    $response->addCommand(new InvokeCommand($targetInputId, 'trigger', ['change']));

    return $response;
  }

  /**
   * Rebuilds the empty location card from a Form API AJAX action.
   */
  public function clearLocationFormWidget(array &$form, FormStateInterface $formState): array {
    $triggering = $formState->getTriggeringElement();
    $parents = array_slice($triggering['#array_parents'], 0, -2);
    $widget = NestedArray::getValue($form, $parents);
    $fieldName = $parents[count($parents) - 1] ?? self::FIELD_NAME;
    $wrapperId = $widget['#attributes']['id'] ?? self::WRAPPER_ID;
    $widget['target_id']['#value'] = '';
    unset($widget['summary'], $widget['actions']);
    return $widget + EventLocationModalWidget::buildCardOrEmpty(
      0,
      (string) $fieldName,
      (string) $wrapperId,
      $this->entityTypeManager,
      $this->currentUserAccount,
      $this->eventRouteContext,
      $this->getStringTranslation(),
    );
  }

  /**
   * Rebuilds the empty main organization card after a Form API AJAX action.
   */
  public function clearOrganizationFormWidget(array &$form, FormStateInterface $formState): array {
    $triggering = $formState->getTriggeringElement();
    $parents = array_slice($triggering['#array_parents'], 0, -2);
    $widget = NestedArray::getValue($form, $parents);
    $fieldName = $parents[count($parents) - 1] ?? 'field_organizacao';
    $wrapperId = $widget['#attributes']['id'] ?? ('field-' . str_replace('_', '-', $fieldName) . '-wrapper');
    $widget['target_id']['#value'] = '';
    unset($widget['summary'], $widget['actions']);
    return $widget + EventOrganizationModalWidget::buildSingleCardOrEmpty(
      0,
      (string) $fieldName,
      (string) $wrapperId,
      $this->entityTypeManager,
      $this->eventRouteContext,
      $this->getStringTranslation(),
    );
  }

  /**
   * Removes one organization card after a Form API AJAX action.
   */
  public function removeOrganizationFormWidget(array &$form, FormStateInterface $formState): array {
    $triggering = $formState->getTriggeringElement();
    $removedOrgId = (int) ($triggering['#org_id'] ?? 0);
    $parents = array_slice($triggering['#array_parents'], 0, -3);
    $widget = NestedArray::getValue($form, $parents);
    $fieldName = $parents[count($parents) - 1] ?? 'field_coorganizadores';
    $wrapperId = $widget['#attributes']['id'] ?? ('field-' . str_replace('_', '-', $fieldName) . '-wrapper');
    $remainingIds = [];
    $currentValues = $formState->getValue($fieldName) ?? [];
    if (is_array($currentValues)) {
      foreach ($currentValues as $value) {
        $targetId = is_array($value) ? (int) ($value['target_id'] ?? 0) : (int) $value;
        if ($targetId > 0 && $targetId !== $removedOrgId) {
          $remainingIds[] = $targetId;
        }
      }
    }
    unset($widget['items'], $widget['actions']);
    return $widget + EventOrganizationModalWidget::buildMultipleCards(
      array_values(array_unique($remainingIds)),
      (string) $fieldName,
      (string) $wrapperId,
      $this->entityTypeManager,
      $this->eventRouteContext,
      $this->getStringTranslation(),
    );
  }

  /**
   * Accepts only identifiers emitted by the EventLocationModalWidget.
   *
   * @return array{0: string, 1: string}
   *   The validated field name and wrapper ID.
   */
  private function validatedWidgetParameters(Request $request): array {
    $fieldName = (string) $request->query->get('field_name', self::FIELD_NAME);
    $wrapperId = (string) $request->query->get('wrapper_id', self::WRAPPER_ID);
    if ($fieldName !== self::FIELD_NAME || $wrapperId !== self::WRAPPER_ID) {
      throw new BadRequestHttpException('Identificadores inválidos para o widget de local.');
    }
    return [$fieldName, $wrapperId];
  }

}
