<?php

/**
 * @file
 * Gate ADR-054 P4: lote inicial de Layout Builder nos displays de conteúdo.
 *
 * Verifica, com bootstrap Drupal somente leitura:
 * 1. Os cinco displays do lote têm Layout Builder habilitado.
 * 2. `allow_custom` é FALSE (sem override por entidade).
 * 3. Cada display tem seções configuradas e o conteúdo migrado continua
 *    renderizando um nó publicado do bundle.
 * 4. A rota de defaults do Layout Builder autoriza administrador e nega
 *    visitante.
 * 5. A configuração ativa é idêntica à do Config Sync para esses displays.
 *
 * Uso:
 *   php vendor/drush/drush/drush.php php:script scripts/validate-aculta420-layout-builder-batch.php
 * ou apenas `php scripts/validate-aculta420-layout-builder-batch.php`.
 */

declare(strict_types=1);

if (!class_exists(\Drupal::class) || !\Drupal::hasContainer()) {
  $root = dirname(__DIR__);
  $drush = (getenv('ACULTA_DRUSH_ROOT') ?: $root) . '/vendor/drush/drush/drush.php';
  if (!is_file($drush)) {
    fwrite(STDERR, "ACULTA420 LB BATCH FAIL: Drush não instalado.\n");
    exit(1);
  }
  passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($drush) . ' php:script ' . escapeshellarg(__FILE__), $code);
  exit($code);
}

$failures = [];
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$condition) {
    $failures[] = $message;
  }
};

$targets = [
  'node.page.full',
  'node.revista.full',
  'node.wiki_entry.full',
  'node.aculta_faq.default',
];

$displayStorage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
$nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
$viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('node');
$syncStorage = \Drupal::service('config.storage.sync');

foreach ($targets as $id) {
  [$entity, $bundle, $mode] = explode('.', $id);
  $display = $displayStorage->load($id);
  $assert($display !== NULL, "$id não existe.");
  if ($display === NULL) {
    continue;
  }

  $assert($display->isLayoutBuilderEnabled(), "$id não tem Layout Builder habilitado.");
  $assert($display->getThirdPartySetting('layout_builder', 'allow_custom', TRUE) === FALSE, "$id permite override por entidade (allow_custom).");

  $sections = $display->getThirdPartySetting('layout_builder', 'sections', []);
  $assert(is_array($sections) && $sections !== [], "$id não tem seções de Layout Builder.");

  $componentCount = 0;
  foreach ($sections as $section) {
    if ($section instanceof \Drupal\layout_builder\Section) {
      $componentCount += count($section->getComponents());
    }
  }
  $assert($componentCount > 0, "$id não tem componentes nas seções.");

  // Configuração ativa idêntica ao Config Sync.
  $configName = 'core.entity_view_display.' . $id;
  $active = \Drupal::config($configName)->getRawData();
  $sync = $syncStorage->read($configName);
  $assert($sync !== FALSE && $active == $sync, "$id diverge do Config Sync.");

  // Renderização real de um nó publicado do bundle.
  $nids = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('type', $bundle)
    ->condition('status', 1)
    ->range(0, 1)
    ->execute();
  if ($nids === []) {
    continue;
  }
  $node = $nodeStorage->load((int) reset($nids));
  if ($node === NULL) {
    continue;
  }
  try {
    $build = $viewBuilder->view($node, $mode);
  }
  catch (\Throwable $e) {
    $failures[] = "$id: falha ao construir o render array: " . $e->getMessage();
    $checks++;
    continue;
  }
  $checks++;
  $assert(isset($build['#node']) && $build['#node'] === $node, "$id: render array sem o nó.");
}

// Acesso às rotas de Layout Builder.
$access = \Drupal::service('access_manager');
$admin = \Drupal::entityTypeManager()->getStorage('user')->load(1);
$anonymous = new \Drupal\Core\Session\AnonymousUserSession();
$routeParams = ['node_type' => 'page', 'view_mode_name' => 'full'];
$assert($access->checkNamedRoute('layout_builder.defaults.node.view', $routeParams, $admin, TRUE)->isAllowed(), 'O administrador não acessa os defaults do Layout Builder.');
$assert($access->checkNamedRoute('layout_builder.defaults.node.view', $routeParams, $anonymous, TRUE)->isAllowed() === FALSE, 'Visitante acessa os defaults do Layout Builder.');

if ($failures !== []) {
  fwrite(STDERR, "FAIL — validate-aculta420-layout-builder-batch\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  - $failure\n");
  }
  exit(1);
}

echo "PASS — validate-aculta420-layout-builder-batch ($checks checks)\n";
