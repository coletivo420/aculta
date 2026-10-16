<?php

/**
 * Static gate for the module x theme map (docs/portal/MAPA-MODULO-TEMA.md).
 *
 * Checks that:
 * - every custom module (*.info.yml under web/modules/custom) has its path in backticks in the map;
 * - every enabled contrib module (config/sync/core.extension.yml, directory under web/modules/contrib) has its
 *   machine name in backticks in the map;
 * - every repository path cited in backticks (web/...) and every theme path cited in backticks
 *   (css/, js/, templates/, components/ under the aculta420 theme) exists.
 *
 * Templates with placeholders (<name>, {a,b}, *) and paths with spaces are documentation syntax and are skipped.
 * The gate does not bootstrap Drupal, so it runs in any checkout.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$mapPath = $root . '/docs/portal/MAPA-MODULO-TEMA.md';
$themeRoot = $root . '/web/themes/custom/aculta420';
$failures = [];
$checks = 0;

if (!is_file($mapPath)) {
  fwrite(STDERR, "MODULE THEME MAP FAIL: docs/portal/MAPA-MODULO-TEMA.md does not exist.\n");
  exit(1);
}

$doc = (string) file_get_contents($mapPath);

if (!str_contains($doc, '## Módulos contrib habilitados')) {
  $failures[] = 'the map has no "Módulos contrib habilitados" section.';
}

// 1. Custom modules: every module directory must be cited by its full repository path.
$customInfo = array_merge(
  glob($root . '/web/modules/custom/*/*.info.yml') ?: [],
  glob($root . '/web/modules/custom/*/modules/*/*.info.yml') ?: [],
  glob($root . '/web/modules/custom/*/modules/*/modules/*/*.info.yml') ?: [],
);
foreach ($customInfo as $info) {
  $relative = substr(dirname($info), strlen($root) + 1);
  $checks++;
  if (!str_contains($doc, '`' . $relative . '`')) {
    $failures[] = "custom module '$relative' is not documented in the map.";
  }
}

// 2. Enabled contrib modules: every machine name must appear in backticks.
$extension = Yaml::parseFile($root . '/config/sync/core.extension.yml');
foreach (array_keys($extension['module'] ?? []) as $name) {
  if (!is_dir($root . '/web/modules/contrib/' . $name)) {
    continue;
  }
  $checks++;
  if (!str_contains($doc, '`' . $name . '`')) {
    $failures[] = "enabled contrib module '$name' is not documented in the map.";
  }
}

// 3. Cited paths must exist.
preg_match_all('/`([^`\s]+)`/u', $doc, $matches);
foreach (array_unique($matches[1]) as $token) {
  if (preg_match('/[<>{}*]/', $token) === 1) {
    continue;
  }
  if (str_starts_with($token, 'web/')) {
    $checks++;
    if (!file_exists($root . '/' . $token)) {
      $failures[] = "cited repository path '$token' does not exist.";
    }
    continue;
  }
  if (preg_match('#^(css|js|templates|components)/#', $token) === 1) {
    $checks++;
    if (!file_exists($themeRoot . '/' . $token)) {
      $failures[] = "cited theme path '$token' does not exist under aculta420.";
    }
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "MODULE THEME MAP FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("MODULE THEME MAP: FAIL (%d failures in %d checks)\n", count($failures), $checks));
  exit(1);
}

echo sprintf("MODULE THEME MAP: PASS (%d checks)\n", $checks);
