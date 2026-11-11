<?php

/**
 * @file
 * Generates an auditable inventory of UI Suite integrations and consumers.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$output = $root . '/docs/architecture/inventarios/ui-suite-consumidores-iniciais.csv';
$sync = $root . '/config/sync';
$theme = $root . '/web/themes/custom/aculta420_v2';
$customModules = $root . '/web/modules/custom';
$runtimeSnapshotPath = $root . '/docs/architecture/inventarios/ui-suite-runtime-2026-11-10.json';

require $root . '/vendor/autoload.php';

$columns = [
  'id',
  'owner_module',
  'config_path',
  'consumer_type',
  'consumer_id',
  'display_plugin',
  'row_plugin',
  'style_plugin',
  'ui_patterns_configured',
  'status',
  'replacement_or_action',
  'risk_or_evidence_gap',
  'rollback',
  'detected_consumers',
  'runtime_status',
];
$rows = [];
$runtimeSnapshot = is_file($runtimeSnapshotPath)
  ? json_decode((string) file_get_contents($runtimeSnapshotPath), TRUE, flags: JSON_THROW_ON_ERROR)
  : [];
$runtimeStatuses = is_array($runtimeSnapshot['ui_suite_modules'] ?? NULL)
  ? $runtimeSnapshot['ui_suite_modules']
  : [];
$runtimeObservedDate = is_string($runtimeSnapshot['observed_at_utc'] ?? NULL)
  ? substr($runtimeSnapshot['observed_at_utc'], 0, 10)
  : 'unknown-date';
$runtimeViewDisplays = is_array($runtimeSnapshot['view_displays'] ?? NULL)
  ? $runtimeSnapshot['view_displays']
  : [];

foreach (glob($sync . '/views.view.*.yml') ?: [] as $path) {
  $view = Yaml::parseFile($path);
  $viewId = (string) ($view['id'] ?? basename($path));
  foreach (($view['display'] ?? []) as $displayId => $display) {
    $options = $display['display_options'] ?? [];
    $defaults = $options['defaults'] ?? [];
    $baseOptions = $view['display']['default']['display_options'] ?? [];
    $rowOptions = $options['row'] ?? (($defaults['row'] ?? TRUE) ? ($baseOptions['row'] ?? []) : []);
    $styleOptions = $options['style'] ?? (($defaults['style'] ?? TRUE) ? ($baseOptions['style'] ?? []) : []);
    $rowPlugin = (string) ($rowOptions['type'] ?? '');
    $stylePlugin = (string) ($styleOptions['type'] ?? '');
    $rowPatterns = $rowOptions['options']['ui_patterns'] ?? NULL;
    $stylePatterns = $styleOptions['options']['ui_patterns']['ui_patterns'] ?? NULL;
    $displayPatterns = $options['ui_patterns'] ?? NULL;
    $patternsConfigured = ($rowPlugin === 'ui_patterns' && is_array($rowPatterns) && !empty($rowPatterns['component_id']))
      || (is_array($stylePatterns) && !empty($stylePatterns['component_id']))
      || (is_array($displayPatterns) && !empty($displayPatterns['component_id']));
    $components = [];
    foreach ([
      'row' => $rowPatterns['component_id'] ?? NULL,
      'style' => $stylePatterns['component_id'] ?? NULL,
      'display' => $displayPatterns['component_id'] ?? NULL,
    ] as $level => $componentId) {
      if (is_string($componentId) && $componentId !== '') {
        $components[] = $level . ':' . $componentId;
      }
    }
    $isForumCategoryPilot = $viewId === 'aculta_forum_categories'
      && in_array((string) $displayId, ['page_categories', 'block_landing'], TRUE)
      && $patternsConfigured;
    $isAlbumsViewsPilot = $viewId === 'aculta_albuns'
      && in_array((string) $displayId, ['default', 'page_1'], TRUE)
      && ((is_array($stylePatterns) && !empty($stylePatterns['component_id']))
        || (is_array($displayPatterns) && !empty($displayPatterns['component_id'])));
    $isEventsCataloguePilot = $viewId === 'eventos_publicos'
      && (string) $displayId === 'block_1'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid';
    $isRevistaNewsPilot = $viewId === 'aculta_news'
      && (string) $displayId === 'block_1'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:revista-listing';
    $isHomeEditorialPilot = $viewId === 'home_editorial_highlights'
      && (string) $displayId === 'block_1'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid';
    $isAlbumPhotosPilot = $viewId === 'aculta_album_fotos'
      && (string) $displayId === 'block_1'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid';
    $isForumGalleryPilot = $viewId === 'aculta_forum_gallery_slides'
      && (string) $displayId === 'block_1'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid';
    $isProductGalleryPilot = $viewId === 'galeria_produto'
      && (string) $displayId === 'default'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid';
    $isMediaGalleriesPilot = $viewId === 'media_galleries'
      && (string) $displayId === 'page_1'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid'
      && $rowPlugin === 'ui_patterns'
      && ($rowPatterns['component_id'] ?? NULL) === 'aculta420_v2:revista-album-card';
    $isUpstreamVvjsExample = preg_match('/^vvj[a-z]_example$/', $viewId) === 1;
    $isWebformSubmissions = $viewId === 'webform_submissions';
    $forumTopicFieldsTemplate = $theme . '/templates/views/views-view-fields--aculta-forum-public-topics.html.twig';
    $isForumTopicsSdc = $viewId === 'aculta_forum_public_topics'
      && (string) $displayId !== 'default'
      && is_file($forumTopicFieldsTemplate)
      && str_contains((string) file_get_contents($forumTopicFieldsTemplate), "aculta420_v2:forum-topic-row");
    $documentTeaserTemplate = $theme . '/templates/content/node--document--teaser.html.twig';
    $documentCardSdc = $theme . '/components/document-card/document-card.component.yml';
    $isDocumentCardSdc = $viewId === 'aculta_documents'
      && (string) $displayId === 'block_1'
      && is_file($documentTeaserTemplate)
      && is_file($documentCardSdc)
      && str_contains((string) file_get_contents($documentTeaserTemplate), "aculta420_v2:document-card");
    $isDocumentsPilot = $isDocumentCardSdc
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid';
    $productCatalogTemplate = $theme . '/templates/commerce/commerce-product--produto-loja--catalog.html.twig';
    $isProductCardAdapter = is_file($productCatalogTemplate)
      && str_contains((string) file_get_contents($productCatalogTemplate), "aculta420_v2:product-card");
    $isShopCatalogPilot = $viewId === 'aculta_shop_catalog'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid'
      && $rowPlugin === 'entity:commerce_product'
      && ($rowOptions['options']['view_mode'] ?? '') === 'catalog'
      && $isProductCardAdapter;
    $wikiTeaserTemplate = $theme . '/templates/content/node--wiki-entry--teaser.html.twig';
    $isWikiEntriesPilot = $viewId === 'wiki_entries'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid'
      && $rowPlugin === 'entity:node'
      && ($rowOptions['options']['view_mode'] ?? '') === 'teaser'
      && is_file($wikiTeaserTemplate)
      && str_contains((string) file_get_contents($wikiTeaserTemplate), "aculta420_v2:wiki-entry");
    $isWikiCategoriesPilot = $viewId === 'wiki_categories'
      && $stylePlugin === 'ui_patterns'
      && ($stylePatterns['component_id'] ?? NULL) === 'aculta420_v2:layout-grid'
      && $rowPlugin === 'entity:taxonomy_term'
      && ($rowOptions['options']['view_mode'] ?? '') === 'default';
    $isEventsBase = $viewId === 'eventos_publicos' && (string) $displayId === 'default';
    $revistaTeaserTemplate = $theme . '/templates/content/node--revista--teaser.html.twig';
    $isRevistaTeaserSdc = $viewId === 'revista_sessoes'
      && str_starts_with($rowPlugin, 'entity:node')
      && ($rowOptions['options']['view_mode'] ?? '') === 'teaser'
      && is_file($revistaTeaserTemplate)
      && str_contains((string) file_get_contents($revistaTeaserTemplate), "aculta420_v2:revista-teaser");
    $isViewsDefaultBase = (string) $displayId === 'default';
    $isViewsFunctionalBase = $isViewsDefaultBase;
    $displayPath = (string) ($options['path'] ?? '');
    $isAdminViewNative = $stylePlugin === 'table'
      && (str_starts_with($displayPath, 'admin/') || str_starts_with($displayPath, 'painel-administrativo/'));
    $runtimeView = $runtimeViewDisplays[$viewId] ?? NULL;
    $wikiRuntimeVerified = $isWikiEntriesPilot
      && is_array($runtimeView)
      && ($runtimeView['status'] ?? '') === 'runtime_verified';
    $wikiCategoriesRuntimeVerified = $isWikiCategoriesPilot
      && is_array($runtimeViewDisplays['wiki_categories'] ?? NULL)
      && (($runtimeViewDisplays['wiki_categories']['status'] ?? '') === 'runtime_verified');
    $albumsRuntimeVerified = $isAlbumsViewsPilot
      && is_array($runtimeView)
      && ($runtimeView['status'] ?? '') === 'runtime_verified';
    $forumRuntimeDisplay = $isForumCategoryPilot && is_array($runtimeView)
      ? ($runtimeView['displays'][$displayId] ?? NULL)
      : NULL;
    $forumRuntimeVerified = is_array($forumRuntimeDisplay)
      && ($forumRuntimeDisplay['status'] ?? '') === 'runtime_verified';
    $eventsRuntimeDisplay = $isEventsCataloguePilot && is_array($runtimeView)
      ? ($runtimeView['displays'][$displayId] ?? NULL)
      : NULL;
    $eventsRuntimeVerified = is_array($eventsRuntimeDisplay)
      && ($eventsRuntimeDisplay['status'] ?? '') === 'runtime_verified'
      && ($eventsRuntimeDisplay['style_plugin'] ?? '') === 'ui_patterns'
      && ($eventsRuntimeDisplay['style_component'] ?? '') === 'aculta420_v2:layout-grid';
    $revistaNewsRuntimeDisplay = $isRevistaNewsPilot && is_array($runtimeView)
      ? ($runtimeView['displays'][$displayId] ?? NULL)
      : NULL;
    $revistaNewsRuntimeVerified = is_array($revistaNewsRuntimeDisplay)
      && ($revistaNewsRuntimeDisplay['status'] ?? '') === 'runtime_verified'
      && ($revistaNewsRuntimeDisplay['style_component'] ?? '') === 'aculta420_v2:revista-listing';
    $homeEditorialRuntimeDisplay = $isHomeEditorialPilot && is_array($runtimeView)
      ? ($runtimeView['displays'][$displayId] ?? NULL)
      : NULL;
    $homeEditorialRuntimeVerified = is_array($homeEditorialRuntimeDisplay)
      && ($homeEditorialRuntimeDisplay['status'] ?? '') === 'runtime_verified'
      && ($homeEditorialRuntimeDisplay['style_component'] ?? '') === 'aculta420_v2:layout-grid';
    $albumPhotosRuntimeDisplay = $isAlbumPhotosPilot && is_array($runtimeView)
      ? ($runtimeView['displays'][$displayId] ?? NULL)
      : NULL;
    $albumPhotosRuntimeVerified = is_array($albumPhotosRuntimeDisplay)
      && ($albumPhotosRuntimeDisplay['status'] ?? '') === 'runtime_verified'
      && ($albumPhotosRuntimeDisplay['style_component'] ?? '') === 'aculta420_v2:layout-grid';
    $forumGalleryRuntimeConfigured = $isForumGalleryPilot
      && is_array($runtimeView)
      && is_array($runtimeView['displays'][$displayId] ?? NULL)
      && ($runtimeView['displays'][$displayId]['status'] ?? '') === 'runtime_configured_no_fixture';
    $documentRuntimeVerified = $isDocumentsPilot
      && is_array($runtimeView)
      && is_array($runtimeView['displays'][$displayId] ?? NULL)
      && ($runtimeView['displays'][$displayId]['status'] ?? '') === 'runtime_verified_sdc_grid';
    $shopRuntimeDisplay = $isShopCatalogPilot && is_array($runtimeView)
      ? ($runtimeView['displays'][$displayId] ?? NULL)
      : NULL;
    $shopRuntimeVerified = is_array($shopRuntimeDisplay)
      && ($shopRuntimeDisplay['status'] ?? '') === 'runtime_verified_sdc_grid'
      && ($shopRuntimeDisplay['style_component'] ?? '') === 'aculta420_v2:layout-grid';
    $productGalleryRuntimeConfigured = $isProductGalleryPilot
      && is_array($runtimeView)
      && is_array($runtimeView['displays'][$displayId] ?? NULL)
      && ($runtimeView['displays'][$displayId]['status'] ?? '') === 'runtime_configured_visual_deferred';
    $mediaGalleriesRuntimeVerified = $isMediaGalleriesPilot
      && is_array($runtimeView)
      && is_array($runtimeView['displays'][$displayId] ?? NULL)
      && ($runtimeView['displays'][$displayId]['status'] ?? '') === 'runtime_verified';
    $revistaRuntimeView = $runtimeViewDisplays[$viewId] ?? NULL;
    $revistaRuntimeVerified = $isRevistaTeaserSdc
      && is_array($revistaRuntimeView)
      && ($revistaRuntimeView['status'] ?? '') === 'runtime_verified';
    $forumCategoryLegacyTemplatesRemoved = $isForumCategoryPilot
      && !is_file($theme . '/templates/views/views-view-fields--aculta-forum-categories--' . $displayId . '.html.twig')
      && !is_file($theme . '/templates/views/views-view-unformatted--aculta-forum-categories--' . $displayId . '.html.twig')
      && !is_file($theme . '/templates/views/views-view--aculta-forum-categories--' . $displayId . '.html.twig');
    $rows[] = [
      'id' => $viewId,
      'owner_module' => match ($viewId) {
        'aculta_albuns' => 'aculta_gallery',
        'aculta_forum_categories' => 'aculta_forum',
        'aculta_forum_public_topics' => 'aculta_forum',
        'revista_sessoes' => 'aculta_magazine',
        'aculta_news' => 'aculta_magazine',
        'aculta_album_fotos', 'aculta_forum_gallery_slides' => 'aculta_gallery',
        'galeria_produto' => 'aculta_portal:commerce',
        'media_galleries' => 'media_gallery (contrib)',
        'aculta_documents' => 'aculta_portal:transparency',
        'aculta_shop_catalog' => 'aculta_portal:commerce',
        'wiki_entries' => 'aculta_wiki',
        'wiki_categories' => 'aculta_wiki',
        'vvjs_example' => 'vvjs:upstream_demo',
        'home_editorial_highlights' => 'views:home_editorial_highlights',
        'eventos_publicos' => 'aculta_events',
        default => 'unresolved_from_config',
      },
      'config_path' => relative_path($root, $path),
      'consumer_type' => 'view_display',
      'consumer_id' => (string) $displayId,
      'display_plugin' => (string) ($display['display_plugin'] ?? ''),
      'row_plugin' => $rowPlugin,
      'style_plugin' => $stylePlugin,
      'ui_patterns_configured' => $components !== [] ? implode(',', $components) : 'not_detected_by_plugin_id_scan',
      'status' => $isUpstreamVvjsExample
        ? 'BLOCKED_UPSTREAM'
        : (($isWikiEntriesPilot || $isWikiCategoriesPilot) && $displayId === 'default'
        ? 'FUNCTIONAL_NATIVE_EXCEPTION'
        : ($isForumTopicsSdc || $isRevistaTeaserSdc || $documentRuntimeVerified || $shopRuntimeVerified || $wikiRuntimeVerified || $wikiCategoriesRuntimeVerified
        ? 'UI_SUITE_NATIVE'
        : ((($isViewsFunctionalBase && !$isProductGalleryPilot) || $isEventsBase || $isAdminViewNative || $isWebformSubmissions)
        ? 'FUNCTIONAL_NATIVE_EXCEPTION'
        : (($albumsRuntimeVerified || $forumRuntimeVerified || $eventsRuntimeVerified || $revistaRuntimeVerified || $revistaNewsRuntimeVerified || $homeEditorialRuntimeVerified || $albumPhotosRuntimeVerified || $mediaGalleriesRuntimeVerified) ? 'UI_SUITE_NATIVE' : 'MIGRATION_IN_PROGRESS')))),
      'replacement_or_action' => match (TRUE) {
        $isUpstreamVvjsExample => 'Upstream demonstration View, not an ACULTA feature; do not edit contrib. Any removal or replacement must be handled as a separately authorized upstream-example lifecycle change',
        $isWikiEntriesPilot && $displayId === 'default' => 'Functional inherited Views base; keep query/access/filter/sort and inherited UI Patterns style, with no independent route output',
        $isWikiCategoriesPilot && $displayId === 'default' => 'Functional inherited Views base; preserve taxonomy query/access/order and inherited UI Patterns style, with no independent route output',
        $isDocumentsPilot => 'The document teaser SDC owns card markup; UI Patterns layout-grid composes the native published-node teaser rows while preserving Field API file rendering',
        $isShopCatalogPilot => 'UI Patterns layout-grid composes the product-card SDC for native Commerce catalog rows; keep price/variation/add-to-cart render arrays, published/type filters, access, ordering and pager in Commerce',
        $isWikiEntriesPilot => 'UI Patterns layout-grid composes native node teaser render arrays through the wiki-entry SDC adapter; keep publication, bundle, active Domain, access, order and pager in Views',
        $isWikiCategoriesPilot => 'UI Patterns layout-grid composes native taxonomy-term rows; preserve vocabulary filter, term links, access, editorial order and pager in Views',
        $isDocumentCardSdc => 'The document teaser row uses the existing document-card SDC; finish its UI Patterns layout composition and Runtime validation',
        $isForumTopicsSdc => 'The Fields row adapter already composes the reusable forum-topic-row SDC; preserve it and migrate to the UI Patterns row plugin only when dynamic entity props and rendered slots can be mapped without losing topic state',
        $isProductGalleryPilot => 'UI Patterns layout-grid composes the native Commerce product media View; preserve product Media IDs, ordering, published/image filters and produto_galeria view mode',
        $isWebformSubmissions => 'Webform submission administration is a functional native Views table; keep Webform query/access/filters and Drupal table presentation',
        $isAdminViewNative => 'Native administrative Views table; keep query, access, filters, sorting, pager and Drupal table presentation',
        $isViewsFunctionalBase || $isEventsBase => 'Native Views base display supplies inherited query, access, filters, sorting and configured style to concrete displays; it has no independent route output',
        $isAlbumsViewsPilot => 'Compose this inherited View level with UI Patterns SDCs; preserve native row fields, filters, sort, pager and access',
        $isForumCategoryPilot => $forumCategoryLegacyTemplatesRemoved
          ? 'UI Patterns row SDC cutover is defined in Sync/install; source field/wrapper templates removed; retain Views query, grouping, pager and access contracts'
          : 'Pilot: replace duplicated row field templates with the forum-category SDC; retain Views query, grouping, pager and access contracts',
        $isEventsCataloguePilot => 'UI Patterns layout-grid wraps the native View rows; aculta_events_catalogue_card keeps access-checked IDs, labels and ordering',
        $isRevistaNewsPilot => 'UI Patterns style composes the existing revista-listing SDC around native teaser render arrays; published filter, sort, access and cache remain Views-owned',
        $isHomeEditorialPilot => 'UI Patterns layout-grid composes the existing editorial highlight Layout Builder render arrays; preserve native query, ordering, pager, access and cache',
        $isAlbumPhotosPilot => 'UI Patterns layout-grid composes native PhotoSwipe image render arrays; preserve media access filtering, album order, pager and cache metadata',
        $isMediaGalleriesPilot => 'UI Patterns row revista-album-card maps the native media thumbnail/title fields; layout-grid composes view_rows while Views keeps query, filters, pager and access',
        $isForumGalleryPilot => 'UI Patterns layout-grid composes Forum gallery View rows; preserve contextual gallery selection, Media access, PhotoSwipe and cache metadata',
        $isRevistaTeaserSdc => 'Keep the native entity teaser row; the ACULTA theme adapter renders fields as render-array slots through the existing revista-teaser SDC',
        default => 'Inventory candidate only; inspect row, style and display independently before migration',
      },
      'risk_or_evidence_gap' => match (TRUE) {
        $isUpstreamVvjsExample => 'Example View remains in Config Sync and its Runtime status was not changed in this microphase; it is not counted as a product consumer',
        $isDocumentsPilot && $documentRuntimeVerified => 'Runtime View retained 3 published nodes; SDC output, anonymous access and field render arrays verified; browser visual, editor/admin profiles and cache-hit remain pending',
        $isShopCatalogPilot && $shopRuntimeVerified => 'Anonymous Runtime returned product-card SDC with price, summary and add-to-cart; Firefox passed 10/10 viewports/modes with focus and contrast; zoom 200%, other roles, cache-hit and human visual acceptance remain pending',
        $isWikiEntriesPilot && $wikiRuntimeVerified => 'Anonymous Runtime returned six published wiki-entry teaser rows through the SDC adapter; Firefox browser comparison passed for the page and canonical article; zoom, cache-hit, other roles and assistive technology remain pending',
        $isWikiCategoriesPilot && $wikiCategoriesRuntimeVerified => 'Anonymous Runtime retained 15 taxonomy category rows; Firefox home comparison verified layout-grid, responsive columns and row count; zoom, cache-hit, other roles and assistive technology remain pending',
        $isDocumentsPilot => 'Runtime returned three published document teasers inside the UI Patterns grid; the current fixture has empty body/file descriptions, so field links are not evidenced; browser visual, editor/admin profiles and cache-hit remain pending',
        $isDocumentCardSdc => 'Document SDC adapter is present; Runtime row rendering and grid integration are pending',
        $isForumTopicsSdc && is_array($runtimeView) && is_array($runtimeView['displays'][$displayId] ?? NULL) => 'Runtime config matches Sync; executable View returns 10 rows; access-profile and browser visual evidence not re-run in this inventory pass',
        $isForumTopicsSdc => 'Existing SDC row adapter is confirmed in source; Runtime output, access profiles and browser evidence need verification',
        $isProductGalleryPilot => $productGalleryRuntimeConfigured
          ? 'Runtime View returned seven Media rows for the published Commerce product; browser visual, access and cache acceptance remain DEFERRED'
          : 'Commerce product View has a programmatic consumer; Runtime execution and browser acceptance remain pending',
        $isWebformSubmissions => 'Native Webform submission table; presentation stays in Drupal Views',
        $isAdminViewNative => 'Administrative table consumes the inherited query/access/filter/sort; presentation stays in Drupal Views',
        $isViewsFunctionalBase || $isEventsBase => 'Inherited query/access/filter/sort and any base style are consumed by explicit displays; no independent route output',
        $isAlbumsViewsPilot => $albumsRuntimeVerified
          ? 'Runtime/render/browser verified at 1280/320 light/dark; pager navigation, keyboard, zoom 200% and cache-hit variation remain open'
          : 'Sync defines row/style/display composition; targeted Runtime import and output, permission, pager/cache and browser comparison remain pending',
        $isForumCategoryPilot && $forumRuntimeVerified => 'Runtime/browser verified at five light viewports, two dark viewports and keyboard focus; cache-hit and zoom 200% remain open; native grouping and pager retained',
        $isForumCategoryPilot && $displayId === 'block_landing' => 'UI Patterns row, views-display and style are configured in Sync/install; the block placement is hidden on the public home, so visible-consumer evidence remains DEFERRED',
        $isForumCategoryPilot && $forumCategoryLegacyTemplatesRemoved => 'Sync/install declare the UI Patterns row, but Runtime/render/browser evidence remains pending',
        $isForumCategoryPilot => 'Config Sync and install defaults use UI Patterns row; Runtime cutover and output/access/cache comparison remain pending',
        $isEventsCataloguePilot && $eventsRuntimeVerified => 'Firefox ESR: 2 card-cultural SDCs inside layout-grid; 320/1440 light/dark, visible keyboard focus, no horizontal scroll; detail routes separately smoke-tested',
        $isEventsCataloguePilot => 'UI Patterns layout-grid is configured; Runtime style output and browser comparison remain pending',
        $isRevistaNewsPilot && $revistaNewsRuntimeVerified => 'Firefox ESR verified 4 native teaser nodes, visible H1, contrast ≥7.16:1, keyboard focus and 1/2/3-column reflow at 320/390/768/1024/1440 in light/dark; cache-hit and 200% zoom remain open',
        $isRevistaNewsPilot => 'UI Patterns SDC style configured; targeted Runtime output, access/cache comparison and browser evidence remain pending',
        $isHomeEditorialPilot && $homeEditorialRuntimeVerified => 'Firefox ESR verified nodes 14/15/16, H1/main, keyboard focus, contrast ≥8.80:1 and reflow at 320/390/768/1024/1440 light/dark; cache-hit and 200% zoom remain open',
        $isHomeEditorialPilot => 'UI Patterns layout-grid configured; targeted Runtime output, access/cache comparison and browser evidence remain pending',
        $isAlbumPhotosPilot && $albumPhotosRuntimeVerified => 'Runtime HTTP 200 and 63 PhotoSwipe images before/after; Firefox ESR 320/390/768/1024/1440 light/dark, H1/main, alt, lazy loading, focus, no overflow and PhotoSwipe Escape verified; cache-hit and 200% zoom remain open',
        $isAlbumPhotosPilot => 'UI Patterns grid configured; Runtime output, media access/cache comparison and browser evidence remain pending',
        $isMediaGalleriesPilot && $mediaGalleriesRuntimeVerified => 'Runtime/Sync match after isolated import; anonymous /galleries returned 3 cards and 2 images with one H1/main; Firefox ESR 320/390/768/1024/1440 light/dark without overflow; pager navigation, cache-hit, zoom 200%, keyboard, profiles and human review remain open',
        $isMediaGalleriesPilot => 'UI Patterns row/style configured; Runtime output, access/cache comparison and browser evidence remain pending',
        $isForumGalleryPilot => $forumGalleryRuntimeConfigured
          ? 'Runtime View configuration imported and executable with zero published Forum galleries; visual acceptance is DEFERRED until a published fixture exists'
          : 'Forum has no published gallery fixture; Runtime import and executable configuration comparison remain pending',
        $isRevistaTeaserSdc && $revistaRuntimeVerified => 'Theme adapter composes revista-teaser; homepage smoke is not attributable to this View; route, access, pager/cache remain pending',
        $isRevistaTeaserSdc => 'Sync source confirms node teaser row and SDC adapter; Runtime output, access, pager/cache and browser acceptance remain pending',
        default => 'No UI Patterns row/style/display plugin ID detected in Sync; Runtime output, access and cache still unverified',
      },
      'rollback' => 'Revert only display config after before/after result comparison',
      'detected_consumers' => '',
      'runtime_status' => match (TRUE) {
        $isUpstreamVvjsExample => 'upstream contributed demonstration; Runtime status not changed or claimed',
        $isDocumentsPilot && $documentRuntimeVerified => 'active; UI Patterns layout-grid; 3 published document rows; anonymous View output with Field API slots; visual browser acceptance pending',
        $isShopCatalogPilot && $shopRuntimeVerified => sprintf('active; UI Patterns layout-grid; %d product rows; Commerce catalog view mode and native add-to-cart retained', $shopRuntimeDisplay['result_count']),
        $isWikiEntriesPilot && $wikiRuntimeVerified => sprintf('active; UI Patterns layout-grid; %d published node teaser rows; wiki-entry SDC adapter and native access retained', $runtimeView['anonymous_render']['result_count_before_after'] ?? 0),
        $isWikiCategoriesPilot && $wikiCategoriesRuntimeVerified => sprintf('active; UI Patterns layout-grid; %d native taxonomy term rows; anonymous category links and editorial order retained', $runtimeViewDisplays['wiki_categories']['displays']['block_1']['result_count_before_after'] ?? 0),
        $isDocumentsPilot => 'configured; Runtime/browser evidence not recorded',
        $isDocumentCardSdc => 'document teaser SDC adapter is confirmed in source; View style migration and Runtime output remain pending',
        $isForumTopicsSdc && is_array($runtimeView) && is_array($runtimeView['displays'][$displayId] ?? NULL) => 'active; native Views fields row uses forum-topic-row SDC adapter; 10 results; browser/access acceptance not re-run',
        $isForumTopicsSdc => 'existing SDC row adapter confirmed in source; Runtime output not recorded',
        $isProductGalleryPilot && $productGalleryRuntimeConfigured => 'active; Commerce product 1 supplies seven media IDs; UI Patterns style returns seven rows; visual acceptance deferred',
        $isProductGalleryPilot => 'configured; Commerce product View has a programmatic consumer; Runtime evidence not recorded',
        $isForumGalleryPilot && $forumGalleryRuntimeConfigured => 'active; UI Patterns View executable; zero published Forum galleries; visual acceptance deferred',
        $isMediaGalleriesPilot && $mediaGalleriesRuntimeVerified => sprintf('active; HTTP 200; %d cards after; anonymous access allowed; Firefox 320/390/768/1024/1440 light/dark', $runtimeView['displays'][$displayId]['result_count_before_after'] ?? 0),
        $isMediaGalleriesPilot => 'configured; Runtime/browser evidence not recorded',
        $isWebformSubmissions => 'functional native Webform submission View; Drupal table presentation',
        $isAdminViewNative => 'functional native administrative View; Drupal table presentation',
        $isViewsFunctionalBase || $isEventsBase => 'functional native base; no direct route/display consumer',
        $albumsRuntimeVerified => sprintf('active; HTTP %d; %d results before/after; anonymous access allowed; browser 1280/320 light/dark', $runtimeView['after_http_status'], $runtimeView['result_count_after']),
        $forumRuntimeVerified => sprintf('active; HTTP %d; %d taxonomy cards; anonymous access allowed; browser 320/390/768/1024/1440 light and 320/1440 dark', $forumRuntimeDisplay['after_http_status'], $forumRuntimeDisplay['result_count_after']),
        $isForumCategoryPilot && $displayId === 'block_landing' => 'active row plugin; block placement hidden on public home; no visible output claimed',
        $eventsRuntimeVerified => sprintf('active; HTTP %d; %d native rows; UI Patterns layout-grid; anonymous access allowed; Firefox 320/1440 light/dark', $eventsRuntimeDisplay['after_http_status'], $eventsRuntimeDisplay['result_count']),
        $isEventsCataloguePilot => 'configured; Runtime/browser evidence not recorded',
        $revistaNewsRuntimeVerified => sprintf('active; HTTP %d; %d native teaser nodes before/after; anonymous access; UI Patterns revista-listing style; Firefox 320/390/768/1024/1440 light/dark', $revistaNewsRuntimeDisplay['after_http_status'], $revistaNewsRuntimeDisplay['result_count_after']),
        $isRevistaNewsPilot => 'configured; Runtime/browser evidence not recorded',
        $homeEditorialRuntimeVerified => sprintf('active; HTTP %d; three curated native editorial rows; UI Patterns layout-grid; anonymous access allowed; Firefox 320/390/768/1024/1440 light/dark', $homeEditorialRuntimeDisplay['after_http_status']),
        $isHomeEditorialPilot => 'configured; Runtime/browser evidence not recorded',
        $albumPhotosRuntimeVerified => sprintf('active; HTTP %d; %d PhotoSwipe media images; UI Patterns layout-grid; anonymous published album', $albumPhotosRuntimeDisplay['after_http_status'], $albumPhotosRuntimeDisplay['result_count_after']),
        $isAlbumPhotosPilot => 'configured; Runtime/browser evidence not recorded',
        $isRevistaTeaserSdc => 'Theme adapter verified in source; route output not attributable to this display',
        default => 'not_observed',
      },
    ];
  }
}

foreach (glob($sync . '/core.entity_view_display.*.yml') ?: [] as $path) {
  $display = Yaml::parseFile($path);
  $enabled = $display['third_party_settings']['layout_builder']['enabled'] ?? FALSE;
  if ($enabled !== TRUE) {
    continue;
  }
  $id = (string) ($display['id'] ?? basename($path));
  $sections = $display['third_party_settings']['layout_builder']['sections'] ?? [];
  $layoutIds = array_values(array_map(
    static fn (array $section): string => (string) ($section['layout_id'] ?? ''),
    array_filter($sections, 'is_array'),
  ));
  $componentLayoutIds = array_values(array_filter(
    $layoutIds,
    static fn (string $layoutId): bool => str_starts_with($layoutId, 'ui_patterns:'),
  ));
  $allSectionsUseComponentLayout = $layoutIds !== [] && count($componentLayoutIds) === count($layoutIds);
  $runtimeConfigId = 'core.entity_view_display.' . $id;
  $runtimeLayout = $runtimeSnapshot['layout_builder_displays'][$runtimeConfigId] ?? NULL;
  $legacyLayoutIds = array_values(array_diff($layoutIds, $componentLayoutIds));
  $rows[] = [
    'id' => $id,
    'owner_module' => 'Drupal entity display',
    'config_path' => relative_path($root, $path),
    'consumer_type' => 'entity_view_display',
    'consumer_id' => (string) ($display['mode'] ?? ''),
    'display_plugin' => 'layout_builder',
    'row_plugin' => '',
    'style_plugin' => '',
    'ui_patterns_configured' => $allSectionsUseComponentLayout
      ? implode(',', array_unique(array_map(
        static fn (array $section): string => (string) ($section['layout_settings']['ui_patterns']['component_id'] ?? 'component_id_unset'),
        array_filter($sections, 'is_array'),
      )))
      : 'layout_builder_core_layout',
    'status' => $allSectionsUseComponentLayout ? 'UI_SUITE_NATIVE' : 'MIGRATION_IN_PROGRESS',
    'replacement_or_action' => $allSectionsUseComponentLayout
      ? 'Retain UI Patterns ComponentLayout SDC and preserve section regions, field blocks, access and cache contracts'
      : 'Migrate compatible Core Layout Builder sections to existing UI Patterns ComponentLayout SDCs; keep excluded displays documented',
    'risk_or_evidence_gap' => $allSectionsUseComponentLayout
      ? (is_array($runtimeLayout)
        ? (($runtimeLayout['render_strategy'] ?? '') === 'direct_sdc_template'
          ? 'Runtime rendered through the direct SDC template adapter; Layout Builder wrapper is bypassed; browser/cache and per-display access coverage remain scoped'
          : 'Runtime ComponentLayout output verified; browser/cache and per-display access coverage remain scoped')
        : 'ComponentLayout configured in Sync; Runtime render, access and cache not observed')
      : 'Core layout IDs still configured: ' . implode(', ', $legacyLayoutIds) . '; assess compatible SDC layout and the two documented exclusions',
    'rollback' => 'Restore captured display configuration; no entity/data changes',
    'detected_consumers' => '',
    'runtime_status' => is_array($runtimeLayout)
      ? (string) ($runtimeLayout['runtime_status'] ?? 'observed')
      : 'not_observed',
  ];
}

foreach (glob($sync . '/block.block.*.yml') ?: [] as $path) {
  $block = Yaml::parseFile($path);
  $id = (string) ($block['id'] ?? basename($path));
  $pluginId = (string) ($block['plugin'] ?? 'unknown');
  $styles = $block['third_party_settings']['ui_styles'] ?? [];
  $selectedStyles = [];
  foreach ($styles as $part => $settings) {
    foreach (($settings['selected'] ?? []) as $styleId => $classes) {
      $selectedStyles[] = $part . ':' . $styleId . '=' . $classes;
    }
  }
  $runtimeBlock = $runtimeSnapshot['block_styles'][$id] ?? NULL;
  $viewId = preg_match('/^views_block:([^:]+)-/', $pluginId, $matches) ? $matches[1] : NULL;
  $viewDisplayId = preg_match('/^views_block:[^:]+-(.+)$/', $pluginId, $matches) ? $matches[1] : NULL;
  $runtimeView = $viewId !== NULL ? ($runtimeViewDisplays[$viewId] ?? NULL) : NULL;
  $runtimeViewDisplay = is_array($runtimeView) && is_array($runtimeView['displays'] ?? NULL)
    && $viewDisplayId !== NULL ? ($runtimeView['displays'][$viewDisplayId] ?? NULL) : NULL;
  $viewStyleVerified = is_array($runtimeViewDisplay)
    && ($runtimeViewDisplay['status'] ?? '') === 'runtime_verified'
    && (is_string($runtimeViewDisplay['style_component'] ?? NULL) || is_string($runtimeViewDisplay['row_component'] ?? NULL) || is_string($runtimeViewDisplay['display_component'] ?? NULL));
  $blockStyleVerified = is_array($runtimeBlock)
    && ($runtimeBlock['status'] ?? '') === 'runtime_verified'
    && $selectedStyles !== [];
  $runtimeVerified = $blockStyleVerified || $viewStyleVerified;
  $owner = match (TRUE) {
    $viewId === 'aculta_news' => 'aculta_magazine',
    $viewId === 'aculta_events' => 'aculta_events',
    $viewId === 'aculta_gallery' => 'aculta_gallery',
    $viewId === 'aculta_forum_categories' => 'aculta_forum',
    $viewId === 'aculta_forum_public_topics' => 'aculta_forum',
    $viewId !== NULL => 'views:' . $viewId,
    str_starts_with($pluginId, 'system_menu_block:') => 'system',
    str_starts_with($pluginId, 'block_content:') => 'block_content',
    default => (string) ($block['provider'] ?? 'unresolved_from_config'),
  };
  /* Keep the block-placement evidence separate from View-owned visual evidence. */
  $blockUiStylesVerified = is_array($runtimeBlock)
    && ($runtimeBlock['status'] ?? '') === 'runtime_verified'
    && $selectedStyles !== [];
  $visibility = implode(',', array_map(
    static fn (string $key, array $condition): string => $key . '=' . json_encode($condition, JSON_UNESCAPED_SLASHES),
    array_keys($block['visibility'] ?? []),
    array_values($block['visibility'] ?? []),
  ));
  $isDonationSdcPlacement = in_array($pluginId, ['aculta_donation_quick', 'aculta_donation_donate', 'aculta_events_upcoming', 'aculta_institution_bar'], TRUE);
  $isNativeBlockPlacement = str_starts_with($pluginId, 'system_')
    || str_starts_with($pluginId, 'local_')
    || str_starts_with($pluginId, 'block_content:')
    || str_starts_with($pluginId, 'views_block:')
    || in_array($pluginId, ['help_block', 'page_title_block', 'system_branding_block', 'aculta_lms_agenda_proximos'], TRUE);
  $rows[] = [
    'id' => $id,
    'owner_module' => $owner,
    'config_path' => relative_path($root, $path),
    'consumer_type' => 'block_instance',
    'consumer_id' => $pluginId,
    'display_plugin' => 'block',
    'row_plugin' => '',
    'style_plugin' => $blockUiStylesVerified ? 'ui_styles_block' : ($viewStyleVerified ? 'ui_patterns_views' : ''),
    'ui_patterns_configured' => $blockUiStylesVerified ? implode(',', $selectedStyles) : ($viewStyleVerified ? 'style:' . ($runtimeViewDisplay['style_component'] ?? $runtimeViewDisplay['row_component'] ?? $runtimeViewDisplay['display_component']) : 'no_ui_styles_selection'),
    'status' => ($runtimeVerified || $isDonationSdcPlacement) ? 'UI_SUITE_NATIVE' : ($isNativeBlockPlacement ? 'FUNCTIONAL_NATIVE_EXCEPTION' : 'MIGRATION_IN_PROGRESS'),
    'replacement_or_action' => $runtimeVerified
      ? ($blockUiStylesVerified
        ? 'UI Styles Block applies existing ACULTA surface and spacing styles on the MAIN transparency block; preserve native Views output and block visibility'
        : 'View display applies its UI Patterns SDC to native row render arrays; preserve this block placement and its visibility/access/cache configuration')
      : ($isNativeBlockPlacement
        ? 'Native Core, content or Views block placement; presentation is owned by the block plugin, View or view mode. Preserve plugin data, visibility, access and cache'
        : 'Inspect rendered output and map the block to existing SDC/UI Styles integrations; preserve plugin data, visibility, access and cache'),
    'risk_or_evidence_gap' => $runtimeVerified
      ? ($blockUiStylesVerified
        ? 'Anonymous Runtime and Firefox verified at 320/390/768/1024/1440 in light/dark with keyboard focus; cache-hit and zoom 200% remain open'
        : 'Anonymous View output and Firefox verified at 320/390/768/1024/1440 in light/dark with visible H1/main and focus; cache-hit and zoom 200% remain open')
      : 'Runtime output, domain/path visibility, cache and visual replacement remain unverified; config visibility: ' . $visibility,
    'rollback' => 'Restore only this block configuration; no content data changes',
    'detected_consumers' => $pluginId,
    'runtime_status' => $runtimeVerified
      ? ($blockUiStylesVerified
        ? 'active; ' . ($runtimeBlock['region'] ?? 'region unknown') . '; ' . ($runtimeBlock['row_count'] ?? 'unknown') . ' rows; anonymous access; ' . ($runtimeBlock['evidence_manifest'] ?? 'browser evidence recorded')
        : 'active; View display ' . $viewId . ':' . $viewDisplayId . '; ' . ($runtimeViewDisplay['result_count_after'] ?? $runtimeViewDisplay['result_count'] ?? 'unknown') . ' results; anonymous access; UI Patterns output evidenced in runtime inventory')
      : 'not_observed',
  ];
}

