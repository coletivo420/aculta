<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

require_once __DIR__ . '/AcultaV2ThemeKernelTestTrait.php';

/**
 * Verifies SDC discovery can use the real V2 theme in a Kernel test.
 */
#[Group('aculta_document_viewer')]
final class AcultaV2ThemeDiscoveryKernelTest extends KernelTestBase {

  use AcultaV2ThemeKernelTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Installs the theme for rendering without changing the site's default.
   */
  public function testThemeCanBeActivatedForSdcRendering(): void {
    $this->installConfig(['system']);

    $this->activateAcultaV2ThemeForSdcRendering();

    self::assertSame('aculta420_v2', $this->container->get('theme.manager')->getActiveTheme()->getName());
  }

}
