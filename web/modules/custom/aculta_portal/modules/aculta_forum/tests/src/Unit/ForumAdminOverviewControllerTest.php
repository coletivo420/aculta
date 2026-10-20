<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Controller\ForumAdminOverviewController;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the central Forum administration page and legacy path redirects.
 */
#[Group('aculta_forum')]
final class ForumAdminOverviewControllerTest extends UnitTestCase {

  /**
   * The central page does not aggregate or proxy other administrative pages.
   */
  public function testOverviewIsCanonicalLandingPageOnly(): void {
    $build = (new ForumAdminOverviewController())->overview();

    self::assertArrayNotHasKey('tools', $build);
    self::assertArrayNotHasKey('introduction', $build);
    self::assertStringContainsString('Painel central', $build['#plain_text']->getUntranslatedString());
  }

  /**
   * Legacy Forum administration URLs return locally to the central panel.
   */
  public function testLegacyAdminPathsRedirectToCentralPanel(): void {
    $response = (new ForumAdminOverviewController())->redirectToOverview();

    self::assertInstanceOf(LocalRedirectResponse::class, $response);
    self::assertSame('/painel-administrativo/forum', $response->getTargetUrl());
    self::assertSame(302, $response->getStatusCode());
  }

}
