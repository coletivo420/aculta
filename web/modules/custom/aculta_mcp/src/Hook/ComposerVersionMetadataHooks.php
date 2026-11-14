<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp\Hook;

use Composer\InstalledVersions;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Restores Composer branch version metadata missing from source checkouts.
 */
final class ComposerVersionMetadataHooks {

  /**
   * Supplies Simple OAuth's installed Composer version to Drupal discovery.
   */
  #[Hook('system_info_alter')]
  public function alterSystemInfo(array &$info, Extension $extension, string $type): void {
    if ($type !== 'module' || $extension->getName() !== 'simple_oauth' || isset($info['version'])
      || !InstalledVersions::isInstalled('drupal/simple_oauth')) {
      return;
    }

    $version = self::versionFromComposerBranch(InstalledVersions::getPrettyVersion('drupal/simple_oauth'));
    if ($version !== NULL) {
      $info['version'] = $version;
    }
  }

  /**
   * Converts Composer's dev-branch label to a version Drupal can compare.
   */
  public static function versionFromComposerBranch(?string $version): ?string {
    if ($version === NULL || preg_match('/^dev-(\d+\.\d+\.x)$/', $version, $matches) !== 1) {
      return NULL;
    }
    return $matches[1] . '-dev';
  }

}
