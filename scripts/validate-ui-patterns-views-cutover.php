<?php

/**
 * @file
 * Checks that the Albums View uses UI Patterns row/style/display safely.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$view = Yaml::parseFile($root . '/config/sync/views.view.aculta_albuns.yml');
$component = Yaml::parseFile($root . '/web/themes/custom/aculta420_v2/components/views-display/views-display.component.yml');
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
  $checks++;
  if (!$condition) {
    fwrite(STDERR, "FAIL — $message\n");
    exit(1);
  }
};

$default = $view['display']['default']['display_options'];
$page = $view['display']['page_1']['display_options'];
$assert(in_array('ui_patterns_views', $view['dependencies']['module'] ?? [], TRUE), 'A dependência ui_patterns_views deve ser declarada.');
$assert(($default['style']['type'] ?? NULL) === 'ui_patterns', 'A View deve usar o plugin UI Patterns Style.');
$assert(($default['style']['options']['ui_patterns']['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:layout-grid', 'Style deve reutilizar layout-grid.');
$assert(($default['style']['options']['ui_patterns']['ui_patterns']['slots']['content']['sources'][0]['source_id'] ?? NULL) === 'view_rows', 'Style deve receber as linhas renderizadas da View.');
$assert(($default['row']['type'] ?? NULL) === 'ui_patterns', 'Row deve usar o plugin UI Patterns.');
$assert(($default['row']['options']['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:revista-album-card', 'Row deve reutilizar o SDC próprio para álbuns.');
$rowSlots = $default['row']['options']['ui_patterns']['slots'] ?? [];
$rowFields = [
  'cover' => 'field_gallery_cover',
  'title' => 'title',
  'published_at' => 'field_published_at',
  'credits' => 'field_credits',
];
foreach ($rowFields as $slot => $field) {
  $assert(($rowSlots[$slot]['sources'][0]['source']['ui_patterns_views_field'] ?? NULL) === $field, "$slot deve usar o campo de View $field.");
}
$assert(isset($default['fields']['field_gallery_cover'], $default['fields']['title'], $default['fields']['field_published_at'], $default['fields']['field_credits']), 'Os handlers originais permanecem disponíveis como sources com acesso/cache da View.');
$assert(($default['pager']['type'] ?? NULL) === 'mini' && ($default['pager']['options']['items_per_page'] ?? NULL) === 12, 'Pager nativo e limite de 12 itens devem ser preservados.');
$assert(($default['access']['type'] ?? NULL) === 'perm' && ($default['access']['options']['perm'] ?? NULL) === 'access content', 'Controle de acesso deve permanecer no plugin nativo.');
$assert(isset($default['filters']['status'], $default['sorts']['field_published_at_value']), 'Filtro de publicação e ordenação editorial devem permanecer.');
$assert(($page['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:views-display', 'Display deve reutilizar o SDC views-display no options da View.');
$assert(($page['defaults']['ui_patterns'] ?? NULL) === FALSE, 'O display deve selecionar explicitamente seu próprio componente em vez de herdar o valor vazio do default.');

$displaySlots = $page['ui_patterns']['slots'] ?? [];
$requiredSources = [
  'attachment_before' => 'view_attachment_before',
  'header' => 'view_header',
  'exposed' => 'view_exposed',
  'rows' => 'view_rows',
  'empty' => 'view_empty',
  'pager' => 'view_pager',
  'more' => 'view_more',
  'footer' => 'view_footer',
  'attachment_after' => 'view_attachment_after',
];
foreach ($requiredSources as $slot => $source) {
  $assert(($displaySlots[$slot]['sources'][0]['source_id'] ?? NULL) === $source, "$slot deve ser composto pelo source $source.");
}
$assert(($page['ui_patterns']['props']['title']['source_id'] ?? NULL) === 'view_title', 'O título deve vir do source nativo View Title.');

$rowComponent = Yaml::parseFile($root . '/web/themes/custom/aculta420_v2/components/revista-album-card/revista-album-card.component.yml');
foreach (['cover', 'title', 'published_at', 'credits'] as $slot) {
  $assert(isset($rowComponent['slots'][$slot]), "O SDC revista-album-card precisa expor o slot $slot.");
}

$forumView = Yaml::parseFile($root . '/config/sync/views.view.aculta_forum_categories.yml');
$forumPage = $forumView['display']['page_categories']['display_options'];
$forumDefault = $forumView['display']['default']['display_options'];
$forumStyle = $forumPage['style'] ?? [];
$assert(($forumPage['defaults']['ui_patterns'] ?? NULL) === FALSE, 'Categorias deve selecionar explicitamente o componente de display sem herdar config vazia.');
$assert(($forumPage['row']['type'] ?? NULL) === 'ui_patterns', 'A View de categorias deve manter UI Patterns no nível row.');
$assert(($forumPage['row']['options']['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:forum-category', 'A View de categorias deve reutilizar forum-category.');
$assert(($forumStyle['type'] ?? NULL) === 'default', 'O estilo nativo de categorias deve permanecer para preservar agrupamento.');
$assert(isset($forumStyle['options']['grouping'][0]), 'O agrupamento nativo por categoria principal deve permanecer.');
$assert(($forumDefault['pager']['type'] ?? NULL) === 'some', 'O pager nativo herdado pela página de categorias deve permanecer.');
$assert(($forumDefault['access']['type'] ?? NULL) === 'perm' && ($forumDefault['access']['options']['perm'] ?? NULL) === 'access content', 'A página de categorias deve manter controle de acesso nativo herdado.');
$assert(isset($forumDefault['filters']['vid'], $forumDefault['sorts']['weight']), 'Vocabulário e ordenação editorial herdados da View de categorias devem permanecer.');
$assert(($forumPage['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:views-display', 'O display de categorias deve reutilizar o SDC views-display.');
$assert(!isset($forumPage['ui_patterns']['props']['title']), 'A página de categorias não deve duplicar seu título visual nativo.');
$forumDisplaySlots = $forumPage['ui_patterns']['slots'] ?? [];
foreach ($requiredSources as $slot => $source) {
  $assert(($forumDisplaySlots[$slot]['sources'][0]['source_id'] ?? NULL) === $source, "Categorias/$slot deve usar a fonte $source.");
}
$assert(($forumPage['header']['area_text_custom']['content'] ?? NULL) === '<p><a href="/">Discussões recentes</a></p>', 'O link Discussões recentes deve permanecer no header nativo da View.');

$slots = $component['slots'] ?? [];
foreach (['attachment_before', 'header', 'exposed', 'rows', 'empty', 'pager', 'more', 'footer', 'attachment_after'] as $slot) {
  $assert(isset($slots[$slot]), "O SDC views-display precisa expor o slot $slot.");
}
$assert(($component['props']['additionalProperties'] ?? NULL) === FALSE, 'O schema do SDC deve rejeitar props desconhecidas.');

fwrite(STDOUT, sprintf("PASS — validate-ui-patterns-views-cutover (%d checks)\n", $checks));
