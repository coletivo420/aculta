<?php

/**
 * @file
 * Gate ADR-054 P7: escopo de carregamento de CSS.
 *
 * Verifica que CSS de aplicação não voltou ao `global-styling` sem decisão e
 * que as bibliotecas contextuais declaram o arquivo certo. Neste pacote cobre
 * `statistics.css`, carregado apenas nas rotas `aculta_statistics.*`.
 *
 * Uso: php scripts/validate-aculta420-css-scope.php
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

$libraries = Yaml::parseFile($theme . '/aculta420_v2.libraries.yml');
$global = array_keys($libraries['global-styling']['css']['theme'] ?? []);
$statistics = array_keys($libraries['statistics']['css']['theme'] ?? []);

$assert(!in_array('src/styles/statistics.css', $global, TRUE), 'statistics.css voltou ao global-styling.');
$assert(in_array('src/styles/statistics.css', $statistics, TRUE), 'A biblioteca statistics não declara src/styles/statistics.css.');
$assert(is_file($theme . '/src/styles/statistics.css'), 'O arquivo statistics.css não existe.');
$assert(($libraries['statistics']['dependencies'] ?? []) === ['aculta420_v2/global-styling'], 'A biblioteca statistics deve depender de global-styling.');

$statisticsCss = (string) file_get_contents($theme . '/src/styles/statistics.css');
$portalMapCss = (string) file_get_contents($theme . '/src/styles/portal-ui-map.css');
$assert(str_contains($statisticsCss, '.aculta-statistics__controls'), 'statistics.css perdeu os seletores movidos do mapa.');
$assert(!str_contains($portalMapCss, 'aculta-statistics'), 'portal-ui-map.css ainda contém seletores de estatísticas; o CSS continua global.');

$hook = (string) file_get_contents($theme . '/src/Hook/PagePresentationHooks.php');
$assert(str_contains($hook, 'aculta420_v2/statistics'), 'O hook não anexa a biblioteca statistics.');
$assert(str_contains($hook, "'aculta_statistics.dashboard'") && str_contains($hook, "'aculta_statistics.area'"), 'O hook não restringe o CSS às rotas do painel de estatísticas.');

// Evidência: bytes do global que deixaram de ser baixados em toda página.
$globalBytes = 0;
foreach ($global as $asset) {
  if (str_starts_with($asset, 'src/') || str_starts_with($asset, 'components/')) {
    $path = $theme . '/' . $asset;
    if (is_file($path)) {
      $globalBytes += filesize($path);
    }
  }
}
$statisticsBytes = is_file($theme . '/src/styles/statistics.css') ? filesize($theme . '/src/styles/statistics.css') : 0;

if ($failures !== []) {
  fwrite(STDERR, "FAIL — validate-aculta420-css-scope\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  - $failure\n");
  }
  exit(1);
}

echo "PASS — validate-aculta420-css-scope ($checks checks)\n";
echo 'global-styling: ' . count($global) . " entradas, $globalBytes bytes de CSS local\n";
echo "statistics: $statisticsBytes bytes fora do carregamento global\n";
