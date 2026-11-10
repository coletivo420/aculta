<?php

/**
 * @file
 * Validates discovery, strict schemas, assets, and rendering of V2 SDCs.
 *
 * Run with: drush scr scripts/validate-aculta420-v2-sdc.php.
 */

declare(strict_types=1);

use Drupal\Core\Cache\NullBackend;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Render\Markup;
use Drupal\Core\Theme\ComponentNegotiator;
use Drupal\Core\Theme\ComponentPluginManager;

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
    && preg_match('/(?<!["\'])<svg\b/i', $source)) {
    throw new RuntimeException(sprintf('Interface SVGs must use the icon SDC: %s', $source_file->getPathname()));
  }
  $framework_class = '(?:row|col-(?:xs|sm|md|lg|xl|xxl)-\d+|d-flex|container-fluid|mb-\d+|form-control|form-select)';
  $has_framework_class = preg_match('/class\s*=\s*[\"\'][^\"\']*(?<![a-z0-9_-])' . $framework_class . '(?![a-z0-9_-])[^\"\']*[\"\']/i', $source)
    || preg_match('/(?:^|[\s,{])\.' . $framework_class . '(?=[\s:{,#]|$)/im', $source);
  $has_framework_btn_class = FALSE;
  if (preg_match_all('/class\s*=\s*([\"\'])(.*?)\1/is', $source, $class_attributes)) {
    foreach ($class_attributes[2] as $class_attribute) {
      $class_tokens = preg_split('/\s+/', trim($class_attribute), -1, PREG_SPLIT_NO_EMPTY);
      if (in_array('btn', $class_tokens, TRUE)) {
        $has_framework_btn_class = TRUE;
        break;
      }
    }
  }
  $has_framework_btn_selector = preg_match('/(?:^|[\s,{])\.btn(?=[\s:{,#]|$)/im', $source);
  if (preg_match('/bootstrap5|bootstrap\.bundle|popper|data-bs-|core\/jquery|core\/drupal\.dialog|\bjQuery\b/i', $source)
    || $has_framework_btn_class
    || $has_framework_btn_selector
    || $has_framework_class) {
    throw new RuntimeException(sprintf('Framework-owned classes or attributes are forbidden in %s.', $source_file->getPathname()));
  }
}
$component_ids = [
  'accordion',
  'account-menu',
  'audio-player',
  'alerts-trigger',
  'avatar',
  'author-card',
  'badge',
  'breadcrumb',
  'button',
  'cart-offcanvas',
  'card-cultural',
  'discussion-comment',
  'dialog',
  'data-table',
  'empty-state',
  'event-page-layout',
  'filter-chips',
  'form-field',
  'forum-category',
  'forum-topic',
  'hero-banner',
  'icon',
  'layout-page',
  'lms-lesson-page',
  'lms-tour',
  'map-popup',
  'messenger-trigger',
  'multidomain-bar',
  'navigation-bar',
  'pagination',
  'purpose-showcase',
  'profile-details',
  'progress',
  'revista-listing',
  'revista-publication',
  'revista-teaser',
  'site-footer',
  'social-links-page',
  'status-messages',
  'tabs',
  'wiki-entry',
];
$manager = \Drupal::service('plugin.manager.sdc');
$validator = \Drupal::service('Drupal\Core\Theme\Component\ComponentValidator');
$renderer = \Drupal::service('renderer');
// Discover current component sources in a nonpersistent cache. This renders
// newly added SDCs in a worktree without clearing Runtime discovery caches.
$theme_manager = \Drupal::service('theme.manager');
$source_manager = new ComponentPluginManager(
  \Drupal::moduleHandler(),
  \Drupal::service('theme_handler'),
  new NullBackend('component_plugins'),
  \Drupal::configFactory(),
  $theme_manager,
  new ComponentNegotiator($theme_manager),
  \Drupal::service('file_system'),
  \Drupal::service('Drupal\\Core\\Theme\\Component\\SchemaCompatibilityChecker'),
  \Drupal::service('Drupal\\Core\\Theme\\Component\\ComponentValidator'),
  DRUPAL_ROOT,
);
$definitions = $source_manager->getDefinitions();
(new \ReflectionProperty(DefaultPluginManager::class, 'definitions'))
  ->setValue($manager, $definitions);
