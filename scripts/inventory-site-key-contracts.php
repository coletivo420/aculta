<?php

/**
 * @file
 * Generates the Portal site-key contract inventory.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$output = $root . '/docs/architecture/inventarios/site-key-purpose-contracts.csv';
$roots = [
  $root . '/web/modules/custom',
  $root . '/web/themes/custom',
  $root . '/config/sync',
  $root . '/scripts',
  $root . '/tests',
];
$extensions = ['php', 'yml', 'yaml', 'twig', 'css', 'js', 'module', 'install', 'theme'];
$columns = ['path', 'line', 'owner', 'category', 'matched_contract', 'status', 'target', 'risk'];
$patterns = [
  'visual_legacy' => '/PurposePaletteSchemes|purposePaletteStyle|aculta_purpose_palette|data-purpose-scheme|--aculta-purpose-/i',
  'route_metadata' => '/_aculta_(?:site_keys?|cross_domain_canonical_site_key)\b/i',
  'state_persistence' => '/active_purposes|active_purposes/i',
  'session_persistence' => '/login_destination.{0,120}purpose|purpose.{0,120}login_destination/i',
  'presentation_contract' => '/domain_presentation.{0,160}purpose|identity.{0,80}purpose|aculta_purpose\b/i',
  'functional_api' => '/DomainPurposeManager|DomainSurfaceMap|ContentPurposeResolver|DomainPurposeRequestSubscriber|ActivePurposesState|PurposeFrontPathProcessor|siteKeyForDonationRoute|allowsSharedSite|getCurrentPurpose|getPurposeForDomainId|runInPurpose/',
  'generic_purpose_symbol' => '/\bpurpose(?:s)?\b/i',
];
$rows = [];

foreach ($roots as $scanRoot) {
  if (!is_dir($scanRoot)) {
    continue;
  }
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), $extensions, TRUE)) {
      continue;
    }
    $path = relative_path($root, $file->getPathname());
    if (str_contains($path, '/vendor/') || str_contains($path, '/.git/')) {
      continue;
    }
    $contents = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
    if ($contents === FALSE) {
      continue;
    }
    foreach ($contents as $index => $line) {
      $category = NULL;
      foreach ($patterns as $candidate => $pattern) {
        if (preg_match($pattern, $line) === 1) {
          $category = $candidate;
          break;
        }
      }
      if ($category === NULL) {
        continue;
      }
      $rows[] = [
        'path' => $path,
        'line' => (string) ($index + 1),
        'owner' => owner_from_path($path),
        'category' => $category,
        'matched_contract' => $category === 'generic_purpose_symbol' ? 'purpose' : $category,
        'status' => 'MIGRATION_IN_PROGRESS',
        'target' => $category === 'visual_legacy' ? 'UI Skins token/skin contract' : 'site_key or domain_id per RN-00 contract map',
        'risk' => $category === 'visual_legacy' ? 'Visual behavior, contrast, cache and fallback' : 'Route/access/session/State/data contract; preserve behavior and persisted values',
      ];
    }
  }
}

usort($rows, static fn (array $a, array $b): int => [$a['path'], (int) $a['line']] <=> [$b['path'], (int) $b['line']]);
$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0775, TRUE) && !is_dir($directory)) {
  throw new RuntimeException('Unable to create inventory output directory.');
}
$handle = fopen($output, 'wb');
if ($handle === FALSE) {
  throw new RuntimeException('Unable to write site key inventory CSV.');
}
fputcsv($handle, $columns, ';', '"', '');
foreach ($rows as $row) {
  fputcsv($handle, array_values($row), ';', '"', '');
}
fclose($handle);
printf("Wrote %d site-key contract references to %s\n", count($rows), relative_path($root, $output));

/**
 * Returns a repository-relative path for an inventory record.
 */
function relative_path(string $root, string $path): string {
  return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
}

/**
 * Resolves a module owner from a custom module path when possible.
 */
function owner_from_path(string $path): string {
  if (preg_match('#^web/modules/custom/([^/]+)#', $path, $matches) === 1) {
    return $matches[1];
  }
  if (preg_match('#^web/themes/custom/([^/]+)#', $path, $matches) === 1) {
    return $matches[1];
  }
  return str_starts_with($path, 'config/sync/') ? 'config_sync' : 'project_tooling';
}
