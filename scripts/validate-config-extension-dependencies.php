<?php

/**
 * @file
 * Validates module providers referenced by manifests and synchronized config.
 *
 * The gate is static and does not bootstrap or modify Drupal. It checks that
 * required dependencies for modules enabled by Config Sync, and module
 * dependencies declared by Config Sync objects, are listed in
 * config/sync/core.extension.yml.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$extensionPath = $root . '/config/sync/core.extension.yml';
if (!is_file($extensionPath)) {
  fwrite(STDERR, "CONFIG EXTENSION DEPENDENCIES FAIL: core.extension.yml not found.\n");
  exit(1);
}

$extension = Yaml::parseFile($extensionPath);
$enabled = $extension['module'] ?? [];
$failures = [];
$checks = 0;

$moduleName = static function (string $dependency): string {
  $separator = strrpos($dependency, ':');
  return $separator === FALSE ? $dependency : substr($dependency, $separator + 1);
};

$moduleRoot = $root . '/web/modules/custom';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), '.info.yml')) {
    continue;
  }
  $relative = substr($file->getPathname(), strlen($root) + 1);
  if (preg_match('#(?:^|/)tests/modules(?:/|$)#', $relative) === 1) {
    continue;
  }
  $manifest = Yaml::parseFile($file->getPathname());
  if (($manifest['type'] ?? '') !== 'module') {
    continue;
  }
  $machineName = substr($file->getFilename(), 0, -9);
  if (!isset($enabled[$machineName])) {
    continue;
  }
  foreach ($manifest['dependencies'] ?? [] as $dependency) {
    $provider = $moduleName((string) $dependency);
    $checks++;
    if (!isset($enabled[$provider])) {
      $failures[] = "$relative requires '$provider', but it is absent from config/sync/core.extension.yml.";
    }
  }
}

$configRoot = $root . '/config/sync';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configRoot, FilesystemIterator::SKIP_DOTS));
$configFiles = 0;
foreach ($iterator as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), '.yml') || str_ends_with($file->getFilename(), '.schema.yml')) {
    continue;
  }
  $config = Yaml::parseFile($file->getPathname());
  $configFiles++;
  foreach ($config['dependencies']['module'] ?? [] as $dependency) {
    $provider = $moduleName((string) $dependency);
    $checks++;
    if (!isset($enabled[$provider])) {
      $relative = substr($file->getPathname(), strlen($root) + 1);
      $failures[] = "$relative requires module '$provider', but it is absent from config/sync/core.extension.yml.";
    }
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "CONFIG EXTENSION DEPENDENCIES FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("CONFIG EXTENSION DEPENDENCIES: FAIL (%d failures in %d checks; %d config files)\n", count($failures), $checks, $configFiles));
  exit(1);
}

echo sprintf("CONFIG EXTENSION DEPENDENCIES: PASS (%d checks; %d config files)\n", $checks, $configFiles);
