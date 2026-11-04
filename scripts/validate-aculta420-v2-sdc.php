<?php

/**
 * @file
 * Validates discovery, strict schemas, assets, and rendering of V2 SDCs.
 *
 * Run with: drush scr scripts/validate-aculta420-v2-sdc.php.
 */

declare(strict_types=1);

$theme_root = DRUPAL_ROOT . '/themes/custom/aculta420_v2';
$theme_info = file_get_contents($theme_root . '/aculta420_v2.info.yml');
$theme_libraries = file_get_contents($theme_root . '/aculta420_v2.libraries.yml');
if (!str_contains($theme_info, "base theme: false") || preg_match('/base theme:\s*[\"\']?(?:bootstrap5|barrio)/i', $theme_info)) {
  throw new RuntimeException('V2 must remain a standalone theme without a Bootstrap or Barrio base theme.');
}
if (preg_match('/bootstrap5|bootstrap\.bundle|popper|data-bs-|core\/jquery/i', $theme_libraries)) {
  throw new RuntimeException('V2 libraries must not declare Bootstrap, Popper, data-bs, or jQuery dependencies.');
}
$source_files = new AppendIterator();
foreach (['components', 'templates', 'js', 'src'] as $source_directory) {
  $path = $theme_root . '/' . $source_directory;
  if (is_dir($path)) {
    $source_files->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)));
  }
}
foreach ($source_files as $source_file) {
  if (!$source_file->isFile() || !preg_match('/\.(?:twig|css|js)$/', $source_file->getFilename())) {
    continue;
  }
  $source = file_get_contents($source_file->getPathname());
  $framework_class = '(?:row|col-(?:xs|sm|md|lg|xl|xxl)-\d+|d-flex|container-fluid|mb-\d+|form-control|form-select)';
  $has_framework_class = preg_match('/class\s*=\s*[\"\'][^\"\']*\b' . $framework_class . '\b/i', $source)
    || preg_match('/(?:^|[\s,{])\.' . $framework_class . '(?=[\s:{,#]|$)/im', $source);
  if (preg_match('/bootstrap5|bootstrap\.bundle|popper|data-bs-|core\/jquery|core\/drupal\.dialog|\bjQuery\b/i', $source)
    || preg_match('/(?:class\s*=\s*[\"\'][^\"\']*|(?:^|[\s,{])\.)\bbtn\b/i', $source)
    || $has_framework_class) {
    throw new RuntimeException(sprintf('Framework-owned classes or attributes are forbidden in %s.', $source_file->getPathname()));
  }
}
$component_ids = [
  'audio-player',
  'alerts-trigger',
  'badge',
  'button',
  'cart-offcanvas',
  'card-cultural',
  'filter-chips',
  'hero-banner',
  'map-popup',
  'messenger-trigger',
  'multidomain-bar',
  'navigation-bar',
  'pagination',
  'status-messages',
];
$manager = \Drupal::service('plugin.manager.sdc');
$validator = \Drupal::service('Drupal\Core\Theme\Component\ComponentValidator');
$renderer = \Drupal::service('renderer');
$definitions = $manager->getDefinitions();
$samples = [
  'audio-player' => ['#props' => ['src' => '/files/sample.mp3', 'title' => 'Sessão cultural']],
  'alerts-trigger' => ['#props' => ['href' => '/avisos/resumo', 'count' => 0]],
  'badge' => ['#props' => ['label' => 'Cultura', 'variant' => 'primary']],
  'button' => ['#props' => ['text' => 'Abrir', 'url' => '/destino', 'variant' => 'primary']],
  'cart-offcanvas' => ['#props' => ['count' => 0, 'empty' => TRUE, 'url' => '/carrinho']],
  'card-cultural' => [
    '#props' => ['title' => 'Evento cultural', 'category' => 'Cultura', 'variant' => 'compact'],
    '#slots' => ['image' => '', 'actions' => ''],
  ],
  'filter-chips' => [
    '#props' => [
      'items' => [
        ['label' => 'Todos', 'url' => '/todos', 'is_active' => TRUE, 'count' => 2],
      ],
    ],
  ],
  'hero-banner' => [
    '#props' => ['title' => 'Agenda cultural', 'cta_text' => 'Ver eventos', 'cta_url' => '/eventos'],
    '#slots' => ['background_media' => ''],
  ],
  'map-popup' => [
    '#props' => ['title' => 'Centro cultural', 'category' => 'Cultura', 'address' => 'Centro'],
    '#slots' => ['thumbnail' => ''],
  ],
  'messenger-trigger' => ['#props' => ['href' => '/mensagens/resumo', 'count' => 0]],
  'multidomain-bar' => [
    '#props' => [
      'brand_home_url' => '/',
      'logo_base_url' => '/themes/custom/aculta420_v2/assets/logos',
      'logo_alt' => 'ACULTA',
      'cart_url' => '/carrinho',
      'cart_count' => 0,
      'messages_url' => '/mensagens/resumo',
      'messages_count' => 0,
      'alerts_url' => '/avisos/resumo',
      'alerts_count' => 0,
      'links' => [['title' => 'ACULTA', 'url' => '/']],
    ],
    '#slots' => ['search' => ''],
  ],
  'navigation-bar' => [
    '#props' => ['site_name' => 'ACULTA', 'menu_id' => 'v2-sdc-validation-menu'],
    '#slots' => ['menu_items' => '<ul><li><a href="/">Início</a></li></ul>', 'utility_items' => ''],
  ],
  'pagination' => [
    '#props' => [
      'current_page' => 1,
      'total_pages' => 2,
      'links' => [['url' => '/?page=0', 'label' => '1', 'is_current' => TRUE]],
    ],
  ],
  'status-messages' => ['#props' => ['type' => 'status', 'messages' => ['Validação concluída.']]],
];