$samples = [
  'avatar' => [
    '#props' => ['size' => 'md', 'label' => 'Avatar sem foto'],
    '#slots' => [
      'media' => Markup::create('<img src="/avatar-example.png" alt="Retrato de exemplo">'),
    ],
  ],
  'author-card' => [
    '#props' => [
      'variant' => 'author_editorial',
      'display_name' => 'Coletiva de Cultura',
      'has_avatar' => TRUE,
      'designation' => 'Autora',
      'bio' => 'Biografia pública autorizada.',
    ],
    '#slots' => [
      'avatar' => Markup::create('<img src="/author-example.png" alt="Retrato de exemplo">'),
      'actions' => '',
    ],
  ],
  'accordion' => [
    '#props' => ['title' => 'Pergunta frequente'],
    '#slots' => ['content' => '<p>Resposta disponível.</p>'],
  ],
  'account-menu' => [
    '#props' => ['authenticated' => TRUE],
    '#slots' => ['avatar' => '', 'menu_items' => '<ul><li><a href="/conta">Minha conta</a></li></ul>'],
  ],
  'audio-player' => ['#props' => ['src' => '/files/sample.mp3', 'title' => 'Sessão cultural']],
  'alerts-trigger' => ['#props' => ['href' => '/avisos/resumo', 'count' => 0]],
  'badge' => ['#props' => ['label' => 'Cultura', 'variant' => 'primary', 'icon_name' => 'tag']],
  'breadcrumb' => [
    '#props' => ['label' => 'Breadcrumb'],
    '#slots' => ['items' => Markup::create('<li class="aculta-breadcrumb__item"><a class="aculta-breadcrumb__link" href="/">Início</a></li><li class="aculta-breadcrumb__item aculta-breadcrumb__current" aria-current="page">Eventos</li>')],
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
      'accent' => 'green',
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
  'data-table' => [
    '#props' => ['label' => 'Tabela de conteúdos', 'mobile_variant' => 'cards'],
    '#slots' => [
      'table' => Markup::create('<table class="data-table__table" role="table"><caption>Conteúdos recentes</caption><thead role="rowgroup"><tr role="row"><th id="col-title" scope="col" role="columnheader">Título</th><th id="col-status" scope="col" role="columnheader">Status</th></tr></thead><tbody role="rowgroup"><tr role="row"><td role="cell" headers="col-title" data-label="Título">Revista cultural</td><td role="cell" headers="col-status" data-label="Status">Rascunho</td></tr></tbody></table>'),
    ],
  ],
  'empty-state' => [
    '#props' => ['compact' => FALSE],
    '#slots' => [
      'message' => Markup::create('<p>Você ainda não está matriculado em nenhum curso.</p>'),
      'actions' => Markup::create('<a href="/catalogo">Explorar o catálogo de cursos</a>'),
    ],
  ],
  'dialog' => [
    '#props' => [
      'id' => 'v2-sdc-dialog-validation',
      'title' => 'Diálogo de validação',
      'trigger_label' => 'Abrir diálogo',
      'trigger_url' => '/destino-completo',
    ],
    '#slots' => [
      'content' => Markup::create('<p>Conteúdo do diálogo.</p>'),
      'actions' => Markup::create('<a class="button button--outline" href="/destino-completo">Continuar</a>'),
    ],
  ],
  'event-page-layout' => [
    '#props' => [],
    '#slots' => [
      'title' => '<h1>Festival de cultura</h1>',
      'essentials' => '<h2>Datas</h2><ul><li>10/05/2030</li></ul>',
      'content' => '<p>Descrição do evento.</p>',
      'sidebar' => '<h2>Organização</h2><p>Coletivo cultural</p>',
    ],
  ],
  'filter-chips' => [
    '#props' => [
      'items' => [
        ['label' => 'Todos', 'url' => '/todos', 'is_active' => TRUE, 'count' => 2],
      ],
    ],
  ],
  'form-field' => [
    '#props' => ['id' => 'email', 'invalid' => TRUE],
    '#slots' => [
      'label' => Markup::create('<label for="email-control">E-mail</label>'),
      'control' => Markup::create('<input id="email-control" name="email" type="email" aria-invalid="true" aria-describedby="email-description email-error">'),
      'description' => 'Informe um endereço válido.',
      'error' => 'Revise o endereço informado.',
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
      'title' => '<h2><a href="/topico/exemplo">Conversa sobre cultura</a></h2>',
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
  'layout-page' => [
    '#props' => ['variant' => 'aside'],
    '#slots' => [
      'title' => '<h1>Página de teste</h1>',
      'content' => '<p>Conteúdo principal.</p>',
      'aside' => '<p>Conteúdo complementar.</p>',
    ],
  ],
  'lms-lesson-page' => [
    '#props' => [],
    '#slots' => [
      'content' => '<div data-portal-ui="aculta-learning-room__lesson"><h1>Aula</h1><p>Conteúdo.</p></div>',
    ],
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
      'is_account_domain' => TRUE,
      'show_utility_bar' => TRUE,
    ],
    '#slots' => [
      'search' => '',
      'account_menu' => '<ul class="menu"><li><a href="/conta">Minha conta</a></li><li><a href="/sair">Sair</a></li></ul>',
    ],
  ],
  'navigation-bar' => [
    '#props' => ['brand_home_url' => '/', 'brand_title' => 'ACULTA', 'menu_id' => 'v2-sdc-validation-menu'],
    '#slots' => [
      'brand' => '<div class="block-system-branding-block"><a class="site-logo" href="/"><img src="/logo.png" alt="ACULTA"></a></div>',
      'menu_items' => '<ul><li><a href="/">Início</a></li></ul>',
      'utility_items' => '',
    ],
  ],
  'pagination' => [
    '#props' => [
      'current_page' => 1,
      'total_pages' => 2,
      'links' => [['url' => '/?page=0', 'label' => '1', 'is_current' => TRUE]],
    ],
  ],
  'purpose-showcase' => [
    '#props' => [
      'heading' => 'Ecossistema ACULTA',
      'links' => [['title' => 'Apoio', 'url' => '/apoio']],
    ],
  ],
  'profile-details' => [
    '#props' => ['title' => 'Biografia', 'title_id' => 'studio-profile-bio-title', 'heading_level' => 2],
    '#slots' => ['bio' => Markup::create('<p>Biografia autorizada.</p>'), 'actions' => ''],
  ],
  'progress' => [
    '#props' => ['id' => 'curso-progresso', 'label' => 'Progresso do curso', 'value' => 60, 'max' => 100],
  ],
  'site-footer' => [
    '#props' => [],
    '#slots' => ['content' => '<div class="region-footer"><p>Rodapé</p></div>'],
  ],
  'lms-tour' => [
    '#props' => [
      'id' => 'v2-sdc-tour-validation',
      'title' => 'Tour guiado LMS',
      'trigger_label' => 'Iniciar tour',
      'trigger_url' => '/curso/iniciar-tour',
      'current_step' => 'welcome',
      'total_steps' => 4,
    ],
    '#slots' => [
      'content' => Markup::create('<p>Bem-vindo ao percurso do curso.</p>'),
      'actions' => Markup::create('<a class="button button--primary" href="/curso/etapa-2">Avançar</a>'),
    ],
  ],
  'revista-teaser' => [
    '#props' => [
      'title' => 'Artigo sobre Cultivo',
      'url' => '/revista/artigo-cultivo',
      'has_category' => TRUE,
      'has_subtitle' => TRUE,
      'has_summary' => TRUE,
      'has_cover' => FALSE,
      'read_label' => 'Ler artigo',
    ],
    '#slots' => [
      'category' => Markup::create('<span>Cultivo</span>'),
      'subtitle' => Markup::create('<span>Subtítulo do artigo</span>'),
      'summary' => Markup::create('<p>Resumo do artigo editorial.</p>'),
    ],
  ],
  'revista-listing' => [
    '#props' => [
      'eyebrow' => 'Editorial',
      'title' => 'Revista ACULTA',
      'has_items' => TRUE,
    ],
    '#slots' => [
      'items' => Markup::create('<div class="revista-items"><p>Lista de publicações</p></div>'),
    ],
  ],
  'revista-publication' => [
    '#props' => [
      'has_meta' => TRUE,
      'has_subtitle' => TRUE,
      'has_summary' => TRUE,
      'has_cover' => FALSE,
      'has_footer' => FALSE,
      'has_authors' => TRUE,
      'has_discussion' => FALSE,
    ],
    '#slots' => [
      'meta' => Markup::create('<div>Publicado em 2026-11-05</div>'),
      'subtitle' => Markup::create('<h2>Subtítulo</h2>'),
      'summary' => Markup::create('<p>Resumo da publicação</p>'),
      'body' => Markup::create('<p>Corpo completo do artigo editorial.</p>'),
      'authors' => Markup::create('<div>Autoria Coletiva</div>'),
    ],
  ],
  'wiki-entry' => [
    '#props' => [
      'variant' => 'full',
      'url' => '/wiki/canabinoides',
      'has_summary' => TRUE,
      'has_image' => FALSE,
      'has_categories' => TRUE,
      'has_related' => FALSE,
      'has_references' => FALSE,
      'has_discussion' => FALSE,
    ],
    '#slots' => [
      'title' => Markup::create('<h1>Canabinoides</h1>'),
      'summary' => Markup::create('<p>Resumo do verbete enciclopédico.</p>'),
      'categories' => Markup::create('<ul><li>Ciência</li></ul>'),
      'body' => Markup::create('<p>Texto completo do verbete.</p>'),
    ],
  ],
  'social-links-page' => [
    '#props' => ['title' => 'Página de links', 'has_links' => TRUE, 'theme_preset' => 'classic'],
    '#slots' => [
      'owner_identity' => '',
      'links' => '<ul data-portal-ui="social-links-list"><li><a data-portal-ui="social-links-channel" href="https://example.invalid/">Canal</a></li></ul>',
    ],
  ],
  'status-messages' => ['#props' => ['type' => 'status', 'messages' => ['Validação concluída.']]],
  'tabs' => [
    '#props' => ['label' => 'Suítes ACULTA'],
    '#slots' => [
      'items' => Markup::create('<div class="item-list"><ul class="item-list__items"><li><a href="/marley" aria-current="page">Marley</a></li><li><a href="/ganjalab">Ganjalab</a></li></ul></div>'),
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
  if ($component_id === 'event-page-layout' && !is_file($theme_root . '/templates/aculta-event-page.html.twig')) {
    throw new RuntimeException('Event page adapter is missing.');
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
      "setAttribute('data-hx-get'",
      "setAttribute('data-hx-target'",
      "setAttribute('data-hx-drupal-only-main-content'",
      "htmx.trigger(link, 'aculta:login:open')",
    ];
    foreach ($required_login_code as $required_code) {
      if (!str_contains($javascript, $required_code)) {
        throw new RuntimeException(sprintf('Institution bar HTMX login behavior is missing: %s', $required_code));
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
  if ($component_id === 'avatar') {
    if (!str_contains($output, 'alt="Retrato de exemplo"') || str_contains($output, 'role="img"')) {
      throw new RuntimeException('Avatar media must preserve its own alternative text.');
    }
    $fallback_build = [
      '#type' => 'component',
      '#component' => $plugin_id,
      '#props' => ['size' => 'md', 'label' => 'Avatar sem foto'],
      '#slots' => [],
    ];
    $fallback_output = (string) $renderer->renderInIsolation($fallback_build);
    if (!str_contains($fallback_output, 'role="img"')
      || !str_contains($fallback_output, 'aria-label="Avatar sem foto"')) {
      throw new RuntimeException('Avatar fallback must expose a neutral, named icon.');
    }
  }
  if ($component_id === 'author-card'
    && (!str_contains($output, 'Coletiva de Cultura')
      || !str_contains($output, 'Biografia pública autorizada.')
      || !str_contains($output, 'alt="Retrato de exemplo"'))) {
    throw new RuntimeException('Author card must render its authorized identity, bio, and avatar slot.');
  }
  if ($component_id === 'tabs'
    && (!str_contains($output, '<nav')
      || !str_contains($output, 'aria-label="Suítes ACULTA"')
      || !str_contains($output, 'aria-current="page"')
      || str_contains($output, 'role="tablist"'))) {
    throw new RuntimeException('Tabs must render page navigation links, not an interactive tab panel widget.');
  }
  if ($component_id === 'dialog') {
    $dialog_dependencies = $definition['library']['dependencies'] ?? [];
    foreach (['aculta420_v2/utility-drawers', 'core/drupal'] as $dependency) {
      if (!in_array($dependency, $dialog_dependencies, TRUE)) {
        throw new RuntimeException(sprintf('Dialog behavior dependency is missing: %s', $dependency));
      }
    }
    $dialog_css = array_keys($definition['library']['css']['component'] ?? []);
    if (!array_filter($dialog_css, static fn(string $asset): bool => str_ends_with($asset, '/dialog.css'))) {
      throw new RuntimeException('Dialog component CSS was not discovered by the SDC library.');
    }
    foreach ([
      '<a class="aculta-dialog__trigger" href="/destino-completo"',
      'aria-controls="v2-sdc-dialog-validation"',
      'aria-haspopup="dialog"',
      '<dialog',
      'id="v2-sdc-dialog-validation"',
      'aria-labelledby="v2-sdc-dialog-validation-title"',
      'aria-modal="true"',
      'data-aculta-utility-drawer',
      'data-aculta-dialog-close',
      'tabindex="-1"',
      'Conteúdo do diálogo.',
      'Continuar',
    ] as $required_markup) {
      if (!str_contains($output, $required_markup)) {
        throw new RuntimeException(sprintf('Dialog markup is missing: %s', $required_markup));
      }
    }
    $dialog_behavior = file_get_contents($theme_root . '/js/utility-dialogs.js');
    if (!str_contains($dialog_behavior, "dialog[data-aculta-utility-drawer]")
      || !str_contains($dialog_behavior, "event.key !== 'Tab'")
      || !str_contains($dialog_behavior, 'last.focus()')
      || !str_contains($dialog_behavior, 'first.focus()')) {
      throw new RuntimeException('Utility dialog behavior must select the SDC contract attribute.');
    }
  }
  if ($component_id === 'data-table') {
    $table_dependencies = $definition['library']['dependencies'] ?? [];
    foreach (['core/drupal', 'core/once'] as $dependency) {
      if (!in_array($dependency, $table_dependencies, TRUE)) {
        throw new RuntimeException(sprintf('Data table behavior dependency is missing: %s', $dependency));
      }
    }
    $table_js_assets = array_keys($definition['library']['js'] ?? []);
    if (!array_filter($table_js_assets, static fn(string $asset): bool => str_ends_with($asset, '/data-table.js'))) {
      throw new RuntimeException('Data table behavior was not discovered as an SDC JavaScript asset.');
    }
    $table_css = file_get_contents($theme_root . '/components/data-table/data-table.css');
    $table_js = file_get_contents($theme_root . '/components/data-table/data-table.js');
    foreach ([
      'data-table--cards .data-table__table td::before',
      'content: attr(data-label)',
      'overflow: auto',
      '::-webkit-scrollbar',
    ] as $required_css) {
      if (!str_contains($table_css, $required_css)) {
        throw new RuntimeException(sprintf('Data table responsive style is missing: %s', $required_css));
      }
    }
    foreach ([
      "once('acultaDataTable'",
      'ResizeObserver',
      'MutationObserver',
      'setAttribute(\'aria-label\'',
      "removeAttribute('tabindex')",
      "setAttribute('role', 'region')",
      "removeAttribute('role')",
    ] as $required_js) {
      if (!str_contains($table_js, $required_js)) {
        throw new RuntimeException(sprintf('Data table scroll behavior is missing: %s', $required_js));
      }
    }
    foreach ([
      'data-aculta-data-table',
      'data-mobile-variant="cards"',
      'role="table"',
      'scope="col"',
      'data-label="Título"',
      'headers="col-title"',
    ] as $required_markup) {
      if (!str_contains($output, $required_markup)) {
        throw new RuntimeException(sprintf('Data table markup contract is missing: %s', $required_markup));
      }
    }
    $scroll_build = [
      '#type' => 'component',
      '#component' => $plugin_id,
      '#props' => ['label' => 'Tabela larga', 'mobile_variant' => 'scroll'],
      '#slots' => ['table' => Markup::create('<table role="table"><tbody role="rowgroup"><tr role="row"><td role="cell">Item</td></tr></tbody></table>')],
    ];
    $scroll_output = (string) $renderer->renderInIsolation($scroll_build);
    if (!str_contains($scroll_output, 'data-mobile-variant="scroll"')) {
      throw new RuntimeException('Data table scroll variant must render as an explicit supported mode.');
    }
  }
  if ($component_id === 'card-cultural') {
    $card_css = file_get_contents($theme_root . '/components/card-cultural/card-cultural.css');
    foreach ([
      'green' => '--accent-green-stripe',
      'yellow' => '--accent-yellow-stripe',
      'red' => '--accent-red-stripe',
    ] as $accent => $token) {
      if (!str_contains($card_css, 'card-cultural--accent-' . $accent)
        || !str_contains($card_css, 'var(' . $token . ')')) {
        throw new RuntimeException(sprintf('Card cultural accent token style is missing: %s', $accent));
      }
    }
    if (!str_contains($output, 'card-cultural--accent-green')) {
      throw new RuntimeException('Card cultural must render the explicit Portal accent value.');
    }
  }
  if ($component_id === 'empty-state') {
    $empty_css = array_keys($definition['library']['css']['component'] ?? []);
    if (!array_filter($empty_css, static fn(string $asset): bool => str_ends_with($asset, '/empty-state.css'))) {
      throw new RuntimeException('Empty state component CSS was not discovered by the SDC library.');
    }
    foreach ([
      'class="empty-state"',
      'Você ainda não está matriculado em nenhum curso.',
      'href="/catalogo"',
      'Explorar o catálogo de cursos',
    ] as $required_markup) {
      if (!str_contains($output, $required_markup)) {
        throw new RuntimeException(sprintf('Empty state render is missing consumer content: %s', $required_markup));
      }
    }
    $compact_build = [
      '#type' => 'component',
      '#component' => $plugin_id,
      '#props' => ['compact' => TRUE],
      '#slots' => ['message' => Markup::create('<p>Este curso ainda não tem aulas publicadas.</p>')],
    ];
    $compact_output = (string) $renderer->renderInIsolation($compact_build);
    if (!str_contains($compact_output, 'empty-state--compact')
      || str_contains($compact_output, 'empty-state__actions')) {
      throw new RuntimeException('Empty state compact mode must render without inventing consumer actions.');
    }
  }
  if ($component_id === 'breadcrumb'
    && (!str_contains($output, 'aria-current="page"') || !str_contains($output, 'aria-label="Breadcrumb"'))) {
    throw new RuntimeException('Breadcrumb must render its accessible label and current page marker.');
  }
  if ($component_id === 'form-field'
    && (!str_contains($output, 'data-aculta-form-field-id="email"')
      || !str_contains($output, '<label for="email-control">E-mail</label>')
      || !str_contains($output, 'aria-describedby="email-description email-error"')
      || !str_contains($output, 'aria-invalid="true"')
      || !str_contains($output, 'id="email-description"')
      || !str_contains($output, 'id="email-error"')
      || !str_contains($output, 'Revise o endereço informado.'))) {
    throw new RuntimeException('Form field must render its Drupal control, accessible description, and textual validation error.');
  }
  if ($component_id === 'progress'
    && (!str_contains($output, '<label class="progress__label" for="curso-progresso">Progresso do curso</label>')
      || !str_contains($output, '<progress')
      || !str_contains($output, 'id="curso-progresso"')
      || !str_contains($output, 'value="60"')
      || !str_contains($output, 'max="100"')
      || !str_contains($output, '>60%</span>'))) {
    throw new RuntimeException('Determinate progress must render its native value, accessible label, and visible percentage.');
  }
  if ($component_id === 'progress') {
    $progress_css = file_get_contents($theme_root . '/components/progress/progress.css');
    if (!str_contains($progress_css, ':indeterminate::-moz-progress-bar')
      || !str_contains($progress_css, ':indeterminate::-webkit-progress-value')
      || !str_contains($progress_css, 'transform: scaleX(.35)')) {
      throw new RuntimeException('Indeterminate progress must be visually distinguished from a completed bar.');
    }
    $indeterminate_build = [
      '#type' => 'component',
      '#component' => $plugin_id,
      '#props' => ['id' => 'carregamento', 'label' => 'Carregando conteúdo', 'value' => NULL],
    ];
    $indeterminate_output = (string) $renderer->renderInIsolation($indeterminate_build);
    if (!str_contains($indeterminate_output, '<progress')
      || !str_contains($indeterminate_output, 'Carregando conteúdo')
      || preg_match('/<progress\b[^>]*\bvalue=/', $indeterminate_output)) {
      throw new RuntimeException('Indeterminate progress must omit the value attribute and retain its accessible label.');
    }
  }
  print $plugin_id . " PASS\n";
}

print sprintf("ACULTA420 V2 SDC: PASS (%d componentes; descoberta, schema estrito, assets e render isolado)\n", count($component_ids));
