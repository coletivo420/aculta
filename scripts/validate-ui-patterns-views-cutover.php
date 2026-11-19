<?php

/**
 * @file
 * Checks that Views cutovers use UI Patterns while preserving native behavior.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);

/**
 * Parses a YAML file into an associative array.
 *
 * @param string $path
 *   The path to the YAML file.
 *
 * @return array<string, mixed>
 *   The parsed YAML mapping.
 */
function cutover_yaml_file(string $path): array {
  $data = Yaml::parseFile($path);
  if (!is_array($data)) {
    fwrite(STDERR, "FAIL — $path is not a valid YAML mapping.\n");
    exit(1);
  }
  /** @var array<string, mixed> $result */
  $result = [];
  foreach ($data as $k => $v) {
    $result[(string) $k] = $v;
  }
  return $result;
}

/**
 * Safely traverses nested array structures without mixed offset warnings.
 *
 * @param mixed $data
 *   The root data structure.
 * @param string ...$keys
 *   The path of keys to traverse.
 *
 * @return mixed
 *   The value if found, or NULL.
 */
function cutover_yaml_get(mixed $data, string ...$keys): mixed {
  $curr = $data;
  foreach ($keys as $k) {
    if (!is_array($curr) || !array_key_exists($k, $curr)) {
      return NULL;
    }
    $curr = $curr[$k];
  }
  return $curr;
}

/**
 * Safely retrieves a nested array or empty array if missing.
 *
 * @param mixed $data
 *   The root data structure.
 * @param string ...$keys
 *   The path of keys to traverse.
 *
 * @return array<string, mixed>
 *   The nested array.
 */
function cutover_yaml_array(mixed $data, string ...$keys): array {
  $val = cutover_yaml_get($data, ...$keys);
  if (!is_array($val)) {
    return [];
  }
  /** @var array<string, mixed> $result */
  $result = [];
  foreach ($val as $k => $v) {
    $result[(string) $k] = $v;
  }
  return $result;
}

$view = cutover_yaml_file($root . '/config/sync/views.view.aculta_albuns.yml');
$component = cutover_yaml_file($root . '/web/themes/custom/aculta420_v2/components/views-display/views-display.component.yml');
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
  $checks++;
  if (!$condition) {
    fwrite(STDERR, "FAIL — $message\n");
    exit(1);
  }
};

$default = cutover_yaml_array($view, 'display', 'default', 'display_options');
$page = cutover_yaml_array($view, 'display', 'page_1', 'display_options');
$viewModules = cutover_yaml_array($view, 'dependencies', 'module');
$assert(in_array('ui_patterns_views', $viewModules, TRUE), 'A dependência ui_patterns_views deve ser declarada.');
$assert(cutover_yaml_get($default, 'style', 'type') === 'ui_patterns', 'A View deve usar o plugin UI Patterns Style.');
$assert(cutover_yaml_get($default, 'style', 'options', 'ui_patterns', 'ui_patterns', 'component_id') === 'aculta420_v2:layout-grid', 'Style deve reutilizar layout-grid.');
$assert(cutover_yaml_get($default, 'style', 'options', 'ui_patterns', 'ui_patterns', 'slots', 'content', 'sources', '0', 'source_id') === 'view_rows', 'Style deve receber as linhas renderizadas da View.');
$assert(cutover_yaml_get($default, 'row', 'type') === 'ui_patterns', 'Row deve usar o plugin UI Patterns.');
$assert(cutover_yaml_get($default, 'row', 'options', 'ui_patterns', 'component_id') === 'aculta420_v2:revista-album-card', 'Row deve reutilizar o SDC próprio para álbuns.');
$rowSlots = cutover_yaml_array($default, 'row', 'options', 'ui_patterns', 'slots');
$rowFields = [
  'cover' => 'field_gallery_cover',
  'title' => 'title',
  'published_at' => 'field_published_at',
  'credits' => 'field_credits',
];
foreach ($rowFields as $slot => $field) {
  $assert(cutover_yaml_get($rowSlots, $slot, 'sources', '0', 'source', 'ui_patterns_views_field') === $field, "$slot deve usar o campo de View $field.");
}
$defaultFields = cutover_yaml_array($default, 'fields');
$assert(isset($defaultFields['field_gallery_cover'], $defaultFields['title'], $defaultFields['field_published_at'], $defaultFields['field_credits']), 'Os handlers originais permanecem disponíveis como sources com acesso/cache da View.');
$assert(cutover_yaml_get($default, 'pager', 'type') === 'mini' && cutover_yaml_get($default, 'pager', 'options', 'items_per_page') === 12, 'Pager nativo e limite de 12 itens devem ser preservados.');
$assert(cutover_yaml_get($default, 'access', 'type') === 'perm' && cutover_yaml_get($default, 'access', 'options', 'perm') === 'access content', 'Controle de acesso deve permanecer no plugin nativo.');
$assert(cutover_yaml_get($default, 'filters', 'status') !== NULL && cutover_yaml_get($default, 'sorts', 'field_published_at_value') !== NULL, 'Filtro de publicação e ordenação editorial devem permanecer.');
$assert(cutover_yaml_get($page, 'ui_patterns', 'component_id') === 'aculta420_v2:views-display', 'Display deve reutilizar o SDC views-display no options da View.');
$assert(cutover_yaml_get($page, 'defaults', 'ui_patterns') === FALSE, 'O display deve selecionar explicitamente seu próprio componente em vez de herdar o valor vazio do default.');

