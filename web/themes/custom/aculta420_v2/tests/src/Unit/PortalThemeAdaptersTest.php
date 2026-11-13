<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Protects visual adapters from taking ownership of Portal behavior.
 */
final class PortalThemeAdaptersTest extends UnitTestCase {

  /**
   * Payment notice uses the shared surface and preserves its link contract.
   */
  public function testPaymentNoticeUsesSurfaceSdc(): void {
    $themeDir = dirname(__DIR__, 3);
    $template = (string) file_get_contents($themeDir . '/templates/portal-payment-notice.html.twig');

    self::assertStringContainsString("include('aculta420_v2:surface'", $template);
    self::assertStringContainsString("tone: 'raised'", $template);
    self::assertStringContainsString('data-portal-payment-notice', $template);
    self::assertStringContainsString("target: '_blank'", $template);
    self::assertStringContainsString("rel: 'noopener'", $template);
    self::assertStringNotContainsString('aculta-panel--default', $template);
  }

}
