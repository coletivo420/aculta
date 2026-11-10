<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$output = $root . '/docs/architecture/inventarios/ui-suite-consumidores-iniciais.csv';
$sync = $root . '/config/sync';
$theme = $root . '/web/themes/custom/aculta420_v2';
$customModules = $root . '/web/modules/custom';

require $root . '/vendor/autoload.php';

$columns = [
  'id',
  'owner_module',
  'config_path',
  'consumer_type',
  'consumer_id',
  'display_plugin',
  'row_plugin',
  'style_plugin',
  'ui_patterns_configured',
  'status',
  'replacement_or_action',
  'risk_or_evidence_gap',
  'rollback',
  'detected_consumers',
];
$rows = [];

foreach (glob($sync . '/views.view.*.yml') ?: [] as $path) {
  $view = Yaml::parseFile($path);
  $viewId = (string) ($view['id'] ?? basename($path));
  foreach (($view['display'] ?? []) as $displayId => $display) {
    $options = $display['display_options'] ?? [];
    $rowPlugin = (string) ($options['row']['type'] ?? '');
    $stylePlugin = (string) ($options['style']['type'] ?? '');
    $rows[] = [
      'id' => $viewId,
      'owner_module' => 'unresolved_from_config',
      'config_path' => relativePath($root, $path),
      'consumer_type' => 'view_display',
      'consumer_id' => (string) $displayId,
      'display_plugin' => (string) ($display['display_plugin'] ?? ''),
      'row_plugin' => $rowPlugin,
      'style_plugin' => $stylePlugin,
      'ui_patterns_configured' => 'not_detected_by_plugin_id_scan',
      'status' => 'MIGRATION_IN_PROGRESS',
      'replacement_or_action' => 'Inventory candidate only; inspect row, style and display independently before migration',
      'risk_or_evidence_gap' => 'No UI Patterns row/style/display plugin ID detected in Sync; Runtime output, access and cache still unverified',
      'rollback' => 'Revert only display config after before/after result comparison',
      'detected_consumers' => '',
    ];
  }
}

foreach (glob($sync . '/core.entity_view_display.*.yml') ?: [] as $path) {
  $display = Yaml::parseFile($path);
  $enabled = $display['third_party_settings']['layout_builder']['enabled'] ?? FALSE;
  if ($enabled !== TRUE) {
    continue;
  }
  $id = (string) ($display['id'] ?? basename($path));
  $rows[] = [
    'id' => $id,
    'owner_module' => 'Drupal entity display',
    'config_path' => relativePath($root, $path),
    'consumer_type' => 'entity_view_display',
    'consumer_id' => (string) ($display['mode'] ?? ''),
    'display_plugin' => 'layout_builder',
    'row_plugin' => '',
    'style_plugin' => '',
    'ui_patterns_configured' => 'layout_builder',
    'status' => 'UI_SUITE_NATIVE',
    'replacement_or_action' => 'Retain ComponentLayout and check component/slot configuration',
    'risk_or_evidence_gap' => 'Entity access, field order, render cache and known 2 exclusions; Runtime not compared',
    'rollback' => 'Restore captured display configuration; no entity/data changes',
    'detected_consumers' => '',
  ];
}

$componentIds = [];
foreach (glob($theme . '/components/*/*.component.yml') ?: [] as $manifest) {
  $componentIds[basename(dirname($manifest))] = relativePath($root, $manifest);
}
$references = array_fill_keys(array_keys($componentIds), []);
$scanRoots = [
  $customModules,
  $theme . '/templates',
  $theme . '/src',
  $sync,
];
foreach ($scanRoots as $scanRoot) {
  if (!is_dir($scanRoot)) {
    continue;
  }
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'twig', 'yml', 'yaml', 'css', 'js'], TRUE)) {
      continue;
    }
    $contents = file_get_contents($file->getPathname());
    if (!is_string($contents)) {
      continue;
    }
    foreach ($componentIds as $componentId => $_manifest) {
      if (preg_match('/aculta420_v2:' . preg_quote($componentId, '/') . '\b/', $contents) === 1) {
        $references[$componentId][] = relativePath($root, $file->getPathname());
      }
    }
  }
}

foreach ($componentIds as $componentId => $manifest) {
  $consumers = array_values(array_unique($references[$componentId]));
  sort($consumers);
  $rows[] = [
    'id' => 'aculta420_v2:' . $componentId,
    'owner_module' => 'aculta420_v2',
    'config_path' => $manifest,
    'consumer_type' => 'sdc',
    'consumer_id' => $componentId,
    'display_plugin' => 'component',
    'row_plugin' => '',
    'style_plugin' => '',
    'ui_patterns_configured' => 'discoverable_sdc_definition',
    'status' => 'UI_SUITE_NATIVE',
    'replacement_or_action' => $consumers === [] ? 'Retain as internal/editorial catalog item; do not hide from UI Patterns Library' : 'Keep SDC; verify every detected consumer remains catalogued',
    'risk_or_evidence_gap' => count($consumers) . ' explicit component-ID reference files; Runtime discovery/catalog not verified',
    'rollback' => 'No removal; revert consumer integration independently',
    'detected_consumers' => implode(', ', $consumers),
  ];
}

usort($rows, static fn (array $a, array $b): int => [$a['consumer_type'], $a['id'], $a['consumer_id']] <=> [$b['consumer_type'], $b['id'], $b['consumer_id']]);
$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0775, TRUE) && !is_dir($directory)) {
  throw new RuntimeException('Unable to create inventory output directory.');
}
$handle = fopen($output, 'wb');
if ($handle === FALSE) {
  throw new RuntimeException('Unable to write inventory CSV.');
}
fputcsv($handle, $columns, ';');
foreach ($rows as $row) {
  fputcsv($handle, array_map(static fn (string $column): string => (string) $row[$column], $columns), ';');
}
fclose($handle);
printf("Wrote %d inventory rows to %s\n", count($rows), relativePath($root, $output));

/**
 * Returns a repository-relative path for an inventory record.
 */
function relativePath(string $root, string $path): string {
  return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
}
