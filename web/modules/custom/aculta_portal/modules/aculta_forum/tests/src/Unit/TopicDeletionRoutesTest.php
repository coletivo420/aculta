<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Prevents regression to state-changing confirmation URLs.
 */
#[Group('aculta_forum')]
final class TopicDeletionRoutesTest extends UnitTestCase {

  /**
   * Both decisions must go through Form API confirmation, not GET controllers.
   */
  public function testDeletionDecisionsRenderForms(): void {
    $path = dirname(__DIR__, 3) . '/aculta_forum.routing.yml';
    $routes = Yaml::parseFile($path);
    self::assertIsArray($routes);

    foreach (['aculta_forum.topic_deletion_approve', 'aculta_forum.topic_deletion_reject'] as $name) {
      self::assertArrayHasKey($name, $routes);
      $defaults = $routes[$name]['defaults'];
      self::assertSame('\\Drupal\\aculta_forum\\Form\\TopicDeletionDecisionForm', $defaults['_form']);
      self::assertArrayNotHasKey('_controller', $defaults);
      self::assertContains('POST', $routes[$name]['methods']);
    }
  }

}