// Inventory every custom Twig template individually. Templates that compose an
// SDC directly are native; other templates remain explicit migration work until
// their visual and functional contracts have a reviewed SDC/UI Suite mapping.
$templateRoots = [
  'aculta420_v2' => $theme . '/templates',
];
$themeSdcAdapters = [];
$themeTemplateIterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($theme . '/templates', FilesystemIterator::SKIP_DOTS),
);
foreach ($themeTemplateIterator as $themeTemplate) {
  if (!$themeTemplate->isFile() || !str_ends_with($themeTemplate->getFilename(), '.html.twig')) {
    continue;
  }
  $themeContents = (string) file_get_contents($themeTemplate->getPathname());
  preg_match_all("/aculta420_v2:([a-z0-9_-]+)/", $themeContents, $themeComponentMatches);
  $themeComponents = array_values(array_unique($themeComponentMatches[1]));
  if ($themeComponents !== []) {
    $themeSdcAdapters[$themeTemplate->getFilename()][] = [
      'path' => relative_path($root, $themeTemplate->getPathname()),
      'components' => $themeComponents,
    ];
  }
}
$moduleIterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($customModules, FilesystemIterator::SKIP_DOTS),
  RecursiveIteratorIterator::SELF_FIRST,
);
foreach ($moduleIterator as $directory) {
  if (!$directory->isDir() || $directory->getFilename() !== 'templates') {
    continue;
  }
  $templateRoot = $directory->getPathname();
  $relative = relative_path($root, $templateRoot);
  if (preg_match('#web/modules/custom/([^/]+)(?:/modules/([^/]+))?#', $relative, $matches) === 1) {
    $owner = $matches[2] ?? $matches[1];
    preg_match('#^web/modules/custom/[^/]+#', $relative, $moduleRootMatch);
    $nestedModulePath = substr($relative, strlen($moduleRootMatch[0] ?? ''));
    if (preg_match_all('#/modules/([^/]+)#', $nestedModulePath, $moduleMatches) > 0) {
      $owner = end($moduleMatches[1]);
    }
    $templateRoots[$owner] = $templateRoot;
  }
}
foreach ($templateRoots as $owner => $templateRoot) {
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.html.twig')) {
      continue;
    }
    $path = $file->getPathname();
    $relativePath = relative_path($root, $path);
    $contents = (string) file_get_contents($path);
    preg_match_all("/aculta420_v2:([a-z0-9_-]+)/", $contents, $componentMatches);
    $components = array_values(array_unique($componentMatches[1]));
    $isSdcAdapter = $components !== [];
    $isNeutralPortalButtonFallback = $relativePath === 'web/modules/custom/aculta_portal/templates/aculta-portal-button.html.twig'
      && !$isSdcAdapter;
    $isPortalComponentAdapter = $file->getFilename() === 'portal-component.html.twig';
    $isPortalComponentThemeAdapter = $isPortalComponentAdapter && str_starts_with($relativePath, 'web/themes/');
    $isNeutralPortalElement = $file->getFilename() === 'portal-element.html.twig';
    $isNeutralDonationWrapper = in_array($file->getFilename(), ['aculta-donation-summary.html.twig', 'aculta-donation-summary-container.html.twig'], TRUE);
    $isTermsModalAdapter = $file->getFilename() === 'aculta-portal-account-terms-modal.html.twig';
    $moduleThemeAdapters = str_starts_with($relativePath, 'web/modules/')
      ? ($themeSdcAdapters[$file->getFilename()] ?? [])
      : [];
    $isThemeAdaptedModuleFallback = $moduleThemeAdapters !== [] && !$isSdcAdapter;
    preg_match('/views-view(?:-fields|-unformatted)?--([a-z0-9_-]+)--([a-z0-9_-]+)/', $file->getFilename(), $viewMatches);
    $viewId = isset($viewMatches[1]) ? str_replace('-', '_', $viewMatches[1]) : '';
    $displayId = $viewMatches[2] ?? '';
    $viewDisplayUsesPatternsRow = FALSE;
    if (isset($viewMatches[1], $viewMatches[2])) {
      $viewPath = $sync . '/views.view.' . $viewId . '.yml';
      if (is_file($viewPath)) {
        $viewDefinition = Yaml::parseFile($viewPath);
        $viewDisplayUsesPatternsRow = ($viewDefinition['display'][$displayId]['display_options']['row']['type'] ?? '') === 'ui_patterns';
      }
    }
    $isBypassedViewTemplate = $viewDisplayUsesPatternsRow
      && preg_match('/^views-view-(?:fields|unformatted)--/', $file->getFilename()) === 1
      && !$isSdcAdapter;
    $viewConsumer = isset($viewMatches[1], $viewMatches[2])
      ? $viewId . ':' . $displayId
      : 'template suggestion; exact route/runtime consumers require review';
    $viewConfig = isset($viewMatches[1])
      ? 'config/sync/views.view.' . str_replace('-', '_', $viewMatches[1]) . '.yml'
      : '';
    if ($file->getFilename() === 'aculta-event-catalogue-card.html.twig') {
      $viewConsumer = 'aculta_events:aculta_event_catalogue_card; eventos_publicos:block_1';
      $viewConfig = 'config/sync/views.view.eventos_publicos.yml; web/modules/custom/aculta_portal/modules/aculta_events/aculta_events.module';
    }
    $rows[] = [
      'id' => 'template:' . $relativePath,
      'owner_module' => $owner,
      'config_path' => $relativePath . ($viewConfig !== '' ? '; ' . $viewConfig : ''),
      'consumer_type' => str_starts_with($relativePath, 'web/themes/') ? 'theme_template_override' : 'custom_module_template',
      'consumer_id' => $viewConsumer,
      'display_plugin' => 'twig-template-suggestion',
      'row_plugin' => '',
      'style_plugin' => '',
      'ui_patterns_configured' => $components !== []
        ? implode(',', $components)
        : ($isPortalComponentAdapter
          ? 'theme_adapter:empty-state,tabs,author-card,profile-details'
          : ($isTermsModalAdapter
            ? 'behavior_contract:data-dialog-title'
            : ($isNeutralDonationWrapper
            ? 'neutral_wrapper:content'
            : ($isNeutralPortalElement
            ? 'neutral_element:data-portal-ui'
            : ($isThemeAdaptedModuleFallback
              ? 'theme_adapter:' . implode(',', array_unique(array_merge(...array_column($moduleThemeAdapters, 'components'))))
              : 'not_detected_in_template'))))),
      'status' => $isSdcAdapter
        ? 'UI_SUITE_NATIVE'
        : ($isPortalComponentThemeAdapter
          ? 'UI_SUITE_NATIVE'
          : (($isPortalComponentAdapter || $isNeutralPortalElement || $isNeutralDonationWrapper || $isTermsModalAdapter || $isNeutralPortalButtonFallback)
          ? 'FUNCTIONAL_NATIVE_EXCEPTION'
          : ($isThemeAdaptedModuleFallback
            ? 'FUNCTIONAL_NATIVE_EXCEPTION'
            : ($isBypassedViewTemplate ? 'LEGACY_OBSOLETE' : 'MIGRATION_IN_PROGRESS')))),
      'replacement_or_action' => $isSdcAdapter
        ? 'Keep as thin adapter; directly composes ' . implode(', ', $components) . ' and preserves Drupal render contract'
        : ($isPortalComponentThemeAdapter
          ? 'Keep as the theme adapter; preprocess_aculta_portal_component maps the neutral name to empty-state, tabs, author-card or profile-details SDCs'
          : (($isPortalComponentAdapter || $isNeutralPortalElement || $isNeutralPortalButtonFallback)
          ? 'Keep only as the theme-independent functional fallback; presentation is owned by the ACULTA420 theme adapter'
          : ($isThemeAdaptedModuleFallback
            ? 'Keep as the theme-independent Drupal render fallback; matching ACULTA420 theme adapter owns presentation through ' . implode(', ', array_unique(array_merge(...array_column($moduleThemeAdapters, 'components')))) . ' SDC'
            : ($isBypassedViewTemplate
              ? 'Obsolete: the configured UI Patterns row renders this display through its SDC and bypasses this Views field/unformatted template; remove after authorized Runtime cutover confirms the matching Sync display'
              : 'Map this template suggestion and its data/access/cache contract to an existing SDC plus the appropriate UI Suite integration; do not remove before replacement proof')))),
      'risk_or_evidence_gap' => $isSdcAdapter
        ? 'SDC inclusion is source-verified; Runtime output, access and cache equivalence remain unverified'
        : ($isNeutralPortalButtonFallback
          ? 'PortalButton render element and theme hook are source-verified; fallback preserves semantic link/button behavior without design classes; active-theme rendering still needs Runtime output verification'
          : ($isThemeAdaptedModuleFallback
            ? 'Module fallback and matching V2 theme SDC adapter are source-verified; Runtime output is not reobserved in this inventory run'
            : ($isBypassedViewTemplate
              ? 'Config Sync declares ui_patterns row and SDC component for this display; current Runtime config and rendered output are not observed'
              : 'Template is an active custom presentation candidate; exact consumers, functional responsibilities and SDC mapping need file/API review'))),
      'rollback' => 'Restore this template from the immediately preceding commit; no entity data changes',
      'detected_consumers' => $isNeutralPortalButtonFallback
        ? 'PortalButton render element; ' . $viewConsumer . '; web/themes/custom/aculta420_v2/templates/aculta-portal-button.html.twig -> aculta420_v2:button'
        : ($isThemeAdaptedModuleFallback
          ? 'Module theme hook; ' . implode(', ', array_column($moduleThemeAdapters, 'path')) . ' -> ' . implode(',', array_unique(array_merge(...array_column($moduleThemeAdapters, 'components'))))
          : $viewConsumer),
      'runtime_status' => 'not_observed',
    ];
  }
}

