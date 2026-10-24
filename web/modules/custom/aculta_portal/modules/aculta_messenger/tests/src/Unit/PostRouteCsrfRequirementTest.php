<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies CSRF access requirements on state-changing Messenger routes.
 */
#[CoversNothing]
#[Group('aculta_messenger')]
final class PostRouteCsrfRequirementTest extends UnitTestCase {

  /**
   * Every POST-only route requires Drupal's route CSRF token.
   */
  public function testPostOnlyRoutesRequireCsrfToken(): void {
    $route_file = dirname(__DIR__, 3) . '/aculta_messenger.routing.yml';
    $routes = Yaml::decode(file_get_contents($route_file));
    $post_routes = [];
    foreach ($routes as $name => $route) {
      if (($route['methods'] ?? []) === ['POST']) {
        $post_routes[] = $name;
        self::assertSame('TRUE', $route['requirements']['_csrf_token'] ?? NULL, $name);
      }
    }

    sort($post_routes);
    self::assertSame([
      'aculta_messenger.drawer_relationship',
      'aculta_messenger.drawer_reply',
      'aculta_messenger.member_relationship',
      'aculta_messenger.profile_relationship',
    ], $post_routes);
  }

}