$documents = cutover_yaml_file($root . '/config/sync/views.view.aculta_documents.yml');
$documentsDefault = cutover_yaml_array($documents, 'display', 'default', 'display_options');
$assert(cutover_yaml_get($documentsDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'props', 'preset', 'source', 'value') === 'cards-wide', 'A View de documentos deve usar o preset de cartões amplos.');

$shop = cutover_yaml_file($root . '/config/sync/views.view.aculta_shop_catalog.yml');
$shopDefault = cutover_yaml_array($shop, 'display', 'default', 'display_options');
$shopModules = cutover_yaml_array($shop, 'dependencies', 'module');
$assert(in_array('ui_patterns_views', $shopModules, TRUE), 'O catálogo Commerce deve declarar a dependência UI Patterns Views.');
$assert(cutover_yaml_get($shopDefault, 'style', 'type') === 'ui_patterns', 'O catálogo Commerce deve usar UI Patterns Style.');
$assert(cutover_yaml_get($shopDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'component_id') === 'aculta420_v2:layout-grid', 'O catálogo Commerce deve reutilizar o layout-grid existente.');
$assert(cutover_yaml_get($shopDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'props', 'preset', 'source', 'value') === 'cards-standard', 'A grade Commerce deve usar o preset de cartões padrão.');
$assert(cutover_yaml_get($shopDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'slots', 'content', 'sources', '0', 'source_id') === 'view_rows', 'O grid Commerce deve compor as rows renderizadas nativamente.');
$assert(cutover_yaml_get($shopDefault, 'row', 'type') === 'entity:commerce_product' && cutover_yaml_get($shopDefault, 'row', 'options', 'view_mode') === 'catalog', 'O row nativo Commerce e seu display catalog devem permanecer.');
$assert(cutover_yaml_get($shopDefault, 'pager', 'type') === 'full' && cutover_yaml_get($shopDefault, 'pager', 'options', 'items_per_page') === 12, 'O pager Commerce deve continuar completo com 12 itens por página.');
$assert(cutover_yaml_get($shopDefault, 'access', 'type') === 'perm' && cutover_yaml_get($shopDefault, 'access', 'options', 'perm') === 'access content', 'O acesso nativo do catálogo Commerce deve permanecer.');
$shopFilters = cutover_yaml_array($shopDefault, 'filters');
$assert(isset($shopFilters['status'], $shopFilters['type']), 'Status publicado e tipo Produto da loja devem permanecer como filtros.');
$assert(cutover_yaml_get($shopDefault, 'sorts', 'title', 'order') === 'ASC', 'O catálogo Commerce deve preservar a ordenação ascendente por título.');
foreach (['block_catalog', 'page_catalog'] as $shopDisplay) {
  $options = cutover_yaml_array($shop, 'display', $shopDisplay, 'display_options');
  $assert((cutover_yaml_get($options, 'defaults', 'style') ?? TRUE) === TRUE, "$shopDisplay deve herdar o grid único do catálogo.");
}

