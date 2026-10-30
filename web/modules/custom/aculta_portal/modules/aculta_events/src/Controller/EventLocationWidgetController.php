<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Plugin\Field\FieldWidget\EventLocationModalWidget;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fornece endpoints AJAX auxiliares para o widget de seleção de local.
 */
final class EventLocationWidgetController extends ControllerBase {

  /**
   * Limpa a seleção do widget de local no formulário pai via AJAX.
   */
  public function clear(Request $request): AjaxResponse {
    $fieldName = (string) $request->query->get('field_name', 'field_event_location');
    $wrapperId = 'field-event-location-wrapper';

    $cardArray = EventLocationModalWidget::buildCardOrEmpty(0, $fieldName, $wrapperId);
    $cardArray['#prefix'] = '<div id="' . $wrapperId . '" class="aculta-event-location-widget">';
    $cardArray['#suffix'] = '</div>';

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapperId, $cardArray));
    $response->addCommand(new InvokeCommand('#field-event-location-target-id', 'val', ['']));
    $response->addCommand(new InvokeCommand('#field-event-location-target-id', 'trigger', ['change']));

    return $response;
  }

}
