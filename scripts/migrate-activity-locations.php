<?php

declare(strict_types=1);

/**
 * @file
 * CLI script para inspeção, migração e rollback de endereços legados de Activity para Locais (Fase 2).
 *
 * Uso via Drush:
 *   drush scr scripts/migrate-activity-locations.php -- --inspect
 *   drush scr scripts/migrate-activity-locations.php -- --dry-run
 *   drush scr scripts/migrate-activity-locations.php -- --execute
 *   drush scr scripts/migrate-activity-locations.php -- --rollback
 */

use Drupal\aculta_events\Migration\ActivityLocationMigrator;

if (!class_exists('\Drupal')) {
  $autoloader = require_once dirname(__DIR__) . '/vendor/autoload.php';
  require_once dirname(__DIR__) . '/web/core/includes/bootstrap.inc';
  $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
  $kernel = \Drupal\Core\DrupalKernel::createFromRequest('prod', $autoloader, dirname(__DIR__) . '/web');
  $kernel->boot();
  $kernel->preHandle($request);
}

$args = $GLOBALS['argv'] ?? [];
$mode = '--dry-run';

foreach ($args as $arg) {
  if (in_array($arg, ['--inspect', '--dry-run', '--execute', '--rollback'], TRUE)) {
    $mode = $arg;
  }
}

/** @var ActivityLocationMigrator $migrator */
$migrator = \Drupal::service('aculta_events.activity_location_migrator');

echo "=== ACULTA Events: Migração de Endereços Legados (Activity -> Locais) ===\n";
echo "Modo selecionado: {$mode}\n\n";

if ($mode === '--inspect') {
  $report = $migrator->inspect();
  echo "Total de atividades: {$report['total_activities']}\n";
  echo "Já vinculadas a um Local: {$report['already_linked']}\n";
  echo "Candidatas para migração: {$report['candidates_count']}\n";
  echo "Atividades com dados parciais (sem nome ou CEP): {$report['incomplete_count']}\n";
  echo "Locais únicos potenciais após deduplicação: {$report['unique_potential_locations']}\n";

  if (!empty($report['incomplete_list'])) {
    echo "\nDetalhe de registros incompletos:\n";
    foreach ($report['incomplete_list'] as $item) {
      echo " - [NID {$item['nid']}] {$item['title']} (Campos ausentes: " . implode(', ', $item['missing']) . ")\n";
    }
  }
  return;
}

if ($mode === '--rollback') {
  echo "Iniciando rollback da migração de locais de Activity...\n";
  $res = $migrator->rollback();
  echo "Nós de Activity desvinculados: {$res['nodes_unlinked']}\n";
  echo "Entidades EventLocation removidas: {$res['locations_deleted']}\n";
  echo "Rollback concluído com sucesso.\n";
  return;
}

$dryRun = ($mode !== '--execute');
if ($dryRun) {
  echo "[AVISO] Executando em modo DRY-RUN (simulação). Nenhuma alteração será salva no banco.\n\n";
}
else {
  echo "[AVISO] Executando em modo EXECUTE (persistente). Alterações serão salvas no banco.\n\n";
}

$res = $migrator->migrate($dryRun);

echo "Total de candidatos avaliados: {$res['candidates_total']}\n";
echo "Locais a criar / criados: {$res['locations_created']}\n";
echo "Locais reaproveitados (deduplicados): {$res['locations_reused']}\n";
echo "Nós de Activity atualizados: {$res['nodes_updated']}\n";

if (!$dryRun && !empty($res['created_location_ids'])) {
  echo "IDs dos locais criados: " . implode(', ', $res['created_location_ids']) . "\n";
}

echo "\nOperação finalizada com sucesso.\n";
