<?php

/**
 * Read-only inventory and contract audit of ACULTA420 component assets.
 *
 * Usage: php scripts/audit-aculta420-sdc-assets.php
 * Requires the project's Composer dependencies; never bootstraps Drupal.
 * This audit does not replace Core SDC validation or browser tests.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__) . '/web/themes/custom/aculta420/components';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($iterator as $file) {
  if ($file->isFile()) {
    $files[] = $file->getPathname();
  }
}
sort($files, SORT_STRING);
$metas = array_values(array_filter($files, static fn(string $p): bool => str_ends_with($p, '.component.yml')));
$issues = [];
$counts = ['components' => 0, 'twig' => 0, 'css' => 0, 'stories' => 0];
$fail = static function (string $path, string $message) use (&$issues, $root): void {
  $issues[] = str_replace($root . '/', '', $path) . ': ' . $message;
};

foreach ($metas as $path) {
  $counts['components']++;
  $dir = dirname($path);
  $name = basename($dir);
  $twig = $dir . '/' . $name . '.twig';
  if (!is_file($twig)) {
    $fail($path, 'missing component Twig template');
  }
  $meta = Yaml::parseFile($path);
  if (!is_array($meta)) {
    $fail($path, 'metadata must parse into a map');
    continue;
  }
  $schema = $meta['$schema'] ?? '';
  if (!is_string($schema) || !str_contains($schema, '/project/drupal/') || !str_contains($schema, '/metadata.schema.json')) {
    $fail($path, 'metadata references non-Core or missing SDC schema');
  }
  $props = array_keys(is_array($meta['props']['properties'] ?? NULL) ? $meta['props']['properties'] : []);
  $slots = array_keys(is_array($meta['slots'] ?? NULL) ? $meta['slots'] : []);
  foreach ($files as $asset) {
    if (!str_starts_with($asset, $dir . '/')) {
      continue;
    }
    if (str_ends_with($asset, '.story.yml')) {
      $story = Yaml::parseFile($asset);
      $expected = 'aculta420:' . $name;
      if (!is_array($story) || ($story['component'] ?? NULL) !== $expected) {
        $fail($asset, 'story references a component other than ' . $expected);
        continue;
      }
      foreach (['props' => $props, 'slots' => $slots] as $section => $allowed) {
        $entries = $story[$section] ?? [];
        if (!is_array($entries)) {
          $fail($asset, $section . ' must be a map');
          continue;
        }
        foreach (array_keys($entries) as $key) {
          if (!in_array($key, $allowed, TRUE)) {
            $fail($asset, 'undeclared ' . $section . ' key: ' . $key);
          }
        }
      }
    }
  }
}
foreach ($files as $file) {
  if (str_ends_with($file, '.story.yml')) {
    $counts['stories']++;
  }
  elseif (str_ends_with($file, '.twig')) {
    $counts['twig']++;
  }
  elseif (str_ends_with($file, '.css')) {
    $counts['css']++;
  }
}
sort($issues, SORT_STRING);
echo 'ACULTA420 SDC asset audit (static only): ' . json_encode($counts, JSON_UNESCAPED_SLASHES) . PHP_EOL;
foreach ($issues as $issue) {
  echo 'FINDING: ' . $issue . PHP_EOL;
}
echo 'Findings: ' . count($issues) . PHP_EOL;
exit($issues === [] ? 0 : 1);
