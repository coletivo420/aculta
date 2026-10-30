<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Route;

/**
 * Resolves Events-owned route names for the active Domain purpose.
 */
final class EventRouteContext {

  /**
   * Returns the organizer route on the Events purpose, otherwise the MAIN route.
   */
  public static function route(string $mainRoute, string $organizerRoute): string {
    if (\Drupal::hasService('aculta_portal.domain_purpose')
      && \Drupal::service('aculta_portal.domain_purpose')->getCurrentPurpose() === 'eventos') {
      return $organizerRoute;
    }
    return $mainRoute;
  }

}
