<?php

/**
 * @file
 * Gate ADR-054 P4: lote inicial de Layout Builder nos displays de conteúdo.
 *
 * Verifica, com bootstrap Drupal somente leitura:
 * 1. Os 22 displays elegíveis do lote têm Layout Builder habilitado.
 * 2. `allow_custom` é FALSE (sem override por entidade).
 * 3. Cada display tem seções configuradas e o conteúdo migrado continua
 *    renderizando um nó publicado do bundle.
 * 4. A rota de defaults do Layout Builder autoriza administrador e nega
 *    visitante.
 * 5. A configuração ativa é idêntica à do Config Sync para esses displays.
 *
 * Uso:
 *   php vendor/drush/drush/drush.php php:script \
 *     scripts/validate-aculta420-layout-builder-batch.php
 * ou apenas `php scripts/validate-aculta420-layout-builder-batch.php`.
 */

declare(strict_types=1);

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\layout_builder\Section;

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
  'block_content.aculta_editorial.default',
  'block_content.aculta_institution.contact',
  'block_content.aculta_institution.default',
  'block_content.aculta_institution.footer',
  'block_content.aculta_institution.full',
  'block_content.aculta_institution.home',
  'block_content.aculta_institution.institutional',
  'block_content.aculta_institution.registration',
  'node.activity.default',
  'node.activity.full',
  'node.aculta_adm.default',
  'node.document.default',
  'node.page.default',
  'taxonomy_term.editorial_author.default',
  'node.wiki_entry.default',
  'node.editorial_highlight.default',
  'node.revista.default',
  'node.aculta_notice.default',
];

$wikiComponent = 'node.wiki_entry.full';

$displayStorage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
$syncStorage = \Drupal::service('config.storage.sync');
$renderer = \Drupal::service('renderer');

foreach ($targets as $id) {
  [$entity, $bundle, $mode] = explode('.', $id);
  $display = $displayStorage->load($id);
  $assert($display !== NULL, "$id não existe.");
  if ($display === NULL) {
    continue;
  }

  $assert($display->isLayoutBuilderEnabled(), "$id não tem Layout Builder habilitado.");
  // Landing pages precisam de override por nó; os demais displays não.
  $expectedAllowCustom = $id === 'node.page.full';
  $assert($display->getThirdPartySetting('layout_builder', 'allow_custom', FALSE) === $expectedAllowCustom, "$id: allow_custom diferente do esperado (" . var_export($expectedAllowCustom, TRUE) . ').');

  $sections = $display->getThirdPartySetting('layout_builder', 'sections', []);
  $assert(is_array($sections) && $sections !== [], "$id não tem seções de Layout Builder.");

  $componentCount = 0;
  foreach ($sections as $section) {
    if ($section instanceof Section) {
      $componentCount += count($section->getComponents());
    }
  }
  $assert($componentCount > 0, "$id não tem componentes nas seções.");

  // Configuração ativa idêntica ao Config Sync.
  $configName = 'core.entity_view_display.' . $id;
  $active = \Drupal::config($configName)->getRawData();
  $sync = $syncStorage->read($configName);
  $assert($sync !== FALSE && $active == $sync, "$id diverge do Config Sync.");

  // Cada display elegível compõe o layout SDC existente pelo UI Patterns.
  $rawSections = $active['third_party_settings']['layout_builder']['sections'] ?? [];
  $assert($rawSections !== [], "$id não tem configuração de seção legível.");
  foreach ($rawSections as $delta => $rawSection) {
    $layoutSettings = $rawSection['layout_settings'] ?? [];
    $patternSettings = $layoutSettings['ui_patterns'] ?? [];
    $expectedLayout = $id === $wikiComponent
      ? 'ui_patterns:aculta420_v2:wiki_entry'
      : 'ui_patterns:aculta420_v2:layout_section';
    $expectedComponent = $id === $wikiComponent
      ? 'aculta420_v2:wiki-entry'
      : 'aculta420_v2:layout-section';
    $assert(($rawSection['layout_id'] ?? NULL) === $expectedLayout, "$id seção $delta não usa o ComponentLayout UI Patterns esperado.");
    $assert(($patternSettings['component_id'] ?? NULL) === $expectedComponent, "$id seção $delta não referencia o SDC esperado.");
    if ($id !== $wikiComponent) {
      $assert(
        ($patternSettings['props'] ?? NULL) === [] && ($patternSettings['slots'] ?? NULL) === [],
        "$id seção $delta contém props/slots inesperados."
      );
    }
    else {
      $assert(isset($patternSettings['props']['url'], $patternSettings['props']['entity_title']), "$id não mapeia URL canônica e título da entidade.");
      $expectedRegions = [
        'field_wiki_summary' => 'summary',
        'body' => 'body',
        'field_wiki_categories' => 'categories',
        'field_wiki_image' => 'image',
        'field_wiki_references' => 'references',
        'field_wiki_related' => 'related',
        'field_aculta_discussion' => 'discussion',
      ];
      $foundFields = [];
      foreach ($rawSection['components'] ?? [] as $component) {
        $plugin = $component['configuration']['id'] ?? '';
        foreach ($expectedRegions as $field => $region) {
          if (str_ends_with($plugin, ':' . $field)) {
            $foundFields[$field] = TRUE;
            $assert(($component['region'] ?? NULL) === $region, "$id: $field fora da região SDC $region.");
          }
        }
      }
      $assert(array_diff_key($expectedRegions, $foundFields) === [], "$id não compõe todos os campos do artigo nos slots SDC.");
    }
  }

  // Renderização real de uma entidade publicada do bundle.
  $entityStorage = \Drupal::entityTypeManager()->getStorage($entity);
  $query = $entityStorage->getQuery()->accessCheck(FALSE)->condition('status', 1)->range(0, 1);
  if ($entityStorage->getEntityType()->hasKey('bundle')) {
    $query->condition($entityStorage->getEntityType()->getKey('bundle'), $bundle);
  }
  $ids = $query->execute();
  if ($ids === []) {
    continue;
  }
  $targetEntity = $entityStorage->load((int) reset($ids));
  if ($targetEntity === NULL) {
    continue;
  }
  try {
    $build = \Drupal::entityTypeManager()->getViewBuilder($entity)->view($targetEntity, $mode);
    $output = (string) $renderer->renderInIsolation($build);
  }
  catch (\Throwable $e) {
    $failures[] = "$id: falha ao renderizar a entidade publicada: " . $e->getMessage();
    $checks++;
    continue;
  }
  $checks++;
  $assert(is_array($build) && $build !== [], "$id: render array vazio.");
  $classes = [];
  preg_match_all('/class="([^"]*)"/', $output, $classMatches);
  foreach ($classMatches[1] as $classList) {
    $classes = [...$classes, ...preg_split('/\s+/', $classList, -1, PREG_SPLIT_NO_EMPTY)];
  }
  if ($id !== $wikiComponent) {
    $assert(
      in_array('layout-section', $classes, TRUE),
      "$id: saída renderizada sem o wrapper SDC layout-section."
    );
  }
  else {
    $assert(in_array('aculta-wiki-article', $classes, TRUE), "$id: saída sem o SDC wiki-entry.");
  }
}

// Acesso às rotas de Layout Builder.
$access = \Drupal::service('access_manager');
$admin = \Drupal::entityTypeManager()->getStorage('user')->load(1);
$anonymous = new AnonymousUserSession();
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
