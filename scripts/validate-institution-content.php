<?php

/**
 * @file
 */

declare(strict_types=1);

/**
 * Gate for the versioned home content (DT-T10).
 *
 * Checks that scripts/content/institution/home-content.json is complete for
 * the configuration that depends on it: every block_content UUID referenced
 * by a block placement in config/sync is declared in the JSON, UUIDs are
 * unique, and the content is in the SDC shape (no legacy section or hero
 * markup left in bodies). Run: php
 * scripts/validate-institution-content.php.
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$path = $root . '/scripts/content/institution/home-content.json';
$checks++;
$raw = is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
if (!is_array($raw)) {
  fwrite(STDERR, "INSTITUTION CONTENT FAIL: home-content.json is missing or is not valid JSON.\n");
  exit(1);
}

/** @var array<string, mixed> $data */
$data = $raw;

/** @var array<string, true> $uuids */
$uuids = [];

$blockContent = is_array($data['block_content'] ?? NULL) ? $data['block_content'] : [];
/** @var array<mixed> $blockContent */
foreach ($blockContent as $item) {
  if (!is_array($item)) {
    continue;
  }
  $checks++;
  $uuid = is_string($item['uuid'] ?? NULL) ? $item['uuid'] : '';
  if ($uuid !== '') {
    if (isset($uuids[$uuid])) {
      $failures[] = "block_content UUID $uuid is declared twice.";
    }
    $uuids[$uuid] = TRUE;
  }

  $checks++;
  $body = is_array($item['body'] ?? NULL) ? $item['body'] : [];
  $bodyValue = is_string($body['value'] ?? NULL) ? $body['value'] : '';
  if (str_contains($bodyValue, 'aculta-editorial-section')) {
    $info = is_string($item['info'] ?? NULL) ? $item['info'] : $uuid;
    $failures[] = "block '$info' still has legacy section markup in its body (migrated to fields).";
  }
}

$checks++;
$heroes = is_array($data['node'] ?? NULL) ? $data['node'] : [];
/** @var array<mixed> $heroes */
if ($heroes === []) {
  $failures[] = 'The front page hero (node 1) is not declared.';
}
foreach ($heroes as $hero) {
  if (!is_array($hero)) {
    continue;
  }
  $checks++;
  if (str_contains((string) json_encode($hero), 'class="aculta-hero"')) {
    $title = is_string($hero['title'] ?? NULL) ? $hero['title'] : (is_string($hero['uuid'] ?? NULL) ? $hero['uuid'] : 'unknown');
    $failures[] = "node '$title' still has legacy hero markup.";
  }
  if (is_string($hero['uuid'] ?? NULL)) {
    $uuids[$hero['uuid']] = TRUE;
  }
}

// Every home and projects-header placement that points at a block_content
// must find its content in the JSON. Institutional data
// (aculta_institution) is maintained in configuration and
// is out of this scope (DT-T10): its placements are listed in the output,
// not checked here.
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

echo sprintf("INSTITUTION CONTENT: PASS (%d checks; %d blocks, %d nodes; %d placements out of scope: institutional data)\n", $checks, count($blockContent), count($heroes), count($outOfScope));
