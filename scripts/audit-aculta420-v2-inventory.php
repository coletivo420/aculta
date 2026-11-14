<?php

/**
 * @file
 * Inventário auditável do ACULTA420 V2 para a refatoração ADR-054.
 *
 * Lê o Config Sync e os arquivos do tema. Não altera nada e não é gate:
 * informa o estado que o plano P0 precisa registrar.
 *
 * Uso: php scripts/audit-aculta420-v2-inventory.php [--json]
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$json = in_array('--json', $argv, TRUE);

$regions = [
  'institution', 'header', 'navigation', 'highlighted', 'help', 'breadcrumb',
  'content', 'sidebar_first', 'footer',
];
$finalRegions = ['institution', 'header', 'content', 'sidebar_first', 'footer'];

$blocks = [];
foreach (glob($root . '/config/sync/block.block.aculta420_v2_*.yml') ?: [] as $file) {
  $data = Yaml::parseFile($file);
  if (!is_array($data)) {
    fwrite(STDERR, "YAML inválido: $file\n");
    continue;
  }
  $blocks[] = [
    'id' => $data['id'] ?? basename($file, '.yml'),
    'plugin' => $data['plugin'] ?? '',
    'region' => $data['region'] ?? '',
    'weight' => $data['weight'] ?? 0,
    'status' => (bool) ($data['status'] ?? TRUE),
    'visibility' => array_keys($data['visibility'] ?? []),
  ];
}
usort($blocks, static fn (array $a, array $b): int => [$a['region'], $a['weight']] <=> [$b['region'], $b['weight']]);

$displays = [];
foreach (glob($root . '/config/sync/core.entity_view_display.*.yml') ?: [] as $file) {
  $data = Yaml::parseFile($file);
  if (!is_array($data)) {
    continue;
  }
  $lb = $data['third_party_settings']['layout_builder'] ?? [];
  $displays[] = [
    'entity' => $data['targetEntityType'] ?? '',
    'bundle' => $data['bundle'] ?? '',
    'mode' => $data['mode'] ?? '',
    'layout_builder' => (bool) ($lb['enabled'] ?? FALSE),
    'file' => basename($file),
  ];
}

$regionCounts = array_fill_keys($regions, 0);
foreach ($blocks as $block) {
  if (isset($regionCounts[$block['region']])) {
    $regionCounts[$block['region']]++;
  }
}

$orphans = array_values(array_filter(
  $blocks,
  static fn (array $block): bool => $block['region'] !== '' && !in_array($block['region'], $finalRegions, TRUE),
));

$libraryFile = $root . '/web/themes/custom/aculta420_v2/aculta420_v2.libraries.yml';
$libraries = Yaml::parseFile($libraryFile) ?: [];
$global = $libraries['global-styling']['css']['theme'] ?? [];

$report = [
  'generated_at' => date('c'),
  'blocks_total' => count($blocks),
  'blocks_by_region' => $regionCounts,
  'blocks_outside_final_regions' => count($orphans),
  'displays_total' => count($displays),
  'displays_layout_builder_enabled' => count(array_filter($displays, static fn (array $d): bool => $d['layout_builder'])),
  'global_styling_files' => array_keys($global),
  'blocks' => $blocks,
  'displays' => $displays,
];

if ($json) {
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
  exit(0);
}

echo "ACULTA420 V2 — inventário ADR-054\n";
echo "Blocos: {$report['blocks_total']} | Fora das cinco regiões finais: {$report['blocks_outside_final_regions']}\n";
echo "Displays: {$report['displays_total']} | Layout Builder habilitado: {$report['displays_layout_builder_enabled']}\n\n";
echo "Blocos por região:\n";
foreach ($regionCounts as $region => $count) {
  $mark = in_array($region, $finalRegions, TRUE) ? 'final' : 'migrar';
  echo sprintf("  %-14s %2d  (%s)\n", $region, $count, $mark);
}
echo "\nArquivos em global-styling: " . count($global) . "\n";
foreach (array_keys($global) as $asset) {
  echo "  - $asset\n";
}
echo "\nBlocos fora das cinco regiões finais:\n";
foreach ($orphans as $block) {
  echo "  - {$block['id']} ({$block['plugin']}) em {$block['region']}\n";
}