$componentIds = [];
foreach (glob($theme . '/components/*/*.component.yml') ?: [] as $manifest) {
  $componentIds[basename(dirname($manifest))] = relative_path($root, $manifest);
}
$references = array_fill_keys(array_keys($componentIds), []);
$scanRoots = [
  $customModules,
  $theme . '/templates',
  $theme . '/src',
  $sync,
];
foreach ($scanRoots as $scanRoot) {
  if (!is_dir($scanRoot)) {
    continue;
  }
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'twig', 'yml', 'yaml', 'css', 'js'], TRUE)) {
      continue;
    }
    $contents = file_get_contents($file->getPathname());
    if (!is_string($contents)) {
      continue;
    }
    foreach ($componentIds as $componentId => $_manifest) {
      if (preg_match('/aculta420_v2:' . preg_quote($componentId, '/') . '\b/', $contents) === 1) {
        $references[$componentId][] = relative_path($root, $file->getPathname());
      }
    }
  }
}

foreach ($componentIds as $componentId => $manifest) {
  $consumers = array_values(array_unique($references[$componentId]));
  sort($consumers);
  $rows[] = [
    'id' => 'aculta420_v2:' . $componentId,
    'owner_module' => 'aculta420_v2',
    'config_path' => $manifest,
    'consumer_type' => 'sdc',
    'consumer_id' => $componentId,
    'display_plugin' => 'component',
    'row_plugin' => '',
    'style_plugin' => '',
    'ui_patterns_configured' => 'discoverable_sdc_definition',
    'status' => 'UI_SUITE_NATIVE',
    'replacement_or_action' => $consumers === [] ? 'Retain as internal/editorial catalog item; do not hide from UI Patterns Library' : 'Keep SDC; verify every detected consumer remains catalogued',
    'risk_or_evidence_gap' => count($consumers) . ' explicit component-ID reference files; Runtime discovery/catalog not verified',
    'rollback' => 'No removal; revert consumer integration independently',
    'detected_consumers' => implode(', ', $consumers),
    'runtime_status' => 'not_applicable',
  ];
}

