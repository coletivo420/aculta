<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects the navigation method contract of notice-open routes.
 */
#[Group('aculta_messenger')]
final class NoticeOpenRouteMethodTest extends UnitTestCase {

  /**
   * Notice links may open through GET but cannot invoke the route with writes.
   */
  public function testIntegratedNoticeOpenRoutesAreGetOnly(): void {
    $routeFile = dirname(__DIR__, 3) . '/aculta_messenger.routing.yml';
    $definitions = Yaml::decode(file_get_contents($routeFile));
    $routes = [
      'aculta_messenger.lms_notice_open',
      'aculta_messenger.wiki_notice_open',
      'aculta_messenger.commerce_notice_open',
      'aculta_messenger.donation_notice_open',
    ];

    foreach ($routes as $routeName) {
      self::assertArrayHasKey($routeName, $definitions);
      self::assertSame(['GET'], $definitions[$routeName]['methods'], $routeName);
    }
  }

}
