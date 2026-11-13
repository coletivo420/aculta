<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Protects visual adapters from taking ownership of Portal behavior.
 */
final class PortalThemeAdaptersTest extends UnitTestCase {

  /**
   * Payment notice delegates presentation to its SDC and preserves link safety.
   */
  public function testPaymentNoticeUsesSurfaceSdc(): void {
    $themeDir = dirname(__DIR__, 3);
    $template = (string) file_get_contents($themeDir . '/templates/portal-payment-notice.html.twig');

    self::assertStringContainsString("include('aculta420_v2:payment-notice'", $template);
    self::assertStringNotContainsString('aculta-panel--default', $template);

    $component = (string) file_get_contents($themeDir . '/components/payment-notice/payment-notice.component.yml');
    $twig = (string) file_get_contents($themeDir . '/components/payment-notice/payment-notice.twig');
    $css = (string) file_get_contents($themeDir . '/components/payment-notice/payment-notice.css');
    $surfaceCss = (string) file_get_contents($themeDir . '/components/surface/surface.css');

    self::assertStringContainsString('payment-notice.css', $component);
    self::assertStringContainsString('data-portal-payment-notice', $twig);
    self::assertStringContainsString('target="_blank"', $twig);
    self::assertStringContainsString('rel="noopener"', $twig);
    self::assertStringContainsString('.aculta-payment-notice__link', $css);
    self::assertStringNotContainsString('.aculta-payment-notice__link', $surfaceCss);
  }

}
