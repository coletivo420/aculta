<?php

/**
 * @file
 * Planejador de habilitação do Layout Builder por display (ADR-054 P4.4).
 *
 * Somente leitura. Classifica os displays do Config Sync e imprime o lote
 * proposto. Não escreve configuração: a habilitação exige importação em
 * ambiente isolado e teste de renderização/rollback por display.
 *
 * Uso:
 *   php scripts/audit-aculta420-layout-builder.php [--json]
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$json = in_array('--json', $argv, TRUE);

$incompatibleEntities = ['message'];
$incompatibleModes = ['media_library'];
// Listagens, field formatters e telas com controller próprio: validar antes.
$adapterEntities = ['aculta_forum_post', 'comment', 'commerce_order', 'commerce_order_item', 'commerce_product', 'commerce_product_variation', 'commerce_store', 'group', 'group_relationship', 'profile', 'media', 'media_gallery', 'user'];
$adapterBundles = ['aculta_forum_topic', 'aculta_lms_topic'];
$adapterModes = ['teaser', 'compact', 'forum_search_result', 'cart', 'summary'];

$rows = [];
foreach (glob($root . '/config/sync/core.entity_view_display.*.yml') ?: [] as $file) {
  $data = Yaml::parseFile($file);
  $entity = (string) ($data['targetEntityType'] ?? '');
  $bundle = (string) ($data['bundle'] ?? '');
  $mode = (string) ($data['mode'] ?? '');
  $lb = (array) ($data['third_party_settings']['layout_builder'] ?? []);

  if (in_array($entity, $incompatibleEntities, TRUE) || in_array($mode, $incompatibleModes, TRUE)) {
    $class = 'INCOMPATÍVEL';
    $why = in_array($entity, $incompatibleEntities, TRUE)
      ? 'Display de e-mail (render de mensagem), não de página.'
      : 'Interface administrativa Media Library do Core.';
  }
  elseif (in_array($entity, $adapterEntities, TRUE) || in_array($bundle, $adapterBundles, TRUE) || in_array($mode, $adapterModes, TRUE)) {
    $class = 'COMPATÍVEL COM ADAPTADOR';
    $why = 'Há template/controller próprio, carrinho, acesso ou composição especial; validar por display.';
  }
  else {
    $class = 'COMPATÍVEL';
    $why = 'Display editorial sem controller próprio conhecido; candidato ao lote inicial.';
  }

  $rows[] = [
    'entity' => $entity,
    'bundle' => $bundle,
    'mode' => $mode,
    'layout_builder_enabled' => (bool) ($lb['enabled'] ?? FALSE),
    'allow_custom' => (bool) ($lb['allow_custom'] ?? FALSE),
    'classification' => $class,
    'reason' => $why,
    'file' => basename($file),
  ];
}

usort($rows, static fn (array $a, array $b): int => [$a['classification'], $a['entity'], $a['bundle'], $a['mode']] <=> [$b['classification'], $b['entity'], $b['bundle'], $b['mode']]);

$summary = [
  'total' => count($rows),
  'layout_builder_enabled' => count(array_filter($rows, static fn (array $r): bool => $r['layout_builder_enabled'])),
  'by_classification' => array_count_values(array_column($rows, 'classification')),
  'rows' => $rows,
];

if ($json) {
  echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
  exit(0);
}

echo "ACULTA420 V2 — planejamento de Layout Builder (ADR-054 P4.4)\n";
echo "Displays: {$summary['total']} | Já habilitados: {$summary['layout_builder_enabled']}\n\n";
foreach ($summary['by_classification'] as $class => $count) {
  echo sprintf("  %-28s %d\n", $class, $count);
}
echo "\nLote inicial proposto (COMPATÍVEL, allow_custom=false):\n";
foreach ($rows as $row) {
  if ($row['classification'] === 'COMPATÍVEL') {
    echo "  - {$row['entity']}.{$row['bundle']}.{$row['mode']} ({$row['file']})\n";
  }
}
echo "\nNenhuma configuração foi alterada. Habilitação requer ambiente isolado,\n";
echo "teste de renderização e rollback por display.\n";
