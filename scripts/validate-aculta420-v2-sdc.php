<?php

/**
 * @file
 * Validates discovery, strict schemas, assets, and rendering of V2 SDCs.
 *
 * Run with: drush scr scripts/validate-aculta420-v2-sdc.php.
 */

declare(strict_types=1);

use Drupal\Core\Render\Markup;

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
  if ($source_file->getExtension() === 'twig'
    && !str_ends_with($source_file->getPathname(), '/components/icon/icon.twig')
    && preg_match('/<svg\b/i', $source)) {
    throw new RuntimeException(sprintf('Interface SVGs must use the icon SDC: %s', $source_file->getPathname()));
  }
  $framework_class = '(?:row|col-(?:xs|sm|md|lg|xl|xxl)-\d+|d-flex|container-fluid|mb-\d+|form-control|form-select)';
  $has_framework_class = preg_match('/class\s*=\s*[\"\'][^\"\']*(?<![a-z0-9_-])' . $framework_class . '(?![a-z0-9_-])[^\"\']*[\"\']/i', $source)
    || preg_match('/(?:^|[\s,{])\.' . $framework_class . '(?=[\s:{,#]|$)/im', $source);
  if (preg_match('/bootstrap5|bootstrap\.bundle|popper|data-bs-|core\/jquery|core\/drupal\.dialog|\bjQuery\b/i', $source)
    || preg_match('/(?:class\s*=\s*[\"\'][^\"\']*|(?:^|[\s,{])\.)\bbtn\b/i', $source)
    || $has_framework_class) {
    throw new RuntimeException(sprintf('Framework-owned classes or attributes are forbidden in %s.', $source_file->getPathname()));
  }
}
$component_ids = [
  'account-menu',
  'author-card',
  'audio-player',
  'alerts-trigger',
  'badge',
  'breadcrumb',
  'button',
  'cart-offcanvas',
  'card-cultural',
  'discussion-comment',
  'filter-chips',
  'forum-category',
  'forum-topic',
  'hero-banner',
  'icon',
  'map-popup',
  'messenger-trigger',
  'multidomain-bar',
  'navigation-bar',
  'pagination',
  'revista-listing',
  'revista-publication',
  'revista-teaser',
  'status-messages',
  'wiki-entry',
];
$manager = \Drupal::service('plugin.manager.sdc');
$validator = \Drupal::service('Drupal\Core\Theme\Component\ComponentValidator');
$renderer = \Drupal::service('renderer');
$definitions = $manager->getDefinitions();
$samples = [
  'account-menu' => [
    '#props' => ['authenticated' => TRUE],
    '#slots' => ['avatar' => '', 'menu_items' => '<a href="/conta">Minha conta</a>'],
  ],
  'author-card' => [
    '#props' => ['variant' => 'author_editorial', 'display_name' => 'Autoria cultural', 'has_avatar' => FALSE],
    '#slots' => ['avatar' => '', 'actions' => ''],
  ],
  'audio-player' => ['#props' => ['src' => '/files/sample.mp3', 'title' => 'Sessão cultural']],
  'alerts-trigger' => ['#props' => ['href' => '/avisos/resumo', 'count' => 0]],
  'badge' => ['#props' => ['label' => 'Cultura', 'variant' => 'primary', 'icon_name' => 'tag']],
  'breadcrumb' => [
    '#props' => ['label' => 'Breadcrumb'],
    '#slots' => [
      'items' => Markup::create(
        '<li class="aculta-breadcrumb__item"><a class="aculta-breadcrumb__link" href="/">Início</a></li>'
        . '<li class="aculta-breadcrumb__item aculta-breadcrumb__current" aria-current="page">Eventos</li>'
      ),
    ],
  ],
  'button' => [
    '#props' => [
      'text' => 'Abrir',
      'url' => '/destino',
      'variant' => 'primary',
      'icon_before' => 'arrow-right',
    ],
  ],
  'cart-offcanvas' => ['#props' => ['count' => 0, 'empty' => TRUE, 'url' => '/carrinho']],
  'card-cultural' => [
    '#props' => [
      'title' => 'Evento cultural',
      'category' => 'Cultura',
      'date' => '2026-11-04',
      'location' => 'Centro',
      'variant' => 'compact',
    ],
    '#slots' => ['image' => '', 'actions' => ''],
  ],
  'discussion-comment' => [
    '#props' => ['has_author' => TRUE, 'has_submitted' => TRUE],
    '#slots' => [
      'author' => 'Autoria pública',
      'submitted' => '<time datetime="2026-11-04">4 de novembro de 2026</time>',
      'body' => 'Contribuição na discussão.',
      'footer' => '<a href="/topico/exemplo#comentario">Link permanente</a>',
    ],
  ],
  'filter-chips' => [
    '#props' => [
      'items' => [
        ['label' => 'Todos', 'url' => '/todos', 'is_active' => TRUE, 'count' => 2],
      ],
    ],
  ],
  'forum-category' => [
    '#props' => [],
    '#slots' => [
      'parent' => 'Discussões públicas',
      'title' => '<h3><a href="/discussoes/categoria">Cultura e sociedade</a></h3>',
      'description' => 'Conversas culturais.',
      'count' => '12 tópicos',
    ],
  ],
  'forum-topic' => [
    '#props' => ['variant' => 'teaser', 'has_title' => TRUE, 'has_meta' => TRUE, 'has_bookmark' => FALSE],
    '#slots' => [
      'title' => '<h2><a href="/forum/topico-exemplo">Conversa sobre cultura</a></h2>',
      'meta' => 'Publicado recentemente',
      'bookmark' => '',
      'content' => '<div class="aculta-author-card">Autoria pública</div><div class="field--name-body">Descrição do tópico.</div>',
    ],
  ],
  'hero-banner' => [
    '#props' => ['title' => 'Agenda cultural', 'cta_text' => 'Ver eventos', 'cta_url' => '/eventos'],
    '#slots' => ['background_media' => ''],
  ],
  'icon' => ['#props' => ['name' => 'map-pin', 'variant' => 'duotone', 'size' => 'md']],
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
      'is_account_domain' => TRUE,
      'show_utility_bar' => TRUE,
      'links' => [['title' => 'ACULTA', 'url' => '/']],
    ],
    '#slots' => [
      'search' => '',
      'account_menu' => '<ul class="menu"><li><a href="/conta">Minha conta</a></li><li><a href="/sair">Sair</a></li></ul>',
    ],
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
  'revista-listing' => [
    '#props' => ['eyebrow' => 'Publicações', 'title' => 'Revista 420', 'has_items' => TRUE],
    '#slots' => ['items' => '<div class="views-row">Publicação</div>'],
  ],
  'revista-publication' => [
    '#props' => [
      'has_meta' => TRUE,
      'has_subtitle' => TRUE,
      'has_summary' => TRUE,
      'has_cover' => TRUE,
      'has_footer' => TRUE,
    ],
    '#slots' => [
      'meta' => 'Autor e data',
      'cover' => '',
      'subtitle' => '',
      'summary' => '',
      'body' => 'Texto',
      'footer' => '',
      'authors' => '',
      'discussion' => '',
    ],
  ],
  'revista-teaser' => [
    '#props' => ['title' => 'Publicação cultural', 'url' => '/revista/publicacao', 'has_cover' => FALSE],
    '#slots' => ['image' => '', 'category' => '', 'subtitle' => '', 'summary' => ''],
  ],
  'status-messages' => ['#props' => ['type' => 'status', 'messages' => ['Validação concluída.']]],
  'wiki-entry' => [
    '#props' => [
      'variant' => 'article',
      'url' => '/wiki/exemplo',
      'has_summary' => TRUE,
      'has_image' => TRUE,
      'has_categories' => TRUE,
      'has_related' => TRUE,
      'has_references' => TRUE,
      'has_discussion' => TRUE,
    ],
    '#slots' => [
      'title' => 'Exemplo de verbete',
      'summary' => 'Resumo',
      'image' => '',
      'categories' => '',
      'body' => 'Corpo',
      'related' => 'Relacionados',
      'references' => 'Referências',
      'discussion' => 'Discussão',
      'actions' => '',
    ],
  ],
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
  if (in_array(
    $component_id,
    [
      'author-card',
      'discussion-comment',
      'forum-category',
      'forum-topic',
      'revista-listing',
      'revista-publication',
      'revista-teaser',
      'wiki-entry',
    ],
    TRUE,
  )) {
    foreach ([$component_id . '.default.story.yml', 'README.md'] as $document) {
      if (!is_file($theme_root . '/components/' . $component_id . '/' . $document)) {
        throw new RuntimeException(sprintf('Missing story or component documentation: %s/%s', $component_id, $document));
      }
    }
  }
  if ($component_id === 'navigation-bar' && !is_file($theme_root . '/components/navigation-bar/navigation-bar.js')) {
    throw new RuntimeException('Navigation behavior file is missing.');
  }
  if ($component_id === 'icon') {
    $icon_build = [
      '#type' => 'component',
      '#component' => $plugin_id,
      '#props' => ['name' => 'map-pin', 'variant' => 'duotone', 'size' => 'md'],
    ];
    $icon = $renderer->renderInIsolation($icon_build);
    if (!str_contains((string) $icon, '<svg') || !str_contains((string) $icon, 'viewBox="0 0 256 256"')) {
      throw new RuntimeException('Phosphor SVG must be rendered inline by the icon SDC.');
    }
  }
  if ($component_id === 'navigation-bar' && !in_array('core/once', $definition['library']['dependencies'] ?? [], TRUE)) {
    throw new RuntimeException('Navigation behavior must depend on core/once.');
  }
  if ($component_id === 'multidomain-bar') {
    if (!is_file($theme_root . '/components/multidomain-bar/multidomain-bar.js')) {
      throw new RuntimeException('Institution bar behavior file is missing.');
    }
    if (!in_array('core/once', $definition['library']['dependencies'] ?? [], TRUE)
      || in_array('core/jquery', $definition['library']['dependencies'] ?? [], TRUE)
      || in_array('core/drupal.dialog.ajax', $definition['library']['dependencies'] ?? [], TRUE)) {
      throw new RuntimeException('Institution bar must use Drupal once without a jQuery dialog dependency.');
    }
    $template = file_get_contents($theme_root . '/components/multidomain-bar/multidomain-bar.twig');
    $javascript = file_get_contents($theme_root . '/components/multidomain-bar/multidomain-bar.js');
    foreach (['account_menu', 'data-aculta-login-dialog-template', 'data-aculta-login-dialog'] as $required_markup) {
      if (!str_contains($template, $required_markup)) {
        throw new RuntimeException(sprintf('Institution bar template is missing: %s', $required_markup));
      }
    }
    $required_login_code = [
      'fetch(',
      "'_wrapper_format'",
      "'ajax_form'",
      "command.command === 'redirect'",
      "command.command === 'insert'",
    ];
    foreach ($required_login_code as $required_code) {
      if (!str_contains($javascript, $required_code)) {
        throw new RuntimeException(sprintf('Institution bar AJAX login behavior is missing: %s', $required_code));
      }
    }
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
  if ($component_id === 'author-card' && !is_file($theme_root . '/templates/aculta-user-author-card.html.twig')) {
    throw new RuntimeException('The aculta_user author-card adapter is missing.');
  }
  if ($component_id === 'revista-listing' && !is_file($theme_root . '/templates/views/views-view--revista-sessoes--page-revista.html.twig')) {
    throw new RuntimeException('The Revista landing View adapter is missing.');
  }
  if ($component_id === 'revista-teaser' && !is_file($theme_root . '/templates/content/node--revista--teaser.html.twig')) {
    throw new RuntimeException('The Revista teaser node adapter is missing.');
  }
  if ($component_id === 'revista-publication' && !is_file($theme_root . '/templates/content/node--revista--full.html.twig')) {
    throw new RuntimeException('The Revista full node adapter is missing.');
  }
  if ($component_id === 'wiki-entry'
    && (!is_file($theme_root . '/templates/content/node--wiki-entry--teaser.html.twig')
      || !is_file($theme_root . '/templates/content/node--wiki-entry--full.html.twig'))) {
    throw new RuntimeException('Wiki node adapters are missing.');
  }
  if ($component_id === 'forum-topic' && !is_file($theme_root . '/templates/content/node--aculta-forum-topic.html.twig')) {
    throw new RuntimeException('Forum topic node adapter is missing.');
  }
  if ($component_id === 'forum-category'
    && (!is_file($theme_root . '/templates/views/views-view-fields--aculta-forum-categories--page-categories.html.twig')
      || !is_file($theme_root . '/templates/views/views-view-unformatted--aculta-forum-categories--page-categories.html.twig')
      || !is_file($theme_root . '/templates/views/views-view-fields--aculta-forum-categories--block-landing.html.twig')
      || !is_file($theme_root . '/templates/views/views-view--aculta-forum-categories--block-landing.html.twig'))) {
    throw new RuntimeException('Forum category View adapters are missing.');
  }
  if ($component_id === 'discussion-comment' && !is_file($theme_root . '/templates/comment--aculta-discussion-post.html.twig')) {
    throw new RuntimeException('Discussion comment adapter is missing.');
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
  if ($component_id === 'breadcrumb'
    && (!str_contains($output, 'aria-current="page"') || !str_contains($output, 'aria-label="Breadcrumb"'))) {
    throw new RuntimeException('Breadcrumb must render its accessible label and current page marker.');
  }
  if ($component_id === 'revista-listing'
    && (!str_contains($output, 'revista-listing__title') || !str_contains($output, '<h1'))) {
    throw new RuntimeException('The Revista landing component must own the page-level H1.');
  }
  if ($component_id === 'wiki-entry'
    && (!str_contains($output, 'aculta-wiki-article__title') || !str_contains($output, '<h1'))) {
    throw new RuntimeException('The Wiki article component must own the page-level H1.');
  }
  print $plugin_id . " PASS\n";
}

print sprintf("ACULTA420 V2 SDC: PASS (%d componentes; descoberta, schema estrito, assets e render isolado)\n", count($component_ids));