$wiki = cutover_yaml_file($root . '/config/sync/views.view.wiki_entries.yml');
$wikiDefault = cutover_yaml_array($wiki, 'display', 'default', 'display_options');
$wikiModules = cutover_yaml_array($wiki, 'dependencies', 'module');
$assert(in_array('ui_patterns_views', $wikiModules, TRUE), 'A Wiki deve declarar UI Patterns Views.');
$assert(cutover_yaml_get($wikiDefault, 'style', 'type') === 'ui_patterns', 'A listagem da Wiki deve usar o plugin UI Patterns Style.');
$assert(cutover_yaml_get($wikiDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'component_id') === 'aculta420_v2:layout-grid', 'A Wiki deve reutilizar o SDC layout-grid.');
$assert(cutover_yaml_get($wikiDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'props', 'max_columns', 'source', 'value') === '2', 'A grade da Wiki deve manter o limite de duas colunas via source number do UI Patterns.');
$assert(cutover_yaml_get($wikiDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'slots', 'content', 'sources', '0', 'source_id') === 'view_rows', 'A grade da Wiki deve compor as rows renderizadas pela View.');
$assert(cutover_yaml_get($wikiDefault, 'row', 'type') === 'entity:node' && cutover_yaml_get($wikiDefault, 'row', 'options', 'view_mode') === 'teaser', 'A Wiki deve preservar o row de node/teaser e o adapter SDC do tema.');
$assert(cutover_yaml_get($wikiDefault, 'access', 'options', 'perm') === 'access content', 'A Wiki deve preservar a permissão nativa de leitura.');
$assert(cutover_yaml_get($wikiDefault, 'pager', 'options', 'items_per_page') === 12, 'O pager da Wiki deve preservar os 12 itens por página.');
$wikiFilters = cutover_yaml_array($wikiDefault, 'filters');
$assert(isset($wikiFilters['status'], $wikiFilters['type'], $wikiFilters['field_domain_source_target_id']), 'Publicação, bundle e Domain ativo devem continuar filtrados pela View.');
$assert(cutover_yaml_get($wikiDefault, 'sorts', 'created', 'order') === 'DESC', 'A Wiki deve preservar a ordem de publicação decrescente.');
foreach (['block_changes', 'block_recent', 'page_1'] as $wikiDisplay) {
  $options = cutover_yaml_array($wiki, 'display', $wikiDisplay, 'display_options');
  $assert((cutover_yaml_get($options, 'defaults', 'style') ?? TRUE) === TRUE, "$wikiDisplay deve herdar a composição SDC única da View.");
}

$wikiCategories = cutover_yaml_file($root . '/config/sync/views.view.wiki_categories.yml');
$wikiCategoriesDefault = cutover_yaml_array($wikiCategories, 'display', 'default', 'display_options');
$wikiCatModules = cutover_yaml_array($wikiCategories, 'dependencies', 'module');
$assert(in_array('ui_patterns_views', $wikiCatModules, TRUE), 'As categorias da Wiki devem declarar UI Patterns Views.');
$assert(cutover_yaml_get($wikiCategoriesDefault, 'style', 'type') === 'ui_patterns', 'As categorias da Wiki devem usar o plugin UI Patterns Style.');
$assert(cutover_yaml_get($wikiCategoriesDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'component_id') === 'aculta420_v2:layout-grid', 'As categorias da Wiki devem reutilizar layout-grid.');
$assert(cutover_yaml_get($wikiCategoriesDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'props', 'max_columns', 'source', 'value') === '2', 'As categorias da Wiki devem manter duas colunas via source number.');
$assert(cutover_yaml_get($wikiCategoriesDefault, 'style', 'options', 'ui_patterns', 'ui_patterns', 'slots', 'content', 'sources', '0', 'source_id') === 'view_rows', 'A grade deve compor rows renderizadas pela View.');
$assert(cutover_yaml_get($wikiCategoriesDefault, 'row', 'type') === 'entity:taxonomy_term' && cutover_yaml_get($wikiCategoriesDefault, 'row', 'options', 'view_mode') === 'default', 'As categorias devem manter o row nativo de taxonomia.');
$assert(cutover_yaml_get($wikiCategoriesDefault, 'access', 'options', 'perm') === 'access content', 'As categorias devem preservar a permissão nativa de leitura.');
$assert(cutover_yaml_get($wikiCategoriesDefault, 'pager', 'options', 'items_per_page') === 30, 'As categorias devem preservar o pager de 30 itens.');
$assert(cutover_yaml_get($wikiCategoriesDefault, 'filters', 'vid') !== NULL && cutover_yaml_get($wikiCategoriesDefault, 'sorts', 'weight', 'order') === 'ASC', 'Vocabulário e ordem editorial devem continuar sob Views.');
$assert((cutover_yaml_get($wikiCategories, 'display', 'block_1', 'display_options', 'defaults', 'style') ?? TRUE) === TRUE, 'O bloco de categorias deve herdar a grade SDC única.');

$displaySlots = cutover_yaml_array($page, 'ui_patterns', 'slots');
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
  $assert(cutover_yaml_get($displaySlots, $slot, 'sources', '0', 'source_id') === $source, "$slot deve ser composto pelo source $source.");
}
$assert(cutover_yaml_get($page, 'ui_patterns', 'props', 'title', 'source_id') === 'view_title', 'O título deve vir do source nativo View Title.');

