<?php

/**
 * @file
 * Gate ADR-054: layouts SDC e descoberta pelo Layout Builder.
 *
 * Verifica, com bootstrap Drupal somente leitura:
 * 1. Os layouts do tema existem como plugin de layout derivado do
 *    `ui_patterns_layouts` (mesmo registro usado pelo Layout Builder).
 * 2. Categoria e regiões correspondem aos slots declarados no SDC.
 * 3. Cada layout renderiza pelo renderer do Drupal, com as classes e o
 *    conteúdo esperados, sem depender de contexto de entidade.
 *
 * Uso:
 *   php vendor/drush/drush/drush.php php:script scripts/validate-aculta420-layouts.php
 * ou apenas `php scripts/validate-aculta420-layouts.php` (delega ao Drush).
 */

declare(strict_types=1);

// Composer pode carregar Drupal sem bootstrap; nesse caso, delega ao Drush.
if (!class_exists(\Drupal::class) || !\Drupal::hasContainer()) {
  $root = dirname(__DIR__);
  $drush = (getenv('ACULTA_DRUSH_ROOT') ?: $root) . '/vendor/drush/drush/drush.php';
  if (!is_file($drush)) {
    fwrite(STDERR, "ACULTA420 LAYOUTS FAIL: Drush não instalado (vendor/drush/drush/drush.php ausente).\n");
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

$layoutManager = \Drupal::service('plugin.manager.core.layout');
$renderer = \Drupal::service('renderer');

$expected = [
  'ui_patterns:aculta420_v2:layout_page' => [
    'regions' => ['title', 'content', 'aside'],
    'props' => ['variant' => 'stack'],
    'slots' => [
      'title' => ['#markup' => '<h1>Título</h1>'],
      'content' => ['#markup' => '<p>Corpo</p>'],
      'aside' => ['#markup' => '<p>Lateral</p>'],
    ],
    'class' => 'layout-page',
  ],
  'ui_patterns:aculta420_v2:layout_section' => [
    'regions' => ['header', 'content'],
    'props' => ['tone' => 'raised'],
    'slots' => [
      'header' => ['#markup' => '<h2>Seção</h2>'],
      'content' => ['#markup' => '<p>Conteúdo</p>'],
    ],
    'class' => 'layout-section--raised',
  ],
  'ui_patterns:aculta420_v2:layout_two_columns' => [
    'regions' => ['primary', 'secondary'],
    'props' => ['ratio' => 'wide'],
    'slots' => [
      'primary' => ['#markup' => '<p>Principal</p>'],
      'secondary' => ['#markup' => '<p>Complementar</p>'],
    ],
    'class' => 'layout-two-columns--wide',
  ],
  'ui_patterns:aculta420_v2:layout_grid' => [
    'regions' => ['content'],
    'props' => ['min_item_width' => '20rem'],
    'slots' => [
      'content' => ['#markup' => '<article>Item</article>'],
    ],
    'class' => 'layout-grid--min-20',
  ],
  'ui_patterns:aculta420_v2:layout_narrow' => [
    'regions' => ['content'],
    'props' => [],
    'slots' => ['content' => ['#markup' => '<p>Leitura</p>']],
    'class' => 'layout-narrow',
  ],
  'ui_patterns:aculta420_v2:layout_full' => [
    'regions' => ['content'],
    'props' => [],
    'slots' => ['content' => ['#markup' => '<p>Largo</p>']],
    'class' => 'layout-full',
  ],
];

$definitions = $layoutManager->getDefinitions();

foreach ($expected as $id => $spec) {
  $assert(isset($definitions[$id]), "Layout não descoberto: $id");

  if (isset($definitions[$id])) {
    $definition = $definitions[$id];
    $assert($definition->getProvider() === 'aculta420_v2', "$id: provider deveria ser aculta420_v2.");
    $assert($definition->getCategory() === 'Layouts', "$id: categoria deveria ser Layouts.");
    $regions = array_keys($definition->getRegions());
    $assert($regions === $spec['regions'], "$id: regiões " . implode(',', $regions) . ' diferentes de ' . implode(',', $spec['regions']) . '.');
  }

  // Componente base declarado no SDC.
  $componentId = str_replace('ui_patterns:', '', $id);
  $componentId = str_replace('aculta420_v2:', 'aculta420_v2:', $componentId);
  $machineName = str_replace('aculta420_v2:', '', $componentId);
  $machineName = str_replace('_', '-', $machineName);

  $build = [
    '#type' => 'component',
    '#component' => 'aculta420_v2:' . $machineName,
    '#props' => $spec['props'],
    '#slots' => $spec['slots'],
  ];

  try {
    $html = (string) $renderer->renderInIsolation($build);
  }
  catch (\Throwable $e) {
    $failures[] = "$id: falha ao renderizar: " . $e->getMessage();
    $checks++;
    continue;
  }

  $checks++;
  $assert(str_contains($html, $spec['class']), "$id: saída sem a classe {$spec['class']}.");
  $assert(trim(strip_tags($html)) !== '', "$id: saída vazia.");
}

if ($failures !== []) {
  fwrite(STDERR, "FAIL — validate-aculta420-layouts\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  - $failure\n");
  }
  exit(1);
}

echo "PASS — validate-aculta420-layouts ($checks checks)\n";
