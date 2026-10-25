<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Controller;

use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\aculta_user\Controller\ReportQueueController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the canonical redirect for the Forum report administration path.
 */
#[Group('aculta_user')]
final class ReportQueueControllerTest extends UnitTestCase {

  /**
   * The retired report queue path redirects locally to the Forum panel.
   */
  public function testReportQueuePathRedirectsToForumPanel(): void {
    $response = (new ReportQueueController())->view();

    self::assertInstanceOf(LocalRedirectResponse::class, $response);
    self::assertSame('/painel-administrativo/forum', $response->getTargetUrl());
    self::assertSame(302, $response->getStatusCode());
  }

  /**
   * The retired path keeps its access requirement and accepts form submissions.
   */
  public function testReportQueueRoutePreservesAccessAndRedirectsPost(): void {
    $routing = Yaml::parseFile(dirname(__DIR__, 4) . '/aculta_user.routing.yml');
    $route = $routing['aculta_user.denuncias_forum'];

    self::assertSame('moderar denuncias do forum', $route['requirements']['_permission']);
    self::assertSame(['GET', 'HEAD', 'POST'], $route['methods']);
  }

}
