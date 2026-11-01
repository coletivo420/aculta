<?php

declare(strict_types=1);

/**
 * Gate for the versioned home content (DT-T10).
 *
 * Checks that scripts/content/institution/home-content.json is complete for the configuration
 * that depends on it: every block_content UUID referenced by a block placement in config/sync
 * is declared in the JSON, UUIDs are unique, and the content is in the SDC shape (no legacy
 * section or hero markup left in bodies). Run: php scripts/validate-institution-content.php
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$path = $root . '/scripts/content/institution/home-content.json';
$checks++;
$data = is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
if (!is_array($data)) {
  fwrite(STDERR, "INSTITUTION CONTENT FAIL: home-content.json is missing or is not valid JSON.\n");
  exit(1);
}

$uuids = [];
foreach ($data['block_content'] ?? [] as $item) {
  $checks++;
  $uuid = $item['uuid'] ?? '';
  if (isset($uuids[$uuid])) {
    $failures[] = "block_content UUID $uuid is declared twice.";
  }
  $uuids[$uuid] = TRUE;
  $checks++;
  if (str_contains((string) ($item['body']['value'] ?? ''), 'aculta-editorial-section')) {
    $failures[] = "block '" . ($item['info'] ?? $uuid) . "' still has legacy section markup in its body (migrated to fields).";
  }
}

$checks++;
$heroes = $data['node'] ?? [];
if ($heroes === []) {
  $failures[] = 'The front page hero (node 1) is not declared.';
}
foreach ($heroes as $hero) {
  $checks++;
  if (str_contains((string) json_encode($hero), 'class="aculta-hero"')) {
    $failures[] = "node '" . ($hero['title'] ?? $hero['uuid']) . "' still has legacy hero markup.";
  }
  $uuids[$hero['uuid'] ?? ''] = TRUE;
}

// Every home and projects-header placement that points at a block_content must find its content in
// the JSON. Institutional data (aculta_institution) is created by scripts/install-institution.php and
// is out of this scope (DT-T10): its placements are listed in the output, not checked here.
$scope = '/^block\.block\.(aculta_home_|aculta_projects_header_)/';
$outOfScope = [];
foreach (glob($root . '/config/sync/block.block.*.yml') ?: [] as $file) {
  if (preg_match($scope, basename($file)) !== 1) {
    $outOfScope[] = basename($file);
    continue;
  }
  if (!preg_match_all('/block_content:(?:basic:)?([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/', (string) file_get_contents($file), $matches)) {
    continue;
  }
  foreach (array_unique($matches[1]) as $uuid) {
    $checks++;
    if (!isset($uuids[$uuid])) {
      $failures[] = basename($file) . " references block_content $uuid, which home-content.json does not declare.";
    }
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "INSTITUTION CONTENT FAIL: $failure\n");
  }
  exit(1);
}

echo sprintf("INSTITUTION CONTENT: PASS (%d checks; %d blocks, %d nodes; %d placements out of scope: institutional data)\n", $checks, count($data['block_content'] ?? []), count($heroes), count($outOfScope));
