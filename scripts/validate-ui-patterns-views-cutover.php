<?php

/**
 * @file
 * Checks that Views cutovers use UI Patterns while preserving native behavior.
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

$shop = Yaml::parseFile($root . '/config/sync/views.view.aculta_shop_catalog.yml');
$shopDefault = $shop['display']['default']['display_options'];
$assert(in_array('ui_patterns_views', $shop['dependencies']['module'] ?? [], TRUE), 'O catálogo Commerce deve declarar a dependência UI Patterns Views.');
$assert(($shopDefault['style']['type'] ?? NULL) === 'ui_patterns', 'O catálogo Commerce deve usar UI Patterns Style.');
$assert(($shopDefault['style']['options']['ui_patterns']['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:layout-grid', 'O catálogo Commerce deve reutilizar o layout-grid existente.');
$assert(($shopDefault['style']['options']['ui_patterns']['ui_patterns']['props']['min_item_width']['source']['value'] ?? NULL) === '16rem', 'A grade Commerce deve usar a largura mínima editorial suportada.');
$assert(($shopDefault['style']['options']['ui_patterns']['ui_patterns']['slots']['content']['sources'][0]['source_id'] ?? NULL) === 'view_rows', 'O grid Commerce deve compor as rows renderizadas nativamente.');
$assert(($shopDefault['row']['type'] ?? NULL) === 'entity:commerce_product' && ($shopDefault['row']['options']['view_mode'] ?? NULL) === 'catalog', 'O row nativo Commerce e seu display catalog devem permanecer.');
$assert(($shopDefault['pager']['type'] ?? NULL) === 'full' && ($shopDefault['pager']['options']['items_per_page'] ?? NULL) === 12, 'O pager Commerce deve continuar completo com 12 itens por página.');
$assert(($shopDefault['access']['type'] ?? NULL) === 'perm' && ($shopDefault['access']['options']['perm'] ?? NULL) === 'access content', 'O acesso nativo do catálogo Commerce deve permanecer.');
$assert(isset($shopDefault['filters']['status'], $shopDefault['filters']['type']), 'Status publicado e tipo Produto da loja devem permanecer como filtros.');
$assert(($shopDefault['sorts']['title']['order'] ?? NULL) === 'ASC', 'O catálogo Commerce deve preservar a ordenação ascendente por título.');
foreach (['block_catalog', 'page_catalog'] as $shopDisplay) {
  $options = $shop['display'][$shopDisplay]['display_options'];
  $assert(($options['defaults']['style'] ?? TRUE) === TRUE, "$shopDisplay deve herdar o grid único do catálogo.");
}

$wiki = Yaml::parseFile($root . '/config/sync/views.view.wiki_entries.yml');
$wikiDefault = $wiki['display']['default']['display_options'];
$assert(in_array('ui_patterns_views', $wiki['dependencies']['module'] ?? [], TRUE), 'A Wiki deve declarar UI Patterns Views.');
$assert(($wikiDefault['style']['type'] ?? NULL) === 'ui_patterns', 'A listagem da Wiki deve usar o plugin UI Patterns Style.');
$assert(($wikiDefault['style']['options']['ui_patterns']['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:layout-grid', 'A Wiki deve reutilizar o SDC layout-grid.');
$assert(($wikiDefault['style']['options']['ui_patterns']['ui_patterns']['props']['max_columns']['source']['value'] ?? NULL) === '2', 'A grade da Wiki deve manter o limite de duas colunas via source number do UI Patterns.');
$assert(($wikiDefault['style']['options']['ui_patterns']['ui_patterns']['slots']['content']['sources'][0]['source_id'] ?? NULL) === 'view_rows', 'A grade da Wiki deve compor as rows renderizadas pela View.');
$assert(($wikiDefault['row']['type'] ?? NULL) === 'entity:node' && ($wikiDefault['row']['options']['view_mode'] ?? NULL) === 'teaser', 'A Wiki deve preservar o row de node/teaser e o adapter SDC do tema.');
$assert(($wikiDefault['access']['options']['perm'] ?? NULL) === 'access content', 'A Wiki deve preservar a permissão nativa de leitura.');
$assert(($wikiDefault['pager']['options']['items_per_page'] ?? NULL) === 12, 'O pager da Wiki deve preservar os 12 itens por página.');
$assert(isset($wikiDefault['filters']['status'], $wikiDefault['filters']['type'], $wikiDefault['filters']['field_domain_source_target_id']), 'Publicação, bundle e Domain ativo devem continuar filtrados pela View.');
$assert(($wikiDefault['sorts']['created']['order'] ?? NULL) === 'DESC', 'A Wiki deve preservar a ordem de publicação decrescente.');
foreach (['block_changes', 'block_recent', 'page_1'] as $wikiDisplay) {
  $options = $wiki['display'][$wikiDisplay]['display_options'];
  $assert(($options['defaults']['style'] ?? TRUE) === TRUE, "$wikiDisplay deve herdar a composição SDC única da View.");
}

$wikiCategories = Yaml::parseFile($root . '/config/sync/views.view.wiki_categories.yml');
$wikiCategoriesDefault = $wikiCategories['display']['default']['display_options'];
$assert(in_array('ui_patterns_views', $wikiCategories['dependencies']['module'] ?? [], TRUE), 'As categorias da Wiki devem declarar UI Patterns Views.');
$assert(($wikiCategoriesDefault['style']['type'] ?? NULL) === 'ui_patterns', 'As categorias da Wiki devem usar o plugin UI Patterns Style.');
$assert(($wikiCategoriesDefault['style']['options']['ui_patterns']['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:layout-grid', 'As categorias da Wiki devem reutilizar layout-grid.');
$assert(($wikiCategoriesDefault['style']['options']['ui_patterns']['ui_patterns']['props']['max_columns']['source']['value'] ?? NULL) === '2', 'As categorias da Wiki devem manter duas colunas via source number.');
$assert(($wikiCategoriesDefault['style']['options']['ui_patterns']['ui_patterns']['slots']['content']['sources'][0]['source_id'] ?? NULL) === 'view_rows', 'A grade deve compor rows renderizadas pela View.');
$assert(($wikiCategoriesDefault['row']['type'] ?? NULL) === 'entity:taxonomy_term' && ($wikiCategoriesDefault['row']['options']['view_mode'] ?? NULL) === 'default', 'As categorias devem manter o row nativo de taxonomia.');
$assert(($wikiCategoriesDefault['access']['options']['perm'] ?? NULL) === 'access content', 'As categorias devem preservar a permissão nativa de leitura.');
$assert(($wikiCategoriesDefault['pager']['options']['items_per_page'] ?? NULL) === 30, 'As categorias devem preservar o pager de 30 itens.');
$assert(isset($wikiCategoriesDefault['filters']['vid']) && ($wikiCategoriesDefault['sorts']['weight']['order'] ?? NULL) === 'ASC', 'Vocabulário e ordem editorial devem continuar sob Views.');
$assert(($wikiCategories['display']['block_1']['display_options']['defaults']['style'] ?? TRUE) === TRUE, 'O bloco de categorias deve herdar a grade SDC única.');

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
$forumInstall = Yaml::parseFile($root . '/web/modules/custom/aculta_portal/modules/aculta_forum/config/install/views.view.aculta_forum_categories.yml');
$forumPage = $forumView['display']['page_categories']['display_options'];
$forumInstallPage = $forumInstall['display']['page_categories']['display_options'];
$forumDefault = $forumView['display']['default']['display_options'];
$forumStyle = $forumPage['style'] ?? [];
$assert(($forumPage['defaults']['ui_patterns'] ?? NULL) === FALSE, 'Categorias deve selecionar explicitamente o componente de display sem herdar config vazia.');
$assert(($forumPage['row']['type'] ?? NULL) === 'ui_patterns', 'A View de categorias deve manter UI Patterns no nível row.');
$assert(($forumPage['row']['options']['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:forum-category', 'A View de categorias deve reutilizar forum-category.');
$assert(($forumStyle['type'] ?? NULL) === 'ui_patterns', 'Categorias deve usar o estilo UI Patterns.');
$assert(($forumStyle['options']['ui_patterns']['ui_patterns']['component_id'] ?? NULL) === 'aculta420_v2:layout-grid', 'A listagem de categorias deve compor os cartões pelo SDC layout-grid.');
$assert(($forumStyle['options']['ui_patterns']['ui_patterns']['slots']['content']['sources'][0]['source_id'] ?? NULL) === 'view_rows', 'A grade deve receber as rows já renderizadas por UI Patterns.');
$assert(($forumInstallPage['style'] ?? NULL) === ($forumPage['style'] ?? NULL), 'Config Sync e instalação devem usar o mesmo style UI Patterns para page_categories.');
$assert(($forumInstallPage['row'] ?? NULL) === ($forumPage['row'] ?? NULL), 'Config Sync e instalação devem manter a mesma composição UI Patterns por row.');
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