$rowComponent = cutover_yaml_file($root . '/web/themes/custom/aculta420_v2/components/revista-album-card/revista-album-card.component.yml');
$rowCompSlots = cutover_yaml_array($rowComponent, 'slots');
foreach (['cover', 'title', 'published_at', 'credits'] as $slot) {
  $assert(isset($rowCompSlots[$slot]), "O SDC revista-album-card precisa expor o slot $slot.");
}

$forumView = cutover_yaml_file($root . '/config/sync/views.view.aculta_forum_categories.yml');
$forumInstall = cutover_yaml_file($root . '/web/modules/custom/aculta_portal/modules/aculta_forum/config/install/views.view.aculta_forum_categories.yml');
$forumPage = cutover_yaml_array($forumView, 'display', 'page_categories', 'display_options');
$forumInstallPage = cutover_yaml_array($forumInstall, 'display', 'page_categories', 'display_options');
$forumDefault = cutover_yaml_array($forumView, 'display', 'default', 'display_options');
$forumStyle = cutover_yaml_array($forumPage, 'style');
$assert(cutover_yaml_get($forumPage, 'defaults', 'ui_patterns') === FALSE, 'Categorias deve selecionar explicitamente o componente de display sem herdar config vazia.');
$assert(cutover_yaml_get($forumPage, 'row', 'type') === 'ui_patterns', 'A View de categorias deve manter UI Patterns no nível row.');
$assert(cutover_yaml_get($forumPage, 'row', 'options', 'ui_patterns', 'component_id') === 'aculta420_v2:forum-category', 'A View de categorias deve reutilizar forum-category.');
$assert(cutover_yaml_get($forumStyle, 'type') === 'ui_patterns', 'Categorias deve usar o estilo UI Patterns.');
$assert(cutover_yaml_get($forumStyle, 'options', 'ui_patterns', 'ui_patterns', 'component_id') === 'aculta420_v2:layout-grid', 'A listagem de categorias deve compor os cartões pelo SDC layout-grid.');
$assert(cutover_yaml_get($forumStyle, 'options', 'ui_patterns', 'ui_patterns', 'slots', 'content', 'sources', '0', 'source_id') === 'view_rows', 'A grade deve receber as rows já renderizadas por UI Patterns.');
$assert(cutover_yaml_get($forumInstallPage, 'style') === cutover_yaml_get($forumPage, 'style'), 'Config Sync e instalação devem usar o mesmo style UI Patterns para page_categories.');
$assert(cutover_yaml_get($forumInstallPage, 'row') === cutover_yaml_get($forumPage, 'row'), 'Config Sync e instalação devem manter a mesma composição UI Patterns por row.');
$assert(cutover_yaml_get($forumDefault, 'pager', 'type') === 'some', 'O pager nativo herdado pela página de categorias deve permanecer.');
$assert(cutover_yaml_get($forumDefault, 'access', 'type') === 'perm' && cutover_yaml_get($forumDefault, 'access', 'options', 'perm') === 'access content', 'A página de categorias deve manter controle de acesso nativo herdado.');
$assert(cutover_yaml_get($forumDefault, 'filters', 'vid') !== NULL && cutover_yaml_get($forumDefault, 'sorts', 'weight') !== NULL, 'Vocabulário e ordenação editorial herdados da View de categorias devem permanecer.');
$assert(cutover_yaml_get($forumPage, 'ui_patterns', 'component_id') === 'aculta420_v2:views-display', 'O display de categorias deve reutilizar o SDC views-display.');
$assert(cutover_yaml_get($forumPage, 'ui_patterns', 'props', 'title') === NULL, 'A página de categorias não deve duplicar seu título visual nativo.');
$forumDisplaySlots = cutover_yaml_array($forumPage, 'ui_patterns', 'slots');
foreach ($requiredSources as $slot => $source) {
  $assert(cutover_yaml_get($forumDisplaySlots, $slot, 'sources', '0', 'source_id') === $source, "Categorias/$slot deve usar a fonte $source.");
}
$assert(cutover_yaml_get($forumPage, 'header', 'area_text_custom', 'content') === '<p><a href="/">Discussões recentes</a></p>', 'O link Discussões recentes deve permanecer no header nativo da View.');

$slots = cutover_yaml_array($component, 'slots');
foreach (['attachment_before', 'header', 'exposed', 'rows', 'empty', 'pager', 'more', 'footer', 'attachment_after'] as $slot) {
  $assert(isset($slots[$slot]), "O SDC views-display precisa expor o slot $slot.");
}
$assert(cutover_yaml_get($component, 'props', 'additionalProperties') === FALSE, 'O schema do SDC deve rejeitar props desconhecidas.');

fwrite(STDOUT, sprintf("PASS — validate-ui-patterns-views-cutover (%d checks)\n", $checks));
