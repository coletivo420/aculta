<?php

/**
 * @file
 * Gate: the Revista has one canonical Node bundle and complete displays.
 *
 * Only reads Config Sync. It does not import configuration or query Runtime.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$config = $root . '/config/sync';
$errors = [];

$node_types = glob($config . '/node.type.*.yml') ?: [];
foreach (['article', 'blog'] as $retired_bundle) {
  if (is_file($config . '/node.type.' . $retired_bundle . '.yml')) {
    $errors[] = "bundle aposentado '$retired_bundle' ainda existe no Config Sync";
  }
}
if (!is_file($config . '/node.type.revista.yml')) {
  $errors[] = 'bundle canônico node.type.revista.yml ausente';
}

foreach (['default', 'teaser', 'full'] as $mode) {
  $display = $config . "/core.entity_view_display.node.revista.$mode.yml";
  if (!is_file($display)) {
    $errors[] = "display Revista '$mode' ausente";
    continue;
  }
  $display_data = Yaml::parseFile($display);
  if (($display_data['targetEntityType'] ?? NULL) !== 'node'
    || ($display_data['bundle'] ?? NULL) !== 'revista'
    || ($display_data['mode'] ?? NULL) !== $mode) {
    $errors[] = "display Revista '$mode' aponta para entidade, bundle ou modo incorreto";
  }
}

$form = $config . '/core.entity_form_display.node.revista.default.yml';
if (!is_file($form)) {
  $errors[] = 'form display Revista ausente';
}
else {
  $form_data = Yaml::parseFile($form);
  if (($form_data['targetEntityType'] ?? NULL) !== 'node' || ($form_data['bundle'] ?? NULL) !== 'revista') {
    $errors[] = 'form display aponta para entidade ou bundle incorreto';
  }
}

if ($errors !== []) {
  echo "REVISTA BUNDLE/DISPLAYS: FAIL\n";
  foreach ($errors as $error) {
    echo " - $error\n";
  }
  exit(1);
}

echo 'REVISTA BUNDLE/DISPLAYS: PASS (um bundle; 3 displays e formulário presentes; '
  . count($node_types) . " tipos Node no Config Sync)\n";
