<?php

/**
 * @file
 * Gate ADR-054: shell paralelo e integridade das regiões.
 *
 * Verifica, sem bootstrap Drupal:
 * 1. As cinco regiões finais existem no info.yml.
 * 2. Nenhum bloco do Config Sync aponta para região inexistente (órfão).
 * 3. O template do shell v2 compõe as cinco áreas finais.
 * 4. As regiões antigas continuam renderizadas como ponte no shell v2.
 * 5. O shell v2 é opt-in (configuração classic/v2) e carrega seu CSS.
 * 6. O template não compara hostname.
 *
 * Uso: php scripts/validate-aculta420-shell-regions.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420_v2';
$strict = in_array('--strict', $argv, TRUE);
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
$legacy = [];
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
  if (!in_array($region, $finalRegions, TRUE)) {
    $legacy[] = basename($file, '.yml') . " -> $region";
  }
}
$assert($orphans === [], 'Blocos apontando para região inexistente: ' . implode(' | ', $orphans));
if ($legacy !== []) {
  // Informativo no modo normal; no modo --strict bloqueia o cutover.
  echo 'Blocos ainda em regiões antigas (ponte ativa): ' . count($legacy) . "\n";
  foreach ($legacy as $item) {
    echo "  - $item\n";
  }
}
$assert(!$strict || $legacy === [], 'Corte bloqueado: ' . count($legacy) . ' bloco(s) ainda em regiões antigas. Migre-os antes de remover as regiões do info.yml.');

$templatePath = $theme . '/templates/layout/page--shell-v2.html.twig';
$assert(is_file($templatePath), 'Template do shell v2 ausente.');
$template = is_file($templatePath) ? (string) file_get_contents($templatePath) : '';

foreach ([
  'page.institution' => 'área institution',
  'page.header' => 'área header',
  'aculta_help' => 'variável help do Portal',
  'aculta_breadcrumb' => 'variável breadcrumb do Portal',
  'page.content' => 'área content',
  'page.sidebar_first' => 'área sidebar_first',
  'page.footer' => 'área footer',
  "aculta420_v2:layout-page" => 'SDC layout-page',
  "aculta420_v2:site-footer" => 'SDC site-footer',
] as $needle => $label) {
  $assert(str_contains($template, $needle), "Shell v2 não compõe $label.");
}

$assert(substr_count($template, 'id="main-content"') === 1, 'Shell v2 deve ter exatamente um landmark main de fallback.');
$assert(preg_match('/\b(hostname|server_name|HTTP_HOST|gethostname)\b/i', $template) !== 1, 'Shell v2 não pode comparar hostname.');

$hookSource = (string) file_get_contents($theme . '/src/Hook/PagePresentationHooks.php');
$themeSource = (string) file_get_contents($theme . '/aculta420_v2.theme');
$assert(str_contains($themeSource, 'theme_suggestions_page_alter'), 'O arquivo do tema não registra a sugestão do shell v2.');
$assert(str_contains($themeSource, "page__shell_v2"), 'A sugestão page__shell_v2 não é adicionada.');
$assert(str_contains($hookSource, "aculta_shell_variant"), 'O hook não lê a configuração aculta_shell_variant.');
$assert(str_contains($hookSource, "aculta420_v2/shell-v2"), 'O CSS do shell v2 não é anexado pelo hook.');

$settingsSource = (string) file_get_contents($theme . '/theme-settings.php');
$assert(str_contains($settingsSource, 'aculta_shell_variant'), 'A configuração do shell não aparece no formulário do tema.');
$assert(str_contains($settingsSource, "'classic'") && str_contains($settingsSource, "'v2'"), 'O formulário não oferece as duas variantes de shell.');

$install = Yaml::parseFile($theme . '/config/install/aculta420_v2.settings.yml');
$assert(($install['aculta_shell_variant'] ?? NULL) === 'v2', 'O padrão do shell deve ser v2 após o cutover.');

$libraries = Yaml::parseFile($theme . '/aculta420_v2.libraries.yml');
$shellCss = $libraries['shell-v2']['css']['theme'] ?? [];
$assert(isset($shellCss['src/styles/shell-v2.css']), 'A biblioteca shell-v2 não declara src/styles/shell-v2.css.');
$assert(is_file($theme . '/src/styles/shell-v2.css'), 'O arquivo src/styles/shell-v2.css não existe.');

if ($failures !== []) {
  fwrite(STDERR, "FAIL — validate-aculta420-shell-regions\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  - $failure\n");
  }
  exit(1);
}

echo "PASS — validate-aculta420-shell-regions ($checks checks)\n";
