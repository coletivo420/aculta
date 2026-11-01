<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Hides the legacy standalone Comment canonical route (decision D-65).
 */
final class CommentCanonicalRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('entity.comment.canonical');
    if ($route === NULL) {
      return;
    }

    // Keep the route name available for safe URL generation by old consumers,
    // but make direct requests unconditionally return 404 before rendering.
    $route->setRequirements([
      '_custom_access' => 'aculta_forum.comment_permalink_access::deny',
    ]);
  }

}
