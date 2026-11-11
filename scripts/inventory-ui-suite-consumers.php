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
    $patternsConfig = is_array($rowPatterns) && !empty($rowPatterns['component_id'])
      ? $rowPatterns
      : NULL;
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
    $runtimeView = $runtimeViewDisplays[$viewId] ?? NULL;
    $albumsRuntimeVerified = $isAlbumsViewsPilot
      && is_array($runtimeView)
      && ($runtimeView['status'] ?? '') === 'runtime_verified';
    $forumCategoryLegacyTemplatesRemoved = $isForumCategoryPilot
      && !is_file($theme . '/templates/views/views-view-fields--aculta-forum-categories--' . $displayId . '.html.twig')
      && !is_file($theme . '/templates/views/views-view-unformatted--aculta-forum-categories--' . $displayId . '.html.twig')
      && !is_file($theme . '/templates/views/views-view--aculta-forum-categories--' . $displayId . '.html.twig');
    $rows[] = [
      'id' => $viewId,
      'owner_module' => $viewId === 'aculta_albuns' ? 'aculta_gallery' : 'unresolved_from_config',
      'config_path' => relative_path($root, $path),
      'consumer_type' => 'view_display',
      'consumer_id' => (string) $displayId,
      'display_plugin' => (string) ($display['display_plugin'] ?? ''),
      'row_plugin' => $rowPlugin,
      'style_plugin' => $stylePlugin,
      'ui_patterns_configured' => $components !== [] ? implode(',', $components) : 'not_detected_by_plugin_id_scan',
      'status' => $albumsRuntimeVerified ? 'UI_SUITE_NATIVE' : 'MIGRATION_IN_PROGRESS',
      'replacement_or_action' => $isAlbumsViewsPilot
        ? 'Compose this inherited View level with UI Patterns SDCs; preserve native row fields, filters, sort, pager and access'
        : ($isForumCategoryPilot
        ? ($forumCategoryLegacyTemplatesRemoved
          ? 'UI Patterns row SDC cutover is defined in Sync/install; source field/wrapper templates removed; retain Views query, grouping, pager and access contracts'
          : 'Pilot: replace duplicated row field templates with the forum-category SDC; retain Views query, grouping, pager and access contracts')
        : 'Inventory candidate only; inspect row, style and display independently before migration'),
      'risk_or_evidence_gap' => $isAlbumsViewsPilot
        ? ($albumsRuntimeVerified
          ? 'Runtime/render/browser verified at 1280/320 light/dark; pager navigation, keyboard, zoom 200% and cache-hit variation remain open'
          : 'Sync defines row/style/display composition; targeted Runtime import and output, permission, pager/cache and browser comparison remain pending')
        : ($isForumCategoryPilot
        ? ($forumCategoryLegacyTemplatesRemoved
          ? 'Sync/install declare the UI Patterns row, but Runtime still has row: fields; authorized import and before/after rendered output, access/cache and browser evidence are pending'
          : 'Config Sync and install defaults use UI Patterns row; Runtime cutover, rendered output, access/cache comparison and browser evidence are pending')
        : 'No UI Patterns row/style/display plugin ID detected in Sync; Runtime output, access and cache still unverified'),
      'rollback' => 'Revert only display config after before/after result comparison',
      'detected_consumers' => '',
      'runtime_status' => $albumsRuntimeVerified
        ? sprintf('active; HTTP %d; %d results before/after; anonymous access allowed; browser 1280/320 light/dark', $runtimeView['after_http_status'], $runtimeView['result_count_after'])
        : 'not_observed',
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
        : ($isThemeAdaptedModuleFallback
          ? 'theme_adapter:' . implode(',', array_unique(array_merge(...array_column($moduleThemeAdapters, 'components'))))
          : 'not_detected_in_template'),
      'status' => $isSdcAdapter
        ? 'UI_SUITE_NATIVE'
        : ($isNeutralPortalButtonFallback
          ? 'FUNCTIONAL_NATIVE_EXCEPTION'
          : ($isThemeAdaptedModuleFallback
            ? 'FUNCTIONAL_NATIVE_EXCEPTION'
            : ($isBypassedViewTemplate ? 'LEGACY_OBSOLETE' : 'MIGRATION_IN_PROGRESS'))),
      'replacement_or_action' => $isSdcAdapter
        ? 'Keep as thin adapter; directly composes ' . implode(', ', $components) . ' and preserves Drupal render contract'
        : ($isNeutralPortalButtonFallback
          ? 'Keep only as the theme-independent functional fallback for aculta_portal_button; visual rendering is owned by the ACULTA420 SDC adapter'
          : ($isThemeAdaptedModuleFallback
            ? 'Keep as the theme-independent Drupal render fallback; matching ACULTA420 theme adapter owns presentation through ' . implode(', ', array_unique(array_merge(...array_column($moduleThemeAdapters, 'components')))) . ' SDC'
            : ($isBypassedViewTemplate
              ? 'Obsolete: the configured UI Patterns row renders this display through its SDC and bypasses this Views field/unformatted template; remove after authorized Runtime cutover confirms the matching Sync display'
              : 'Map this template suggestion and its data/access/cache contract to an existing SDC plus the appropriate UI Suite integration; do not remove before replacement proof'))),
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
    'risk' => '159 displays in Sync; none use UI Patterns row/style/display plugin IDs; Runtime and output comparisons are pending.',
  ],
  'drupal/ui_styles' => [
    'module' => 'ui_styles', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use registered UI Styles for reusable SDC, block, Layout Builder and Views visual choices.',
    'risk' => 'Existing UI Styles config is present but this run did not exhaustively compare plugin registrations and consumer forms.',
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
    'risk' => 'Enabled in Sync only for revista_editorial; Runtime plugin is Disabled. Config import and editor/browser validation await explicit operational authorization.',
    'configs' => ['config/sync/editor.editor.revista_editorial.yml', 'config/sync/filter.format.revista_editorial.yml'],
  ],
  'drupal/ui_styles_entity_status' => [
    'module' => 'ui_styles_entity_status', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Apply visual status styles through UI Styles while preserving moderation and entity access behavior.',
    'risk' => 'Assignments across entity displays and moderation forms need inventory and output comparison.',
  ],
  'drupal/ui_styles_block' => [
    'module' => 'ui_styles_block', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use UI Styles on compatible block instances and templates.',
    'risk' => 'Runtime block assignments and domain overrides are not represented by Sync alone.',
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
    'risk' => 'Module is enabled and plugin discovery is source/runtime verified; active Runtime lacks aculta_portal.visual_skins and theme third-party CSS-variable settings, although both exist in Sync. Config import, CSS output, cache separation and visual acceptance remain pending.',
  ],
  'drupal/ui_icons' => [
    'module' => 'ui_icons',
    'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Keep the current Phosphor and Fluent Emoji packs and validate UI Icons menu/picker/pattern adapters against the locked 2.1.x API',
    'risk' => 'Package is directly locked but module is absent from Sync; Runtime enablement differs from Sync and pack data ownership must remain with each pack owner',
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
    'replacement' => 'Render menu icons through the official UI Icons formatter/adapters and existing icon packs.',
    'risk' => 'Pack ownership, menu assignments and Runtime enablement need direct verification.',
  ],
  'drupal/ui_icons_picker' => [
    'module' => 'ui_icons_picker', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Use the official picker for editorial/admin selection while preserving pack ownership and access.',
    'risk' => 'Picker forms and allowed pack inventory have not been verified in Runtime.',
  ],
  'drupal/ui_icons_patterns' => [
    'module' => 'ui_icons_patterns', 'status' => 'MIGRATION_IN_PROGRESS',
    'replacement' => 'Expose UI Icons through UI Patterns props on compatible SDCs.',
    'risk' => 'UI Icons and UI Patterns integration API/assignments require focal validation.',
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
    'replacement' => 'Use the official popover integration for existing icon selection forms where access permits.',
    'risk' => 'Enabled in Runtime but absent from Sync; picker usage and pack permissions need review.',
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
        ? 'observed_2026-11-10:' . ($runtimeStatuses[$metadata['module']]['status'] ?? 'unknown') . '; version=' . ($runtimeStatuses[$metadata['module']]['version'] ?? 'unknown')
        : 'observed_2026-11-10:' . $runtimeStatuses[$metadata['module']])
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
  'risk_or_evidence_gap' => 'Runtime confirms UI Skins enabled and five CSS variables discovered, but active theme settings and Domain-to-skin map are absent; both are only in Sync. Authorized import, warmed cache isolation, contrast and browser acceptance remain deferred',
  'rollback' => 'Revert the scoped code/config commit; do not restore purpose palette behavior or aliases',
  'detected_consumers' => 'DomainSkinResolver, AppearanceHooks, SurfaceSettingsHooks, UI Skins PageTop scopes',
  'runtime_status' => 'ui_skins=Enabled 1.2.1; map and theme CSS-variable settings absent from active config',
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
