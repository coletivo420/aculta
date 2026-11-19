<?php

/**
 * @file
 * Gate ADR-054/056: shell único e integridade das regiões.
 *
 * Verifica, sem bootstrap Drupal:
 * 1. As cinco regiões finais existem no info.yml.
 * 2. Nenhum bloco do Config Sync aponta para região inexistente (órfão).
 * 3. O template único compõe as cinco regiões e os SDCs do shell.
 * 4. O landmark main existe uma vez no SDC layout-page.
 * 5. Não há configuração ou biblioteca para um shell paralelo.
 * 6. A composição não compara hostname.
 *
 * Uso: php scripts/validate-aculta420-shell-regions.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420_v2';
$failures = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$condition) {
    $failures[] = $message;
  }
};

$info = Yaml::parseFile($theme . '/aculta420_v2.info.yml');
$regions = array_keys($info['regions'] ?? []);
$finalRegions = ['institution', 'header', 'content', 'sidebar_first', 'footer'];

foreach ($finalRegions as $region) {
  $assert(in_array($region, $regions, TRUE), "Região final ausente no info.yml: $region");
}

// Nenhum bloco do tema V2 pode apontar para região inexistente.
// Blocos de outros temas (Claro/Gin) usam as regiões do próprio tema.
$orphans = [];
foreach (glob($root . '/config/sync/block.block.aculta420_v2_*.yml') ?: [] as $file) {
  $data = Yaml::parseFile($file);
  if (($data['theme'] ?? 'aculta420_v2') !== 'aculta420_v2') {
    continue;
  }
  $region = $data['region'] ?? '';
  if ($region === '') {
    continue;
  }
  if (!in_array($region, $regions, TRUE)) {
    $orphans[] = basename($file, '.yml') . " -> $region";
  }
}
$assert($orphans === [], 'Blocos apontando para região inexistente: ' . implode(' | ', $orphans));

$templatePath = $theme . '/templates/layout/page.html.twig';
$assert(is_file($templatePath), 'Template único templates/layout/page.html.twig ausente.');
$template = is_file($templatePath) ? (string) file_get_contents($templatePath) : '';
$layoutPath = $theme . '/components/layout-page/layout-page.twig';
$assert(is_file($layoutPath), 'O SDC layout-page do shell não existe.');
$layout = is_file($layoutPath) ? (string) file_get_contents($layoutPath) : '';

foreach ([
  'page.institution' => 'área institution',
  'page.header' => 'área header',
  'page.content' => 'área content',
  'page.sidebar_first' => 'área sidebar_first',
  'page.footer' => 'área footer',
  "aculta420_v2:layout-page" => 'SDC layout-page',
  "aculta420_v2:site-footer" => 'SDC site-footer',
] as $needle => $label) {
  $assert(str_contains($template, $needle), "Shell único não compõe $label.");
}

$assert(substr_count($layout, '<main') === 1, 'O SDC layout-page deve ter exatamente um landmark main.');
$assert(!str_contains($template, '<main'), 'O template de página não deve duplicar o landmark main do SDC.');
$assert(preg_match('/\b(hostname|server_name|HTTP_HOST|gethostname)\b/i', $template . $layout) !== 1, 'A composição do shell não pode comparar hostname.');

$settingsSource = (string) file_get_contents($theme . '/theme-settings.php');
$install = Yaml::parseFile($theme . '/config/install/aculta420_v2.settings.yml');
$schema = Yaml::parseFile($theme . '/config/schema/aculta420_v2.schema.yml');
$libraries = Yaml::parseFile($theme . '/aculta420_v2.libraries.yml');
$assert(!is_file($theme . '/templates/layout/page--shell-v2.html.twig'), 'O template paralelo do shell continua presente.');
$assert(!is_file($theme . '/src/styles/shell-v2.css'), 'O CSS do shell paralelo continua presente.');
$assert(!isset($libraries['shell-v2']), 'A biblioteca shell-v2 continua declarada.');
$assert(!str_contains($settingsSource, 'aculta_shell_variant'), 'O formulário ainda oferece variantes de shell.');
$assert(!array_key_exists('aculta_shell_variant', $install), 'A configuração de instalação ainda declara variante de shell.');
$assert(!array_key_exists('aculta_shell_variant', $schema), 'O schema ainda declara variante de shell.');

if ($failures !== []) {
  fwrite(STDERR, "FAIL — validate-aculta420-shell-regions\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  - $failure\n");
  }
  exit(1);
}

echo "PASS — validate-aculta420-shell-regions ($checks checks)\n";
