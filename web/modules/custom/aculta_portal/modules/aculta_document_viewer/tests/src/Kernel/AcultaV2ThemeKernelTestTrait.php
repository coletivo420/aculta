<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Kernel;

/**
 * Boots ACULTA420 V2 component discovery without changing system.theme.
 */
trait AcultaV2ThemeKernelTestTrait {

  /**
   * Installs and activates the real SDC theme for rendered Kernel responses.
   */
  protected function activateAcultaV2ThemeForSdcRendering(): void {
    $configFactory = $this->container->get('config.factory');
    $defaultTheme = $configFactory->get('system.theme')->get('default');

    $themeInstaller = $this->container->get('theme_installer');
    $themeInstaller->install(['aculta420_v2']);

    $themeInitialization = $this->container->get('theme.initialization');
    $themeManager = $this->container->get('theme.manager');
    $themeManager->setActiveTheme($themeInitialization->initTheme('aculta420_v2'));

    self::assertSame($defaultTheme, $configFactory->get('system.theme')->get('default'));
  }

}
