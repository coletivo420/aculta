<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_linkweed\Unit;

use Drupal\aculta_linkweed\Hook\AcultaLinksThemeHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that registered Links Twig templates exist in the module.
 */
#[Group('aculta_linkweed')]
#[CoversClass(AcultaLinksThemeHooks::class)]
final class LinksThemeHooksTest extends UnitTestCase {

  /**
   * Registered page and landing templates resolve to module files.
   */
  public function testRegisteredTemplatesExist(): void {
    $module_path = dirname(__DIR__, 3);
    $definitions = (new AcultaLinksThemeHooks())->theme();

    foreach (['aculta_linkweed_page', 'aculta_linkweed_landing'] as $hook) {
      self::assertArrayHasKey($hook, $definitions);
      $template = $definitions[$hook]['template'];
      self::assertFileExists($module_path . '/templates/' . $template . '.html.twig');
    }
  }

}
