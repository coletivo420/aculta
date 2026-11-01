<?php

/**
 * @file
 * Static schema gate for ACULTA420 SDC components.
 *
 * This is read-only and does not bootstrap Drupal.
 *
 * Checks, for every components/**\/<name>/<name>.component.yml:
 * - Required metadata: name, status (stable|experimental|deprecated), group,
 *   description.
 * - Props: an object schema; each property declares a supported type; enum
 *   values match the type;
 *   a default is one of the enum values when an enum exists;
 * - Slots: a map (may be empty); each slot declares a title.
 * - Stable components keep a non-empty description.
 *
 * Also checks, for every include('aculta420:<name>', {...}) call in templates/
 * and components/:
 * - The component exists.
 * - Every variable passed is a declared prop or slot.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__) . '/web/themes/custom/aculta420';
$failures = [];
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$condition) {
    $failures[] = $message;
  }
};

$allowedStatus = ['stable', 'experimental', 'deprecated'];
$typeMap = [
  'string' => 'is_string',
  'integer' => 'is_int',
  'number' => 'is_numeric',
  'boolean' => 'is_bool',
  'array' => 'is_array',
  'object' => 'is_array',
];
$supportedClassTypes = [
  'Drupal\\Core\\Template\\Attribute',
];

$components = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/components', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
  if (!str_ends_with($file->getFilename(), '.component.yml')) {
    continue;
  }
  $name = basename($file->getPath());
  $relative = str_replace($root . '/', '', $file->getPathname());
  $meta = Yaml::parseFile($file->getPathname());
  $components[$name] = ['meta' => $meta, 'path' => $file->getPathname()];

  $assert(is_string($meta['name'] ?? NULL) && $meta['name'] !== '', "$relative: missing name.");
  $assert(in_array($meta['status'] ?? NULL, $allowedStatus, TRUE), "$relative: status must be stable, experimental or deprecated.");
  $assert(is_string($meta['group'] ?? NULL) && $meta['group'] !== '', "$relative: missing group.");
  $assert(is_string($meta['description'] ?? NULL) && trim((string) $meta['description']) !== '', "$relative: missing description.");
  if (($meta['status'] ?? NULL) === 'stable') {
    $assert(trim((string) ($meta['description'] ?? '')) !== '', "$relative: stable component needs a description.");
  }

  $props = $meta['props']['properties'] ?? NULL;
  $assert(($meta['props']['type'] ?? NULL) === 'object', "$relative: props must be an object schema.");
  $props = is_array($props) ? $props : [];
  foreach ($props as $propName => $prop) {
    $type = $prop['type'] ?? NULL;
    $types = is_array($type) ? $type : [$type];
    $ok = TRUE;
    foreach ($types as $t) {
      if ($t !== 'null' && !isset($typeMap[(string) $t]) && !in_array((string) $t, $supportedClassTypes, TRUE)) {
        $ok = FALSE;
      }
    }
    $assert($ok, "$relative: prop '$propName' has an unsupported type.");
    if (isset($prop['enum'])) {
      $assert(is_array($prop['enum']) && $prop['enum'] !== [], "$relative: prop '$propName' enum must be a non-empty list.");
      if (array_key_exists('default', $prop) && is_array($prop['enum'])) {
        $assert(in_array($prop['default'], $prop['enum'], TRUE), "$relative: prop '$propName' default is not in its enum.");
      }
    }
  }

  $slots = $meta['slots'] ?? [];
  $assert(is_array($slots), "$relative: slots must be a map.");
  foreach (is_array($slots) ? $slots : [] as $slotName => $slot) {
    $assert(is_array($slot) && isset($slot['title']) && is_string($slot['title']), "$relative: slot '$slotName' needs a title.");
  }

  $components[$name]['declared'] = array_merge(array_keys($props), array_keys(is_array($slots) ? $slots : []));
}

$calls = [];
foreach (['templates', 'components'] as $dir) {
  $walker = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
  foreach ($walker as $file) {
    if (!str_ends_with($file->getFilename(), '.twig')) {
      continue;
    }
    $source = file_get_contents($file->getPathname());
    preg_match_all("/include\\(\\s*'aculta420:([a-z0-9-]+)'\\s*,\\s*\\{(.*?)\\}\\s*,/s", $source, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $calls[] = [
        'component' => $match[1],
        'keys' => $match[2],
        'file' => str_replace($root . '/', '', $file->getPathname()),
      ];
    }
  }
}

foreach ($calls as $call) {
  $label = $call['file'] . " -> aculta420:" . $call['component'];
  $assert(isset($components[$call['component']]), "$label: component does not exist.");
  if (!isset($components[$call['component']])) {
    continue;
  }
  // Ternary branches (`? value :`) are values, not keys. Drop them before
  // matching keys.
  $objectKeys = preg_replace('/\?\s*[^,?]*?\s*:/', '?', $call['keys']);
  preg_match_all('/([a-z_][a-z0-9_]*)\s*:/', $objectKeys, $keys);
  foreach (array_unique($keys[1]) as $key) {
    $assert(in_array($key, $components[$call['component']]['declared'], TRUE), "$label: variable '$key' is not declared by the component.");
  }
}

$assert(count($components) > 0, 'No SDC components found.');
$assert(count($calls) > 0, 'No SDC include calls found.');

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "SDC SCHEMA FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("SDC SCHEMA: FAIL (%d failures in %d checks)\n", count($failures), $checks));
  exit(1);
}

echo sprintf("SDC SCHEMA: PASS (%d components, %d include calls, %d checks)\n", count($components), count($calls), $checks);
