<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Status;

/**
 * Resolves required PHP extensions for the Portal's Composer-backed modules.
 */
final class PhpExtensionRequirements {

  /**
   * Builds the extension inventory from locked runtime package dependencies.
   *
   * Development packages and optional `suggest` metadata are intentionally
   * excluded. An extension is available only when PHP reports it as loaded.
   *
   * @param array $requirements
   *   Portal module requirement declarations.
   * @param array $packages
   *   Runtime package records from composer.lock.
   * @param string[] $loadedExtensions
   *   Extensions returned by `get_loaded_extensions()` in the current PHP SAPI.
   *
   * @return array
   *   Rows keyed by extension name, including the integrations that require it.
   */
  public static function build(array $requirements, array $packages, array $loadedExtensions): array {
    $packageMap = [];
    foreach ($packages as $package) {
      if (is_array($package) && is_string($package['name'] ?? NULL)) {
        $packageMap[$package['name']] = $package;
      }
    }

    $loaded = array_fill_keys(array_map('strtolower', $loadedExtensions), TRUE);
    $extensions = [];
    foreach ($requirements as $requirement) {
      $roots = array_filter([
        $requirement['package'] ?? NULL,
        $requirement['related_package'] ?? NULL,
      ], 'is_string');
      $visited = [];
      $declared = [];
      foreach ($roots as $root) {
        self::collect($root, $packageMap, $visited, $declared);
      }

      foreach ($declared as $extension => $sourcePackages) {
        $extensions[$extension]['integrations'][$requirement['name']] = TRUE;
        foreach (($requirement['modules'] ?? []) as $module) {
          $extensions[$extension]['modules'][$module] = TRUE;
        }
        foreach (array_keys($sourcePackages) as $sourcePackage) {
          $extensions[$extension]['packages'][$sourcePackage] = TRUE;
        }
      }
    }

    ksort($extensions);
    $rows = [];
    foreach ($extensions as $extension => $data) {
      $phpExtension = substr($extension, 4);
      $rows[] = [
        'extension' => $extension,
        'integrations' => array_keys($data['integrations']),
        'modules' => array_keys($data['modules']),
        'packages' => array_keys($data['packages']),
        'loaded' => isset($loaded[$phpExtension]),
      ];
    }
    return $rows;
  }

  /** Recursively collects required ext-* packages in a Composer dependency tree. */
  private static function collect(string $name, array $packageMap, array &$visited, array &$extensions): void {
    if (isset($visited[$name]) || !isset($packageMap[$name])) {
      return;
    }
    $visited[$name] = TRUE;
    foreach (($packageMap[$name]['require'] ?? []) as $dependency => $constraint) {
      if (str_starts_with($dependency, 'ext-')) {
        $extensions[$dependency][$name] = TRUE;
      }
      elseif (isset($packageMap[$dependency])) {
        self::collect($dependency, $packageMap, $visited, $extensions);
      }
    }
  }

}