$composer = json_decode((string) file_get_contents($root . '/composer.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), TRUE, flags: JSON_THROW_ON_ERROR);
$syncExtensions = Yaml::parseFile($sync . '/core.extension.yml');
$lockedPackages = [];
foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
  if (is_array($package) && isset($package['name'])) {
    $lockedPackages[$package['name']] = $package;
  }
}
$moduleInventory = [
  'drupal/ui_patterns' => [
    'module' => 'ui_patterns', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Expose all internal/editorial SDCs through UI Patterns Library and compose compatible Blocks, Views and Layout Builder displays.',
    'risk' => 'Runtime discovery, actual display assignments, access and cache behavior have not been rechecked in the current database.',
  ],
  'drupal/ui_patterns_ui' => [
    'module' => 'ui_patterns_ui', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Provide administrative UI for mapping existing SDCs to compatible Drupal consumers.',
    'risk' => 'Form access and discovered component catalog require Runtime verification.',
  ],
  'drupal/ui_patterns_blocks' => [
    'module' => 'ui_patterns_blocks', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use SDC-backed UI Patterns block plugins for compatible visual blocks; preserve native functional Drupal blocks.',
    'risk' => 'Sync enablement does not prove Runtime assignments or per-domain visibility.',
  ],
  'drupal/ui_patterns_field' => [
    'module' => 'ui_patterns_field', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Map compatible field output to existing SDCs through UI Patterns field formatters.',
    'risk' => 'Field formatter assignments and access/cache metadata have not been exhaustively inventoried.',
  ],
  'drupal/ui_patterns_field_formatters' => [
    'module' => 'ui_patterns_field_formatters', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Replace duplicated field markup with compatible existing SDC formatters.',
    'risk' => 'Per-field configuration and unsupported field types need runtime comparison.',
  ],
  'drupal/ui_patterns_layouts' => [
    'module' => 'ui_patterns_layouts', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Compose compatible Layout Builder regions from existing SDCs and keep the two documented exclusions.',
    'risk' => 'Twenty-two Sync displays are inventoried; override layouts and Runtime parity remain unverified.',
  ],
  'drupal/ui_patterns_library' => [
    'module' => 'ui_patterns_library', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Catalog every internal, reusable and editorial SDC with real previews restricted to administrators.',
    'risk' => 'Runtime catalog and real preview access matrix have not been revalidated.',
  ],
  'drupal/ui_patterns_views' => [
    'module' => 'ui_patterns_views', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Adopt UI Patterns independently at row, style and display levels after preserving filters, pager, sorting and access.',
    'risk' => '159 displays in Sync; four UI Patterns row/style/display levels are configured across the Albums and Forum Categories views. Most displays, cache-hit behavior and multi-page navigation still need review.',
  ],
  'drupal/ui_styles' => [
    'module' => 'ui_styles', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use registered UI Styles for reusable SDC, block, Layout Builder and Views visual choices.',
    'risk' => 'Existing UI Styles config is present but this run did not exhaustively compare plugin registrations and consumer forms.',
  ],
  'drupal/ui_styles_block' => [
    'module' => 'ui_styles_block', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Apply registered UI Styles to block, title and content parts; keep domain visibility and functional block plugins unchanged.',
    'risk' => 'One MAIN transparency block has a verified raised-surface/spacing selection. Remaining block placements need output mapping and risk review. Zoom 200% and cache-hit are not yet tested.',
  ],
  'drupal/ui_styles_library' => [
    'module' => 'ui_styles_library', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Expose the approved UI Styles catalog to authorized administrators.',
    'risk' => 'Enabled in Runtime but absent from Config Sync; library access and registered style inventory need review.',
  ],
  'drupal/ui_styles_page' => [
    'module' => 'ui_styles_page', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use UI Styles on page regions with available region attributes; keep institutional shell ownership in the theme.',
    'risk' => 'Runtime and Sync now both enable the module; region style assignments and visual effects still need authenticated output review.',
  ],
  'drupal/ui_styles_ckeditor5' => [
    'module' => 'ui_styles_ckeditor5', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Provide SDC surface, spacing, elevation and radius choices in revista_editorial through the official block plugin; UI Patterns CKEditor remains excluded.',
    'risk' => 'Enabled in Sync and Runtime for revista_editorial; editor output, stored markup and browser validation remain pending.',
    'configs' => ['config/sync/editor.editor.revista_editorial.yml', 'config/sync/filter.format.revista_editorial.yml'],
  ],
  'drupal/ui_styles_entity_status' => [
    'module' => 'ui_styles_entity_status', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Apply visual status styles through UI Styles while preserving moderation and entity access behavior.',
    'risk' => 'Enabled in Sync and Runtime with a global unpublished preview cue; upstream only adds classes to unpublished content entities and Layout Builder sections. Functional test passed, but real Runtime visual/access/cache review remains pending.',
  ],
  'drupal/ui_styles_layout_builder' => [
    'module' => 'ui_styles_layout_builder', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use UI Styles for compatible Layout Builder sections/components.',
    'risk' => 'Per-display and per-override assignments require runtime audit.',
  ],
  'drupal/ui_styles_ui_patterns' => [
    'module' => 'ui_styles_ui_patterns', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Expose approved style choices on UI Patterns components without duplicating component classes.',
    'risk' => 'Plugin registry and editor form behavior require integration tests.',
  ],
  'drupal/ui_styles_views' => [
    'module' => 'ui_styles_views', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Apply registered UI Styles to compatible Views displays.',
    'risk' => 'Views style assignments and resulting output have not been compared in Runtime.',
  ],
  'drupal/ui_skins' => [
    'module' => 'ui_skins',
    'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Portal DomainSkinResolver + five UI Skins CSS-variable plugins/scopes selected by Drupal Domain ID; theme consumes data-skin and data-theme',
    'risk' => 'Runtime has the Domain-to-skin map and five UI Skins CSS-variable settings; A/B/A verified Links Ruderalis against other-domain Skunk values. Contrast and visual acceptance across ten domains, modes and breakpoints remain pending.',
  ],
  'drupal/ui_icons' => [
    'module' => 'ui_icons',
    'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Keep the three existing local Phosphor Regular, Phosphor Duotone, and Fluent Emoji packs; preserve pack ownership and use UI Icons adapters only where configured',
    'risk' => 'UI Icons 2.1.0 is enabled in Sync and Runtime. Menus restrict selection to Phosphor; editorial emoji remains in CKEditor. Runtime selector now matches Sync; optional Runtime submodules still differ from Sync. Picker/menu visual and accessibility acceptance remains pending.',
  ],
  'drupal/ui_patterns_field_group' => [
    'module' => 'ui_patterns_field_group',
    'status' => 'BLOCKED_UPSTREAM',
    'replacement' => 'Use only after Drupal 12 metadata/API compatibility and security acceptance; then map compatible field groups to existing SDCs',
    'risk' => '2.0.x-dev SHA is locked and the module is pre-enabled in Runtime, but Drupal.org explicitly says it is outside the Security Advisory Policy; installed info.yml/lock require Core ^9/^10/^11 (no Drupal 12), and Field Group compatibility is unverified. Do not add consumers; removal requires authorized operation.',
  ],
  'drupal/ui_patterns_ckeditor5' => [
    'module' => 'ui_patterns_ckeditor5',
    'status' => 'LEGACY_OBSOLETE',
    'replacement' => 'Prohibited integration with no repository consumers, no compatibility path and no replacement required. Runtime uninstall is an administrative operation and remains DEFERRED pending explicit authorization.',
    'risk' => 'Current Runtime recheck confirms Enabled while Sync and repository consumers are absent. ADR-055 prohibits this integration; disable/uninstall remains DEFERRED pending explicit operational authorization.',
  ],
  'drupal/ui_icons_menu' => [
    'module' => 'ui_icons_menu', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use the official menu adapter with existing Phosphor packs and menu-owned assignments.',
    'risk' => 'Main and account menus configure icon_picker and permit only Phosphor Regular/Duotone. Picker form is available in Runtime; visual/accessibility acceptance remains pending.',
  ],
  'drupal/ui_icons_picker' => [
    'module' => 'ui_icons_picker', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use Picker on menu-owned settings and UI Patterns sources where configured.',
    'risk' => 'Picker 2.1.0 is enabled in Sync/Runtime; menu configs and UI Patterns source select icon_picker. Runtime selector was aligned through targeted partial import; visual/accessibility acceptance remains pending.',
  ],
  'drupal/ui_icons_patterns' => [
    'module' => 'ui_icons_patterns', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Expose UI Icons through the native UI Patterns icon source on compatible SDC props.',
    'risk' => 'Module/API verified in UI Icons 2.1.0; Sync and Runtime select icon_picker. No SDC icon-prop consumer or visual/accessibility acceptance is recorded yet.',
  ],
  'drupal/ui_icons_field' => [
    'module' => 'ui_icons_field', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use the official icon field formatter for compatible data-owned icon fields.',
    'risk' => 'Field ownership and formatter assignments need inventory before migration.',
  ],
  'drupal/ui_icons_library' => [
    'module' => 'ui_icons_library', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Expose only approved icon packs through the administrative UI Icons library.',
    'risk' => 'Access policy and pack ownership need Runtime validation.',
  ],
  'drupal/ui_icons_canvas' => [
    'module' => 'ui_icons_canvas', 'status' => 'FUNCTIONAL_NATIVE_EXCEPTION',
    'replacement' => 'Keep disabled unless Canvas becomes an approved editor; use existing CKEditor and SDC workflows.',
    'risk' => 'Optional integration, currently disabled in Runtime; no Canvas integration is in scope.',
  ],
  'drupal/ui_icons_ckeditor5' => [
    'module' => 'ui_icons_ckeditor5', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use only the approved UI Icons editor integration if it is required; UI Patterns CKEditor integration stays prohibited.',
    'risk' => 'Enabled in Runtime but absent from Sync; editor formats and pack access need review.',
  ],
  'drupal/ui_icons_field_link_attributes' => [
    'module' => 'ui_icons_field_link_attributes', 'status' => 'FUNCTIONAL_NATIVE_EXCEPTION',
    'replacement' => 'Keep disabled unless a compatible Field Link Attributes use case is approved.',
    'risk' => 'Optional integration, disabled in Runtime; no consumer identified.',
  ],
  'drupal/ui_icons_field_linkit' => [
    'module' => 'ui_icons_field_linkit', 'status' => 'FUNCTIONAL_NATIVE_EXCEPTION',
    'replacement' => 'Keep disabled unless Linkit icon selection is explicitly required.',
    'risk' => 'Optional integration, disabled in Runtime; no consumer identified.',
  ],
  'drupal/ui_icons_field_linkit_attributes' => [
    'module' => 'ui_icons_field_linkit_attributes', 'status' => 'FUNCTIONAL_NATIVE_EXCEPTION',
    'replacement' => 'Keep disabled unless Linkit attribute integration is explicitly required.',
    'risk' => 'Optional integration, disabled in Runtime; no consumer identified.',
  ],
  'drupal/ui_icons_font' => [
    'module' => 'ui_icons_font', 'status' => 'FUNCTIONAL_NATIVE_EXCEPTION',
    'replacement' => 'Keep disabled; current project uses official Phosphor/Fluent packs and does not need generated icon fonts.',
    'risk' => 'Optional integration, disabled in Runtime; font generation would duplicate pack delivery.',
  ],
  'drupal/ui_icons_media' => [
    'module' => 'ui_icons_media', 'status' => 'FUNCTIONAL_NATIVE_EXCEPTION',
    'replacement' => 'Keep disabled unless media entities own icon selections.',
    'risk' => 'Optional integration, disabled in Runtime; no media icon field consumer identified.',
  ],
  'drupal/ui_icons_popover' => [
    'module' => 'ui_icons_popover', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use only where the Runtime configuration explicitly selects icon_popover; keep Sync selection intentional and consistent.',
    'risk' => 'Enabled in Runtime but absent from Sync and unused by the current UI Patterns selector. Removing it requires a reviewed module/config delta; no integration removal was performed.',
  ],
  'drupal/ui_icons_text' => [
    'module' => 'ui_icons_text', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use official text formatter where existing icon text fields require rendering.',
    'risk' => 'Enabled in Runtime; field assignments and data ownership need inventory.',
  ],
  'drupal/ui_patterns_legacy' => [
    'module' => 'ui_patterns_legacy', 'status' => 'LEGACY_OBSOLETE',
    'replacement' => 'No consumer; remain disabled and remove package-level legacy integration only if upstream API permits safe removal.',
    'risk' => 'Disabled in Runtime; confirm zero dependent config before a later package cleanup.',
  ],
];
foreach ($moduleInventory as $packageName => $metadata) {
  $package = $lockedPackages[$packageName] ?? NULL;
  $required = isset($composer['require'][$packageName]) || isset($composer['require-dev'][$packageName]);
  $moduleVersion = is_array($package) ? (string) ($package['version'] ?? 'not locked') : 'not locked';
  $moduleReference = is_array($package) ? (string) ($package['source']['reference'] ?? $package['dist']['reference'] ?? 'no reference') : 'no lock reference';
  $enabledInSync = isset($syncExtensions['module'][$metadata['module']]);
  $risk = $metadata['risk'];
  if ($enabledInSync && str_contains($risk, 'absent from Sync')) {
    $risk = str_replace('module is absent from Sync', 'module is enabled in Sync', $risk);
    $risk = str_replace('Enabled in Sync only', 'Enabled in Sync', $risk);
  }
  elseif (!$enabledInSync && str_contains($risk, 'enabled in Sync')) {
    $risk = str_replace('module is enabled in Sync', 'module is absent from Sync', $risk);
  }
  $risk = str_replace(
    [
      'Runtime enablement differs from Sync',
      'Enabled in Runtime but absent from Config Sync',
      'Runtime plugin is Disabled',
      'disabled in Runtime',
      'module is disabled in Runtime',
    ],
    [
      'historical Runtime snapshot differs from Sync; current Runtime is not observed',
      'historical Runtime snapshot was Enabled while absent from Config Sync; current Runtime is not observed',
      'historical Runtime snapshot recorded Disabled; current Runtime is not observed',
      'historical Runtime snapshot recorded Disabled; current Runtime is not observed',
      'historical Runtime snapshot recorded Disabled; current Runtime is not observed',
    ],
    $risk,
  );
  $packageOwner = match (TRUE) {
    str_starts_with($packageName, 'drupal/ui_patterns') => 'drupal/ui_patterns',
    str_starts_with($packageName, 'drupal/ui_styles') => 'drupal/ui_styles',
    str_starts_with($packageName, 'drupal/ui_icons') => 'drupal/ui_icons',
    default => $packageName,
  };
  $ownerPackage = $lockedPackages[$packageOwner] ?? NULL;
  $ownerVersion = is_array($ownerPackage) ? (string) ($ownerPackage['version'] ?? $moduleVersion) : $moduleVersion;
  $ownerReference = is_array($ownerPackage) ? (string) ($ownerPackage['source']['reference'] ?? $ownerPackage['dist']['reference'] ?? $moduleReference) : $moduleReference;
  $configPaths = array_merge(
    ['composer.json', 'composer.lock', 'config/sync/core.extension.yml'],
    $metadata['configs'] ?? [],
    [relative_path($root, $runtimeSnapshotPath)],
  );
  $rows[] = [
    'id' => $metadata['module'],
    'owner_module' => 'Composer / ' . $metadata['module'],
    'config_path' => implode('; ', $configPaths),
    'consumer_type' => 'ui_suite_integration',
    'consumer_id' => $metadata['module'] . ' (package ' . $packageOwner . ' ' . $ownerVersion . ' @ ' . $ownerReference . '; lock=' . $moduleVersion . ' @ ' . $moduleReference . ')',
    'display_plugin' => $metadata['module'],
    'row_plugin' => '',
    'style_plugin' => '',
    'ui_patterns_configured' => $enabledInSync ? 'enabled_in_sync' : 'absent_from_sync',
    'status' => $metadata['status'],
    'replacement_or_action' => $metadata['replacement'],
    'risk_or_evidence_gap' => 'Direct Composer requirement: ' . ($required ? 'yes' : 'no') . '. ' . $risk,
    'rollback' => 'Revert scoped code/config changes; no entity data migration',
    'detected_consumers' => '',
    'runtime_status' => array_key_exists($metadata['module'], $runtimeStatuses)
      ? (is_array($runtimeStatuses[$metadata['module']])
        ? 'observed_' . $runtimeObservedDate . ':' . ($runtimeStatuses[$metadata['module']]['status'] ?? 'unknown') . '; version=' . ($runtimeStatuses[$metadata['module']]['version'] ?? 'unknown')
        : 'observed_' . $runtimeObservedDate . ':' . $runtimeStatuses[$metadata['module']])
      : 'current_runtime=not_observed',
  ];
}

$rows[] = [
  'id' => 'aculta420_v2:ui_skins_domain_visual_engine',
  'owner_module' => 'aculta420_v2',
  'config_path' => 'config/sync/aculta_portal.visual_skins.yml; config/sync/aculta420_v2.settings.yml; web/themes/custom/aculta420_v2/aculta420_v2.ui_skins.css_variables.yml; web/modules/custom/aculta_portal/src/Appearance/DomainSkinResolver.php; web/themes/custom/aculta420_v2/src/Hook/SurfaceSettingsHooks.php',
  'consumer_type' => 'theme_settings_visual_engine',
  'consumer_id' => 'aculta_portal.visual_skins',
  'display_plugin' => 'ui_skins_css_variables',
  'row_plugin' => '',
  'style_plugin' => '',
  'ui_patterns_configured' => 'ui_skins_code_and_sync_declared',
  'status' => 'MIGRATION_IN_PROGRESS',
  'replacement_or_action' => 'Portal maps ten Drupal Domain IDs to skin IDs; UI Skins declares light/dark CSS variables; purpose palette form, config key, class, selectors and tokens removed',
  'risk_or_evidence_gap' => 'Runtime has the active Domain-to-skin map and five CSS-variable settings; Links A/B/A values are verified. Full contrast, cache-hit isolation and visual acceptance across ten domains remain deferred',
  'rollback' => 'Revert the scoped code/config commit; do not restore purpose palette behavior or aliases',
  'detected_consumers' => 'DomainSkinResolver, AppearanceHooks, SurfaceSettingsHooks, UI Skins PageTop scopes',
  'runtime_status' => 'ui_skins=Enabled 1.2.1; map active (Links=Ruderalis, default=Skunk); five UI Skins CSS-variable definitions/scopes active',
];

usort(
  $rows,
  static fn (array $a, array $b): int => [$a['consumer_type'], $a['id'], $a['consumer_id']]
    <=> [$b['consumer_type'], $b['id'], $b['consumer_id']],
);
$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0775, TRUE) && !is_dir($directory)) {
  throw new RuntimeException('Unable to create inventory output directory.');
}
$handle = fopen($output, 'wb');
if ($handle === FALSE) {
  throw new RuntimeException('Unable to write inventory CSV.');
}
fputcsv($handle, $columns, ';');
foreach ($rows as $row) {
  fputcsv($handle, array_map(static fn (string $column): string => (string) $row[$column], $columns), ';');
}
fclose($handle);
printf("Wrote %d inventory rows to %s\n", count($rows), relative_path($root, $output));

/**
 * Returns a repository-relative path for an inventory record.
 */
function relative_path(string $root, string $path): string {
  return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
}
