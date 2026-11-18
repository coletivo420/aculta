<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * Checks that menu icons cannot replace their accessible text labels.
 */
final class UiIconsMenuAccessibilityConfigTest extends UnitTestCase {

  /**
   * The menu icon position selector includes an unlabeled icon-only option.
   */
  public function testMenusKeepTextAlongsideDecorativeIcons(): void {
    $root = dirname(__DIR__, 7);

    foreach (['main', 'account'] as $menu) {
      $contents = file_get_contents($root . '/config/sync/system.menu.' . $menu . '.yml');
      self::assertIsString($contents);

      $configuration = Yaml::decode($contents);
      self::assertIsArray($configuration);

      $thirdPartySettings = $configuration['third_party_settings'] ?? NULL;
      self::assertIsArray($thirdPartySettings);

      $iconSettings = $thirdPartySettings['ui_icons_menu'] ?? NULL;
      self::assertIsArray($iconSettings);
      self::assertFalse($iconSettings['icon_position'] ?? NULL);
    }
  }

}
