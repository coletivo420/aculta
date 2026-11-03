<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Fornece endpoints AJAX auxiliares para o widget de seleção de local.
 */
final class EventLocationWidgetController extends ControllerBase {

  private const FIELD_NAME = 'field_event_location';

  private const WRAPPER_ID = 'field-event-location-wrapper';

  /**
   * Limpa a seleção do widget de local no formulário pai via AJAX.
   */
  public function clear(Request $request): AjaxResponse {
    [$fieldName, $wrapperId] = $this->validatedWidgetParameters($request);

    $cardArray = EventLocationModalWidget::buildCardOrEmpty(0, $fieldName, $wrapperId);
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
   * Accepts only the identifiers emitted by EventLocationModalWidget.
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
