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
  'account-terms-modal',
  'account-menu',
  'account-shell',
  'account-profile-card',
  'account-courses',
  'audio-player',
  'alerts-trigger',
  'avatar',
  'author-card',
  'article-authors',
  'badge',
  'breadcrumb',
  'button',
  'cart-offcanvas',
  'card-cultural',
  'courses-catalog',
  'account-experience-dashboard',
  'account-experience-card',
  'consent-banner',
  'dialog',
  'data-table',
  'document-download',
  'discussion-reaction-bar',
  'discussion-reaction-picker',
  'discussion-reaction-category',
  'discussion-reaction-option',
  'donation-presentation',
  'empty-state',
  'event-page-layout',
  'events-upcoming-block',
  'filter-chips',
  'form-field',
  'forum-category',
  'forum-category-page',
  'forum-list-page',
  'forum-search-page',
  'forum-topic',
  'forum-topic-row',
  'green-stash-page',
  'heading',
  'hero-banner',
  'hint',
  'icon',
  'image-audit-report',
  'layout-section',
  'layout-page',
  'lms-certificate-card',
  'lms-learning-course-card',
  'lms-learning-dashboard',
  'lms-learning-journey',
  'lms-learning-room',
  'lms-course-curriculum',
  'lms-course-materials',
  'lms-h5p-course',
  'lms-lesson-page',
  'lms-tour',
  'map-popup',
  'messenger-trigger',
  'multidomain-bar',
  'navigation-bar',
  'pagination',
  'product-card',
  'purpose-showcase',
  'profile-details',
  'portal-requirements',
  'progress',
  'related-content',
  'search-preview',
  'revista-listing',
  'revista-publication',
  'revista-teaser',
  'saved-content-page',
  'sales-panel',
  'secret-reveal',
  'studio-account-section-layout',
  'studio-menu',
  'studio-shell',
  'studio-form',
  'studio-section-header',
  'studio-listing-table',
  'studio-menu-item',
  'studio-revista-form',
  'profile-editor-dialog',
  'studio-course-authoring-page',
  'support-form',
  'public-profile-page',
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
  'account-terms-modal' => [
    '#props' => ['title' => 'Termos da conta'],
    '#slots' => [
      'content' => Markup::create('<form><div class="portal-terms-modal__documents"><a href="#uso">Termos</a></div><button>Continuar</button></form>'),
    ],
  ],
  'account-menu' => [
    '#props' => [
      'authenticated' => TRUE,
      'legal_links' => [
        ['title' => 'Busca', 'url' => '/busca'],
        ['title' => 'Privacidade', 'url' => '/politica-de-privacidade'],
      ],
    ],
    '#slots' => ['avatar' => '', 'menu_items' => '<ul><li><a href="/conta">Minha conta</a></li></ul>'],
  ],
  'account-shell' => [
    '#props' => [
      'title' => 'Minha conta',
      'section_title' => 'Visão geral',
      'menu_items' => [['label' => 'Perfil', 'href' => '/conta', 'active' => TRUE]],
      'accordion_items' => [['label' => 'Pedidos', 'href' => '/conta/pedidos', 'open' => FALSE]],
      'logout' => ['label' => 'Sair', 'href' => '/user/logout'],
    ],
    '#slots' => ['content' => Markup::create('<p>Resumo da conta.</p>')],
  ],
  'account-profile-card' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<article class="portal-account-bio"><h3>Sobre</h3><p>Biografia.</p></article>')],
  ],
  'account-courses' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<article class="portal-account-course"><h3>Curso de exemplo</h3><div class="portal-account-course__meta">Em andamento</div></article>')],
  ],
  'audio-player' => ['#props' => ['src' => '/files/sample.mp3', 'title' => 'Sessão cultural']],
  'alerts-trigger' => ['#props' => ['href' => '/avisos/resumo', 'count' => 0]],
  'badge' => ['#props' => ['label' => 'Cultura', 'variant' => 'primary', 'icon_name' => 'tag']],
  'breadcrumb' => [
    '#props' => [
      'label' => 'Breadcrumb',
      'trail' => [['label' => 'Eventos', 'url' => '/eventos']],
      'current' => 'Agenda',
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
      'accent' => 'green',
      'variant' => 'compact',
    ],
    '#slots' => ['image' => '', 'actions' => ''],
  ],
  'article-authors' => [
    '#props' => [],
    '#slots' => [
      'title' => Markup::create('<h2>Autoria</h2>'),
      'authors' => Markup::create('<article class="author-card">Pessoa autora</article>'),
    ],
  ],
  'courses-catalog' => [
    '#props' => [],
    '#slots' => [
      'intro' => Markup::create('<p>Formação para ampliar conhecimento e fortalecer direitos.</p>'),
      'catalog_title' => Markup::create('<h2>Cursos disponíveis</h2>'),
      'catalog' => Markup::create('<ul><li>Curso de exemplo</li></ul>'),
      'collaboration' => Markup::create('<p>Acesse sua conta para participar.</p>'),
    ],
  ],
  'account-experience-card' => [
    '#props' => [
      'id' => 'cursos',
      'kind' => 'card',
      'icon' => 'books',
      'title' => 'Meus cursos',
      'description' => 'Continue seu percurso de aprendizagem.',
      'url' => '/ganjalab',
      'action_label' => 'Acessar',
    ],
  ],
  'account-experience-dashboard' => [
    '#props' => [
      'show_identity' => TRUE,
      'identity_name' => 'Pessoa de exemplo',
      'identity_email' => 'pessoa@aculta.org',
      'profile_url' => '/perfil/exemplo',
      'experiences_title' => 'Suas experiências',
      'experiences_description' => 'Acesse suas áreas na ACULTA.',
      'account_url' => '/conta/preferencias',
      'account_link_label' => 'Configurações da conta',
      'account_link_title' => 'Minha conta',
      'account_link_description' => 'Preferências e dados cadastrais',
    ],
    '#slots' => [
      'menu' => Markup::create('<nav aria-label="Studio"><a href="/conta">Conta</a></nav>'),
      'avatar' => Markup::create('<img src="/avatar.svg" alt="" width="96" height="96">'),
      'profile_link' => Markup::create('<a href="/perfil/exemplo">Ver perfil</a>'),
      'calendar' => Markup::create('<section>Calendário</section>'),
      'connections' => Markup::create('<section>Conexões</section>'),
      'experiences' => Markup::create('<li class="aculta-account-experience-item"><article class="aculta-xp-card">Curso</article></li>'),
    ],
  ],
  'consent-banner' => [
    '#props' => ['privacy_url' => '/sobre/politica-de-privacidade'],
  ],
  'data-table' => [
    '#props' => ['label' => 'Tabela de conteúdos', 'mobile_variant' => 'cards'],
    '#slots' => [
      'table' => Markup::create('<table class="data-table__table" role="table"><caption>Conteúdos recentes</caption><thead role="rowgroup"><tr role="row"><th id="col-title" scope="col" role="columnheader">Título</th><th id="col-status" scope="col" role="columnheader">Status</th></tr></thead><tbody role="rowgroup"><tr role="row"><td role="cell" headers="col-title" data-label="Título">Revista cultural</td><td role="cell" headers="col-status" data-label="Status">Rascunho</td></tr></tbody></table>'),
    ],
  ],
  'document-download' => [
    '#props' => ['format' => 'PDF', 'size' => '2,4 MB', 'format_token' => 'pdf'],
    '#slots' => ['link' => Markup::create('<a href="/manual.pdf">Manual do curso</a>')],
  ],
  'discussion-reaction-bar' => [
    '#props' => [
      'target_id' => 'discussion-reactions-aculta_forum_post-42',
      'entity_type' => 'aculta_forum_post',
      'entity_id' => '42',
      'reactions' => ['broccoli' => ['id' => 'broccoli', 'count' => 2, 'user_reacted' => FALSE]],
      'total_count' => 2,
      'user_reaction' => NULL,
      'allowed_reactions' => [
        'broccoli' => ['id' => 'broccoli', 'label' => 'Brócolis', 'icon' => 'broccoli', 'enabled' => TRUE],
      ],
      'default_reaction' => 'broccoli',
      'can_react' => TRUE,
      'is_own_content' => FALSE,
      'react_url' => '/discussion/aculta_forum_post/42/react',
      'picker_url' => '/discussion/aculta_forum_post/42/reactions',
      'csrf_token' => 'token-de-teste',
    ],
  ],
  'discussion-reaction-picker' => [
    '#props' => [],
    '#slots' => [
      'categories' => [[
        '#type' => 'component',
        '#component' => 'aculta420_v2:discussion-reaction-category',
        '#props' => ['category_key' => 'comunidade', 'label' => 'Comunidade'],
        '#slots' => [
          'items' => [[
            '#type' => 'component',
            '#component' => 'aculta420_v2:discussion-reaction-option',
            '#props' => [
              'reaction_id' => 'apoio',
              'name' => 'Apoio',
              'icon' => 'heart',
              'keywords' => 'apoio coracao comunidade',
              'target_id' => 'discussion-reactions-comment-123',
              'react_url' => '/discussoes/reagir/comment/123',
              'csrf_token' => 'dummy-token',
              'selected' => TRUE,
            ],
          ]],
        ],
      ]],
    ],
  ],
  'discussion-reaction-category' => [
    '#props' => ['category_key' => 'comunidade', 'label' => 'Comunidade'],
    '#slots' => [
      'items' => [[
        '#type' => 'component',
        '#component' => 'aculta420_v2:discussion-reaction-option',
        '#props' => [
          'reaction_id' => 'apoio',
          'name' => 'Apoio',
          'icon' => 'heart',
          'keywords' => 'apoio comunidade',
          'target_id' => 'discussion-reactions-comment-123',
          'react_url' => '/discussoes/reagir/comment/123',
          'csrf_token' => 'dummy-token',
          'selected' => TRUE,
        ],
      ]],
    ],
  ],
  'discussion-reaction-option' => [
    '#props' => [
      'reaction_id' => 'apoio',
      'name' => 'Apoio',
      'icon' => 'heart',
      'keywords' => 'apoio comunidade',
      'target_id' => 'discussion-reactions-comment-123',
      'react_url' => '/discussoes/reagir/comment/123',
      'csrf_token' => 'dummy-token',
      'selected' => TRUE,
    ],
    '#slots' => [],
  ],
  'donation-presentation' => [
    '#props' => ['variant' => 'thanks'],
    '#slots' => ['content' => Markup::create('<p class="donation-presentation__donor">Pessoa de exemplo</p>')],
  ],
  'empty-state' => [
    '#props' => ['compact' => FALSE],
    '#slots' => [
      'message' => Markup::create('<p>Você ainda não está matriculado em nenhum curso.</p>'),
      'actions' => Markup::create('<a href="/catalogo">Explorar o catálogo de cursos</a>'),
    ],
  ],
  'events-upcoming-block' => [
    '#props' => [
      'title' => 'Próximos eventos',
      'items' => [
        ['title' => 'Evento de demonstração', 'url' => '/eventos/demonstracao'],
      ],
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
  'forum-category-page' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<header class="forum-category-header"><h1>Categoria</h1></header><div class="aculta-forum-category-page__layout"><main>Tópicos</main><aside>Regras</aside></div>')],
  ],
  'forum-list-page' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<section class="aculta-forum-list__panel" aria-label="Lista"><div class="aculta-forum-list__rows"><article>Tópico</article></div></section>')],
  ],
  'forum-search-page' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<form id="views-exposed-form-aculta-forum-search-page-search"><div class="form-item-keys"><input name="keys"></div><div class="form-item-categoria"><select name="categoria"></select></div><div id="edit-actions"><button type="submit">Buscar</button></div></form><div class="view-aculta-forum-search"><div class="view-empty">Sem resultados</div></div>')],
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
  'forum-topic-row' => [
    '#props' => ['pinned' => FALSE, 'badges' => NULL, 'replies' => NULL, 'views' => NULL, 'last_activity' => NULL],
    '#slots' => [
      'title' => Markup::create('<a href="/discussoes/topico-exemplo">Tópico de teste</a>'),
      'meta' => Markup::create('<article class="author-card">Autoria pública</article><span class="forum-topic-row__category">Cultura</span>'),
      'pinned_content' => 'Fixo',
      'replies_content' => '42',
      'activity_content' => '2h atrás',
    ],
  ],
  'heading' => [
    '#props' => ['text' => 'Revista ACULTA', 'level' => 2, 'style' => 'stripe'],
  ],
  'hero-banner' => [
    '#props' => ['title' => 'Agenda cultural', 'cta_text' => 'Ver eventos', 'cta_url' => '/eventos'],
    '#slots' => ['background_media' => ''],
  ],
  'hint' => [
    '#props' => ['tone' => 'muted'],
    '#slots' => ['content' => Markup::create('Texto auxiliar fictício.')],
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
  'lms-learning-dashboard' => [
    '#props' => [
      'title' => 'Minha aprendizagem',
      'introduction' => 'Acompanhe seus cursos.',
      'continue_title' => 'Continuar aprendendo',
      'courses_title' => 'Meus cursos',
      'empty_courses_message' => 'Ainda sem cursos.',
      'certificates_title' => 'Meus certificados',
    ],
    '#slots' => [
      'resume' => Markup::create('<article class="lms-learning-course-card">Continuar curso</article>'),
      'courses' => Markup::create('<article class="lms-learning-course-card">Meu curso</article>'),
      'empty_action' => Markup::create('<a href="/cursos">Explorar cursos</a>'),
      'certificates' => Markup::create('<article class="lms-certificate-card">Certificado</article>'),
    ],
  ],
  'lms-learning-course-card' => [
    '#props' => [
      'title' => 'Curso de exemplo',
      'progress_id' => 'lms-course-progress-1',
      'progress_label' => 'Progresso do curso de exemplo',
      'progress_text' => '1 de 3 aulas concluídas',
      'progress_value' => 33,
      'has_lessons' => TRUE,
      'finished' => FALSE,
      'pending_evaluation' => FALSE,
      'pending_text' => 'Avaliação pendente',
      'finished_text' => 'Curso concluído',
      'current_lesson' => 'Próxima aula: Introdução',
    ],
    '#slots' => [
      'journey_action' => Markup::create('<a href="/group/1">Ver jornada</a>'),
      'continue_action' => Markup::create('<a href="/group/1/aula/1">Continuar</a>'),
    ],
  ],
  'lms-certificate-card' => [
    '#props' => [
      'title' => 'Curso de exemplo',
      'status' => 'active',
      'status_text' => 'Válido',
      'issued_text' => 'Emitido hoje',
    ],
    '#slots' => ['verification_action' => Markup::create('<a href="/certificados/1">Ver certificado</a>')],
  ],
  'lms-learning-journey' => [
    '#props' => [],
    '#slots' => [
      'content' => Markup::create('<section data-portal-ui="aculta-learning-journey"><h2 data-portal-ui="aculta-learning-journey__title">Minha jornada</h2><ol data-portal-ui="aculta-learning-journey__lessons"><li>Aula 1</li></ol></section>'),
    ],
  ],
  'lms-learning-room' => [
    '#props' => [],
    '#slots' => [
      'content' => Markup::create('<main data-portal-ui="aculta-learning-room"><div data-portal-ui="aculta-learning-room__layout"><article data-portal-ui="aculta-learning-room__content">Atividade</article></div></main>'),
    ],
  ],
  'layout-section' => [
    '#props' => [],
    '#slots' => [
      'header' => Markup::create('<h2>Participantes</h2>'),
      'content' => Markup::create('<div>Cartões de participantes.</div>'),
    ],
  ],
  'lms-lesson-page' => [
    '#props' => [],
    '#slots' => [
      'content' => '<div data-aculta-lms="course-preview" data-portal-ui="aculta-course-lesson-preview"><h1>Aula</h1><p>Conteúdo.</p></div>',
    ],
  ],
  'lms-course-curriculum' => [
    '#props' => [],
    '#slots' => [
      'header' => Markup::create('<h1>Curso de exemplo</h1>'),
      'summary' => Markup::create('<p>1 aula</p>'),
      'actions' => Markup::create('<a href="/curso/aula/adicionar">Adicionar aula</a>'),
      'lessons_heading' => Markup::create('Estrutura do curso'),
      'lessons' => Markup::create('<ol><li>Aula de exemplo</li></ol>'),
      'lesson_order_form' => '',
      'resources' => Markup::create('<section class="aculta-lms-course-builder__resources"><h2>Materiais de apoio</h2><ul><li>Leitura complementar</li></ul></section>'),
    ],
  ],
  'lms-course-materials' => [
    '#props' => [],
    '#slots' => [
      'title' => Markup::create('<h2>Documentos privados do curso</h2>'),
      'description' => Markup::create('<p>Arquivos vinculados ao curso.</p>'),
      'add' => Markup::create('<a href="/curso/documentos/adicionar">Adicionar documento</a>'),
      'items' => Markup::create('<ul><li>Material de estudo.pdf</li></ul>'),
      'empty' => '',
      'unavailable' => '',
    ],
  ],
  'lms-h5p-course' => [
    '#props' => [],
    '#slots' => [
      'title' => Markup::create('<h2>Curso de exemplo</h2>'),
      'course_link' => Markup::create('<a href="/ganjalab/curso">Acessar curso</a>'),
      'activities' => Markup::create('<div>Atividade H5P</div>'),
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
      'portal_menu' => '<ul class="menu"><li><a href="/conta">Minha conta</a></li><li><a href="/sair">Sair</a></li></ul>',
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
  'portal-requirements' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<section><h2>Requisitos</h2><div class="aculta-portal-requirements__legend">Estados</div></section>')],
  ],
  'progress' => [
    '#props' => ['id' => 'curso-progresso', 'label' => 'Progresso do curso', 'value' => 60, 'max' => 100],
  ],
  'related-content' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<h3>Conteúdo relacionado</h3><ul><li>Local relacionado</li></ul>')],
  ],
  'search-preview' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<ul><li><a href="/busca">Resultado de exemplo</a></li></ul>')],
  ],
  'green-stash-page' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<section class="studio aculta-green-stash"><h2>Produtos</h2><nav class="aculta-green-stash__navigation"><a class="aculta-green-stash__navigation-link" href="/green-stash" aria-current="page">Produtos</a></nav><div class="aculta-green-stash__products"><h3>Produto de exemplo</h3></div></section>')],
  ],
  'image-audit-report' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<p>Relatório somente de leitura.</p><table><caption>Resumo</caption><tbody><tr><td>Imagens</td><td>12</td></tr></tbody></table>')],
  ],
  'sales-panel' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<section><h2>Vendas por período</h2><table><caption>Relatório demonstrativo</caption><tbody><tr><td>2026-11</td><td>3</td></tr></tbody></table></section>')],
  ],
  'saved-content-page' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<h1 class="page-title">Itens salvos</h1><ul class="studio-saved-tabs"><li><a href="/salvos" aria-current="page">Salvos</a></li></ul><div class="studio-saved-list-wrapper"><ul class="studio-card-compact-list"><li><article class="studio-card-compact"><a class="compact-card-title" href="/conteudo">Conteúdo salvo</a></article></li></ul></div>')],
  ],
  'secret-reveal' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<p>Nome da credencial</p><pre>valor oculto</pre>')],
  ],
  'studio-account-section-layout' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<section class="studio studio-account-section"><details class="studio-account-section__navigation"><summary>Seções</summary><ul class="studio-account-nav-list"><li><a href="/conta">Dados</a></li></ul></details><div data-aculta-account-main>Conteúdo</div></section>')],
  ],
  'studio-menu' => [
    '#props' => ['title' => 'Studio Dashboard'],
    '#slots' => [
      'sidebar_items' => [[
        '#type' => 'component',
        '#component' => 'aculta420_v2:studio-menu-item',
        '#props' => ['title' => 'Meu Perfil', 'url' => '/conta/perfil', 'current' => TRUE, 'icon' => 'user', 'show_icon' => TRUE],
      ]],
      'dialog_items' => [[
        '#type' => 'component',
        '#component' => 'aculta420_v2:studio-menu-item',
        '#props' => ['title' => 'Meu Perfil', 'url' => '/conta/perfil', 'current' => TRUE, 'icon' => 'user', 'show_icon' => FALSE],
      ]],
    ],
  ],
  'studio-listing-table' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<div class="studio__listing-wrap"><table class="studio__listing"><thead><tr><th>Título</th></tr></thead><tbody><tr><td>Exemplo</td></tr></tbody></table></div>')],
  ],
  'studio-menu-item' => [
    '#props' => ['title' => 'Meu Perfil', 'url' => '/conta/perfil', 'current' => TRUE, 'icon' => 'user'],
    '#slots' => [],
  ],
  'studio-section-header' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<div class="studio__section-header"><h2 data-portal-ui="studio-heading">Perfil editorial</h2><ul class="studio__actions"><li><a href="#editar">Editar</a></li></ul></div>')],
  ],
  'studio-revista-form' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<form class="node-revista-form"><fieldset><legend>Publicação</legend><label for="title">Título</label><input id="title" value="Exemplo"><div class="form-actions"><button class="form-submit">Salvar</button></div></fieldset></form>')],
  ],
  'profile-editor-dialog' => [
    '#props' => [],
    '#slots' => [
      'intro' => Markup::create('<p>Gerencie sua identidade.</p>'),
      'identity' => Markup::create('<h2>Identidade</h2>'),
      'bio' => Markup::create('<h2>Biografia</h2>'),
      'social_links' => Markup::create('<h2>Redes sociais</h2>'),
    ],
  ],
  'studio-shell' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<div class="studio__layout"><nav class="studio__sidebar">Painel</nav><main class="studio__main"><h1>Marley</h1><form><label for="shell-title">Título</label><input id="shell-title"><button class="form-submit">Salvar</button></form></main></div>')],
  ],
  'studio-form' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<form><fieldset><legend>Perfil</legend><label for="studio-form-title">Título</label><input id="studio-form-title" value="Exemplo"><div class="form-actions"><button class="form-submit">Salvar</button></div></fieldset></form>')],
  ],
  'studio-course-authoring-page' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<section class="studio-course-authoring"><a class="studio-course-authoring__back" href="#educador">Voltar</a><form><label for="course-name">Nome</label><input id="course-name"><button class="form-submit">Criar curso</button></form></section>')],
  ],
  'support-form' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<form class="aculta-support"><fieldset><legend>Apoio</legend><div class="form-actions"><button>Continuar</button></div></fieldset></form>')],
  ],
  'public-profile-page' => [
    '#props' => [],
    '#slots' => ['content' => Markup::create('<section class="portal-profile"><header class="portal-profile__header"><h2 class="portal-profile__name">Pessoa exemplo</h2></header><p class="portal-profile__bio">Biografia pública de exemplo.</p></section>')],
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
  'product-card' => [
    '#props' => [],
    '#slots' => [
      'content' => Markup::create('<div><a href="/produtos">Produto de teste</a><p>Resumo do produto.</p><form><label for="test-size">Tamanho</label><select id="test-size"><option>P</option></select><button type="button">Adicionar ao carrinho</button></form></div>'),
    ],
  ],
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

  foreach (['component.yml', 'twig'] as $suffix) {
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
    foreach (['portal_menu', 'data-aculta-login-dialog-template', 'data-aculta-login-dialog'] as $required_markup) {
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
  if ($component_id === 'studio-menu') {
    if (substr_count($output, 'Meu Perfil') !== 2
      || substr_count($output, 'aria-current="page"') !== 2
      || substr_count($output, 'class="aculta-icon') !== 1) {
      throw new RuntimeException('Studio menu must render authorized item slots in both navigations and keep the icon only in the sidebar.');
    }
  }
  if ($component_id === 'studio-menu-item'
    && (!str_contains($output, 'href="/conta/perfil"')
      || !str_contains($output, 'aria-current="page"')
      || !str_contains($output, 'Meu Perfil'))) {
    throw new RuntimeException('Studio menu item must preserve its destination, active state and label.');
  }
  if ($component_id === 'discussion-reaction-picker'
    && (!str_contains($output, 'data-reaction-picker-search')
      || !str_contains($output, 'data-reaction-category="comunidade"')
      || !str_contains($output, 'data-emoji-keywords="apoio coracao comunidade"'))) {
    throw new RuntimeException('Reaction picker must render its search controls and categorized SDC options.');
  }
  if ($component_id === 'discussion-reaction-option'
    && (!str_contains($output, 'name="csrf_token" value="dummy-token"')
      || !str_contains($output, 'name="reaction" value="apoio"')
      || !str_contains($output, 'is-selected'))) {
    throw new RuntimeException('Reaction option must preserve CSRF, reaction ID and selected state.');
  }
  if ($component_id === 'forum-topic-row') {
    foreach ([
      'forum-topic-row--pinned',
      'Tópico de teste',
      'Autoria pública',
      'Cultura',
      '42',
      '2h atrás',
    ] as $required_output) {
      if (!str_contains($output, $required_output)) {
        throw new RuntimeException(sprintf('Forum topic row is missing expected output: %s', $required_output));
      }
    }
    $empty_state_build = [
      '#type' => 'component',
      '#component' => $plugin_id,
      '#props' => [
        'pinned' => FALSE,
        'badges' => NULL,
        'replies' => NULL,
        'views' => NULL,
        'last_activity' => NULL,
      ],
      '#slots' => [
        'title' => 'Tópico sem atividade',
        'meta' => 'Categoria',
        'pinned_content' => ['#markup' => ''],
        'replies_content' => ['#markup' => ''],
        'activity_content' => ['#markup' => ''],
      ],
    ];
    $empty_state_output = (string) $renderer->renderInIsolation($empty_state_build);
    if (str_contains($empty_state_output, 'forum-topic-row--pinned')
      || !str_contains($empty_state_output, 'Dado indisponível')) {
      throw new RuntimeException('Empty Forum topic row slots must not pin rows and must retain unavailable-data placeholders.');
    }
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
