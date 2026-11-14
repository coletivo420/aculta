<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Route;

use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;

/**
 * Resolves Events-owned route names for the active Domain purpose.
 */
final class EventRouteContext {

  public function __construct(
    private readonly ?DomainSiteManagerInterface $domainSiteManager = NULL,
  ) {}

  /**
   * Returns the organizer route for Events, otherwise the MAIN route.
   */
  public function route(string $mainRoute, string $organizerRoute): string {
    if ($this->domainSiteManager?->getCurrentSiteKey()?->value === 'eventos') {
      return $organizerRoute;
    }
    return $mainRoute;
  }

}
