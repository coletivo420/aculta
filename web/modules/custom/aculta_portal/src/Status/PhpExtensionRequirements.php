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
   * @param array<int, array<string, mixed>>|array<mixed> $requirements
   *   Portal module requirement declarations.
   * @param array<int, array<string, mixed>>|array<mixed> $packages
   *   Runtime package records from composer.lock.
   * @param string[] $loadedExtensions
   *   Extensions returned by `get_loaded_extensions()` in the current PHP SAPI.
   *
   * @return array<mixed>
   *   Rows keyed by extension name, including the integrations that require it.
   */
  public static function build(array $requirements, array $packages, array $loadedExtensions): array {
    /** @var array<string, array<string, mixed>> $packageMap */
    $packageMap = [];
    foreach ($packages as $package) {
      if (is_array($package) && isset($package['name']) && is_string($package['name'])) {
        /** @var array<string, mixed> $packageTyped */
        $packageTyped = $package;
        $packageMap[$package['name']] = $packageTyped;
      }
    }

    $loaded = array_fill_keys(array_map('strtolower', $loadedExtensions), TRUE);
    /** @var array<string, array{integrations: array<string, true>, modules: array<string, true>, packages: array<string, true>}> $extensions */
    $extensions = [];
    foreach ($requirements as $requirement) {
      if (!is_array($requirement)) {
        continue;
      }
      $requirementName = isset($requirement['name']) && is_string($requirement['name']) ? $requirement['name'] : '';
      $rawRoots = [
        $requirement['package'] ?? NULL,
        $requirement['related_package'] ?? NULL,
      ];
      $extraPackages = isset($requirement['packages']) && is_array($requirement['packages']) ? $requirement['packages'] : [];
      $rawRoots = array_merge($rawRoots, $extraPackages);

      /** @var list<string> $roots */
      $roots = [];
      foreach ($rawRoots as $rawRoot) {
        if (is_string($rawRoot) && $rawRoot !== '') {
          $roots[] = $rawRoot;
        }
      }

      if (($requirement['scan_all'] ?? FALSE) === TRUE) {
        $roots = array_merge($roots, array_keys($packageMap));
      }
      /** @var array<string, true> $visited */
      $visited = [];
      /** @var array<string, array<string, true>> $declared */
      $declared = [];
      foreach ($roots as $root) {
        self::collect($root, $packageMap, $visited, $declared);
      }

      $reqModules = isset($requirement['modules']) && is_array($requirement['modules']) ? $requirement['modules'] : [];
      foreach ($declared as $extension => $sourcePackages) {
        if (!isset($extensions[$extension])) {
          $extensions[$extension] = [
            'integrations' => [],
            'modules' => [],
            'packages' => [],
          ];
        }
        if ($requirementName !== '') {
          $extensions[$extension]['integrations'][$requirementName] = TRUE;
        }
        foreach ($reqModules as $module) {
          if (is_string($module) && $module !== '') {
            $extensions[$extension]['modules'][$module] = TRUE;
          }
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

  /**
   * Recursively collects required ext-* packages in a Composer dependency tree.
   *
   * @param string $name
   *   Package name.
   * @param array<string, array<string, mixed>> $packageMap
   *   Known package data.
   * @param array<string, true> $visited
   *   Visited package tracker.
   * @param array<string, array<string, true>> $extensions
   *   Discovered extensions map.
   */
  private static function collect(string $name, array $packageMap, array &$visited, array &$extensions): void {
    if (isset($visited[$name]) || !isset($packageMap[$name])) {
      return;
    }
    $visited[$name] = TRUE;
    $requires = isset($packageMap[$name]['require']) && is_array($packageMap[$name]['require']) ? $packageMap[$name]['require'] : [];
    foreach ($requires as $dependency => $constraint) {
      $dependencyStr = (string) $dependency;
      if (str_starts_with($dependencyStr, 'ext-')) {
        $extensions[$dependencyStr][$name] = TRUE;
      }
      elseif (isset($packageMap[$dependencyStr])) {
        self::collect($dependencyStr, $packageMap, $visited, $extensions);
      }
    }
  }

}