foreach ($component_ids as $component_id) {
  $plugin_id = 'aculta420_v2:' . $component_id;
  if (!isset($definitions[$plugin_id])) {
    throw new RuntimeException(sprintf('SDC not discovered: %s', $plugin_id));
  }
  $definition = $definitions[$plugin_id];
  $validator->validateDefinition($definition, TRUE);

  foreach (['component.yml', 'twig', 'css'] as $suffix) {
    $extension = $suffix === 'component.yml' ? '.component.yml' : '.' . $suffix;
    $path = $theme_root . '/components/' . $component_id . '/' . $component_id . $extension;
    if (!is_file($path)) {
      throw new RuntimeException(sprintf('Missing SDC asset: %s', $path));
    }
  }
  if ($component_id === 'navigation-bar' && !is_file($theme_root . '/components/navigation-bar/navigation-bar.js')) {
    throw new RuntimeException('Navigation behavior file is missing.');
  }
  if ($component_id === 'navigation-bar' && !in_array('core/once', $definition['library']['dependencies'] ?? [], TRUE)) {
    throw new RuntimeException('Navigation behavior must depend on core/once.');
  }
  if ($component_id === 'navigation-bar') {
    $javascript = file_get_contents($theme_root . '/components/navigation-bar/navigation-bar.js');
    $template = file_get_contents($theme_root . '/components/navigation-bar/navigation-bar.twig');
    foreach (['Drupal.behaviors.acultaNavigation', "once('acultaNavigation'", "event.key === 'Escape'"] as $required_code) {
      if (!str_contains($javascript, $required_code)) {
        throw new RuntimeException(sprintf('Navigation behavior is missing: %s', $required_code));
      }
    }
    foreach (['aria-expanded="false"', 'aria-controls=', 'role="navigation"'] as $required_markup) {
      if (!str_contains($template, $required_markup)) {
        throw new RuntimeException(sprintf('Navigation markup is missing: %s', $required_markup));
      }
    }
  }

  $build = [
    '#type' => 'component',
    '#component' => $plugin_id,
    '#props' => $samples[$component_id]['#props'],
    '#slots' => $samples[$component_id]['#slots'] ?? [],
  ];
  $output = (string) $renderer->renderInIsolation($build);
  if ($output === '') {
    throw new RuntimeException(sprintf('Empty SDC render: %s', $plugin_id));
  }
  print $plugin_id . " PASS\n";
}

print sprintf("ACULTA420 V2 SDC: PASS (%d componentes; descoberta, schema estrito, assets e render isolado)\n", count($component_ids));
