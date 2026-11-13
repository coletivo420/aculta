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
$assert(!in_array('src/styles/studio-identity.css', $studio, TRUE), 'studio-identity.css foi removido: a biblioteca studio não deve declará-lo.');
$assert(!is_file($theme . '/src/styles/studio-identity.css'), 'studio-identity.css reapareceu sem consumidor que aplique html.studio-identity.');
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
$articleAuthorsCss = (string) file_get_contents($theme . '/components/article-authors/article-authors.css');
$articleAuthorsComponent = (string) file_get_contents($theme . '/components/article-authors/article-authors.component.yml');
$assert(!in_array('src/styles/public-profile.css', $global, TRUE), 'public-profile.css voltou ao global-styling.');
$assert(in_array('src/styles/public-profile.css', $publicProfile, TRUE), 'A biblioteca public-profile não declara public-profile.css.');
$assert(!in_array('src/styles/article-authors.css', $global, TRUE), 'article-authors.css voltou ao global-styling.');
$assert(!is_file($theme . '/src/styles/article-authors.css'), 'A folha antiga de autoria permaneceu fora do SDC.');
$assert(!str_contains($publicProfileCss, '.portal-article-author'), 'public-profile.css ainda contém regras de autoria de artigo.');
$assert(str_contains($articleAuthorsCss, '.portal-article-author'), 'O SDC article-authors perdeu as regras de autoria de artigo.');
$assert(str_contains($articleAuthorsComponent, 'article-authors.css'), 'O SDC article-authors não declara seu CSS local.');
$assert(str_contains($hook, 'aculta420_v2/public-profile'), 'O hook não anexa a biblioteca public-profile.');
$assert(str_contains($hook, "'aculta_user.perfil_publico'"), 'O hook não restringe o CSS à rota do perfil público.');

$messengerComponent = (string) file_get_contents($theme . '/components/messenger-trigger/messenger-trigger.component.yml');
$messengerCss = (string) file_get_contents($theme . '/components/messenger-trigger/messenger-trigger.css');
$portalExtrasCss = (string) file_get_contents($theme . '/src/styles/portal-ui-extras.css');
$messengerBar = (string) file_get_contents($theme . '/components/multidomain-bar/multidomain-bar.twig');
$assert(!in_array('src/styles/messenger-drawer.css', $global, TRUE), 'messenger-drawer.css voltou ao global-styling.');
$assert(!is_file($theme . '/src/styles/messenger-drawer.css'), 'A folha antiga da gaveta permaneceu fora do SDC.');
$assert(str_contains($messengerComponent, 'messenger-trigger.css'), 'O SDC messenger-trigger não declara o CSS da gaveta.');
$assert(str_contains($messengerBar, "{% if messages_url %}"), 'A barra institucional inclui mensagens sem URL/integração disponível.');
$assert(str_contains($messengerBar, "include('aculta420_v2:messenger-trigger'"), 'A barra não compõe a gaveta pelo SDC messenger-trigger.');
$assert(str_contains($messengerCss, '.aculta-messenger-notice-origin'), 'O SDC messenger-trigger perdeu os estilos dos grupos de avisos.');
$assert(!str_contains($portalExtrasCss, '.aculta-messenger-notice-origin'), 'Estilos de avisos do Messenger voltaram ao portal-ui-extras global.');

$relatedContentCss = (string) file_get_contents($theme . '/components/related-content/related-content.css');
$relatedContentComponent = (string) file_get_contents($theme . '/components/related-content/related-content.component.yml');
$contentCss = (string) file_get_contents($theme . '/src/styles/content.css');
$relatedContentBlock = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php');
$assert(!str_contains($contentCss, '.aculta-related-content'), 'Os seletores do bloco relacionado voltaram ao content.css global.');
$assert(str_contains($relatedContentCss, '.aculta-related-content__item'), 'O SDC related-content perdeu o CSS dos itens.');
$assert(str_contains($relatedContentComponent, 'related-content.css'), 'O SDC related-content não declara sua folha local.');
$assert(str_contains($relatedContentBlock, 'aculta420_v2:related-content'), 'O bloco RelatedContent não compõe o SDC de apresentação.');

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
// A UI Patterns Library não pode voltar a estilos globais em layout.css.
$assert(!str_contains($layoutCss, '.aculta-component-library-page'), 'layout.css voltou a conter regras exclusivas da UI Patterns Library.');
$assert(!str_contains($layoutCss, '.ui_patterns_component'), 'layout.css voltou a conter regras da UI Patterns Library.');
$assert(str_contains($componentLibraryCss, '.aculta-component-library-page'), 'component-library-mode.css perdeu o escopo da UI Patterns Library.');
$assert(str_contains($componentLibraryCss, '.ui_patterns_component'), 'component-library-mode.css perdeu as regras de cartão da UI Patterns Library.');

// O container do site_key account pertence à biblioteca contextual da conta.
$portalAccountCss = (string) file_get_contents($theme . '/src/styles/portal-account.css');
$assert(!str_contains($layoutCss, 'data-site-key="account"'), 'layout.css voltou a conter o container do site_key account.');
$assert(str_contains($portalAccountCss, 'html[data-site-key="account"]'), 'portal-account.css perdeu o container do site_key account.');

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
