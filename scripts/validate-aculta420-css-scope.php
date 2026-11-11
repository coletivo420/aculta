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

$studio = array_keys($libraries['studio']['css']['theme'] ?? []);
$assert(!in_array('src/styles/studio.css', $global, TRUE), 'studio.css voltou ao global-styling.');
$assert(!in_array('src/styles/studio-identity.css', $global, TRUE), 'studio-identity.css voltou ao global-styling.');
$assert(in_array('src/styles/studio.css', $studio, TRUE), 'A biblioteca studio não declara src/styles/studio.css.');
$assert(in_array('src/styles/studio-identity.css', $studio, TRUE), 'A biblioteca studio não declara src/styles/studio-identity.css.');
$assert(($libraries['studio']['dependencies'] ?? []) === ['aculta420_v2/global-styling'], 'A biblioteca studio deve depender de global-styling.');

$statisticsCss = (string) file_get_contents($theme . '/src/styles/statistics.css');
$portalMapCss = (string) file_get_contents($theme . '/src/styles/portal-ui-map.css');
$assert(str_contains($statisticsCss, '.aculta-statistics__controls'), 'statistics.css perdeu os seletores movidos do mapa.');
$assert(!str_contains($portalMapCss, 'aculta-statistics'), 'portal-ui-map.css ainda contém seletores de estatísticas; o CSS continua global.');

$hook = (string) file_get_contents($theme . '/src/Hook/PagePresentationHooks.php');
$assert(str_contains($hook, 'aculta420_v2/statistics'), 'O hook não anexa a biblioteca statistics.');
$assert(str_contains($hook, "'aculta_statistics.dashboard'") && str_contains($hook, "'aculta_statistics.area'"), 'O hook não restringe o CSS às rotas do painel de estatísticas.');
$assert(str_contains($hook, 'aculta420_v2/studio'), 'O hook não anexa a biblioteca studio.');
$assert(str_contains($hook, "'aculta_studio.'"), 'O hook não restringe o CSS do Studio às rotas aculta_studio.*.');

$publicProfile = array_keys($libraries['public-profile']['css']['theme'] ?? []);
$publicProfileCss = (string) file_get_contents($theme . '/src/styles/public-profile.css');
$articleAuthorsCss = (string) file_get_contents($theme . '/src/styles/article-authors.css');
$assert(!in_array('src/styles/public-profile.css', $global, TRUE), 'public-profile.css voltou ao global-styling.');
$assert(in_array('src/styles/public-profile.css', $publicProfile, TRUE), 'A biblioteca public-profile não declara public-profile.css.');
$assert(in_array('src/styles/article-authors.css', $global, TRUE), 'article-authors.css deve permanecer no global-styling (caixa de autoria em artigos).');
$assert(!str_contains($publicProfileCss, '.portal-article-author'), 'public-profile.css ainda contém regras de autoria de artigo.');
$assert(str_contains($articleAuthorsCss, '.portal-article-author'), 'article-authors.css perdeu as regras de autoria de artigo.');
$assert(str_contains($hook, 'aculta420_v2/public-profile'), 'O hook não anexa a biblioteca public-profile.');
$assert(str_contains($hook, "'aculta_user.perfil_publico'"), 'O hook não restringe o CSS à rota do perfil público.');

$portalAccount = array_keys($libraries['portal-account']['css']['theme'] ?? []);
$assert(!in_array('src/styles/portal-account.css', $global, TRUE), 'portal-account.css voltou ao global-styling.');
$assert(in_array('src/styles/portal-account.css', $portalAccount, TRUE), 'A biblioteca portal-account não declara portal-account.css.');
$surfaceHook = (string) file_get_contents($theme . '/src/Hook/SurfaceSettingsHooks.php');
$assert(str_contains($surfaceHook, 'aculta420_v2/portal-account'), 'O preprocess_html não anexa a biblioteca portal-account.');
$assert(str_contains($surfaceHook, "\$siteKey === 'account'"), 'O preprocess_html não restringe o CSS ao site_key account.');

$eventOrganization = array_keys($libraries['event-organization']['css']['theme'] ?? []);
$assert(!in_array('src/styles/event-organization.css', $global, TRUE), 'event-organization.css voltou ao global-styling.');
$assert(in_array('src/styles/event-organization.css', $eventOrganization, TRUE), 'A biblioteca event-organization não declara o arquivo.');
$assert(str_contains($hook, 'aculta420_v2/event-organization'), 'O hook não anexa a biblioteca event-organization.');
$assert(str_contains($hook, "'aculta_events.'"), 'O hook não restringe o CSS às rotas aculta_events.*.');

$wiki = array_keys($libraries['wiki']['css']['theme'] ?? []);
$assert(!in_array('src/styles/wiki.css', $global, TRUE), 'wiki.css voltou ao global-styling.');
$assert(in_array('src/styles/wiki.css', $wiki, TRUE), 'A biblioteca wiki não declara wiki.css.');
$assert(str_contains($surfaceHook, 'aculta420_v2/wiki'), 'O preprocess_html não anexa a biblioteca wiki.');
$assert(str_contains($surfaceHook, "\$siteKey === 'wiki'"), 'O preprocess_html não restringe o CSS ao site_key wiki.');

$forum = array_keys($libraries['forum']['css']['theme'] ?? []);
$assert(!in_array('src/styles/forum.css', $global, TRUE), 'forum.css voltou ao global-styling.');
$assert(in_array('src/styles/forum.css', $forum, TRUE), 'A biblioteca forum não declara forum.css.');
$assert(str_contains($hook, 'aculta420_v2/forum'), 'O hook não anexa a biblioteca forum.');
$assert(str_contains($hook, "'view.aculta_forum'"), 'O hook não restringe o CSS às rotas do Fórum.');

$events = array_keys($libraries['events']['css']['theme'] ?? []);
$assert(!in_array('src/styles/events.css', $global, TRUE), 'events.css voltou ao global-styling.');
$assert(in_array('src/styles/events.css', $events, TRUE), 'A biblioteca events não declara events.css.');
$assert(str_contains($hook, 'aculta420_v2/events'), 'O hook não anexa a biblioteca events.');

$layoutCss = (string) file_get_contents($theme . '/src/styles/layout.css');
$componentLibraryCss = (string) file_get_contents($theme . '/src/styles/component-library-mode.css');
$assert(!str_contains($layoutCss, '.aculta-studio'), 'layout.css ainda contém regras do Studio; elas pertencem ao CSS contextual.');
$assert(str_contains($componentLibraryCss, '.aculta-studio-topbar'), 'Regras do Studio na biblioteca de componentes não foram movidas para component-library-mode.css.');

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
