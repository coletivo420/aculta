<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies status-message props and the Portal cart adapter contract.
 */
final class StatusMessagesComponentTest extends UnitTestCase {

  /**
   * Dismissal remains opt-in and accessible labels are required when supplied.
   */
  public function testDismissibleStatusMessageContract(): void {
    $themeDir = dirname(__DIR__, 3);
    $meta = Yaml::parse((string) file_get_contents($themeDir . '/components/status-messages/status-messages.component.yml'));
    $props = $meta['props']['properties'];

    self::assertFalse($props['dismissible']['default']);
    self::assertEqualsCanonicalizing(['status', 'alert', NULL], $props['role']['enum']);
    self::assertSame(1, $props['close_label']['minLength']);

    $story = Yaml::parse((string) file_get_contents($themeDir . '/components/status-messages/status-messages.dismissible.story.yml'));
    self::assertTrue($story['props']['dismissible']);
    self::assertSame('Fechar aviso do carrinho', $story['props']['close_label']);

    $template = (string) file_get_contents($themeDir . '/templates/portal-cart-status.html.twig');
    self::assertStringContainsString("include('aculta420_v2:status-messages'", $template);
    self::assertStringContainsString("type: status_type == 'error' ? 'error' : 'status'", $template);
    self::assertStringContainsString("'data-portal-cart-status': ''", $template);
    self::assertStringContainsString('dismissible: true', $template);
  }

}
