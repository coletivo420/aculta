<?php

/**
 * @file
 * Applies the reviewed institutional content to the local site using Core APIs.
 * Run with: php vendor/drush/drush/drush.php php:script scripts/install-institution.php
 * Existing editorial entities are never overwritten on subsequent executions.
 */

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\system\Entity\Menu;
use Drupal\views\Entity\View;

$data = json_decode(file_get_contents(__DIR__ . '/institution/content.json'), TRUE, 512, JSON_THROW_ON_ERROR);
// This installer is deliberately local-only; production requires a separate review.
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1', 'default'], TRUE)) {
  throw new RuntimeException('Run this installer only against the local Drupal site.');
}
global $state;
$state = \Drupal::state()->get('aculta.institution_setup', []);
if (!empty($state['complete'])) {
  throw new RuntimeException('Institutional content already installed. Edit through Drupal; this script will not overwrite it.');
}
if (!isset($state['original_front'])) {
  $state['original_front'] = \Drupal::config('system.site')->get('page.front');
  $state['nodes'] = [];
  $state['blocks'] = [];
  $state['menus'] = [];
  \Drupal::state()->set('aculta.institution_setup', $state);
}

function institution_escape(string $text): string {
  return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function institution_paragraphs(array $paragraphs): string {
  return implode('', array_map(fn($text) => '<p>' . institution_escape($text) . '</p>', $paragraphs));
}
function institution_link(string $path, string $label, string $class = ''): string {
  return '<a href="' . institution_escape($path) . '"' . ($class ? ' class="' . institution_escape($class) . '"' : '') . '>' . institution_escape($label) . '</a>';
}
function institution_section(array $section, string $extra = '', string $class = ''): string {
  $html = '<section class="aculta-editorial-section ' . institution_escape($class) . '">';
  if (!empty($section['bar'])) {
    $html .= '<h2 class="aculta-section-title">' . institution_escape($section['bar']) . '</h2>';
    $html .= '<h3 class="aculta-editorial-heading">' . institution_escape($section['title']) . '</h3>';
  }
  else {
    $html .= '<h2>' . institution_escape($section['title']) . '</h2>';
  }
  return $html . '<div class="aculta-prose">' . institution_paragraphs($section['paragraphs']) . $extra . '</div></section>';
}
function institution_field(string $entity_type, string $bundle, string $name, string $type, string $label, array $settings = [], int $cardinality = 1): void {
  if (!FieldStorageConfig::loadByName($entity_type, $name)) {
    FieldStorageConfig::create(['entity_type' => $entity_type, 'field_name' => $name, 'type' => $type, 'cardinality' => $cardinality, 'settings' => $settings])->save();
  }
  if (!FieldConfig::loadByName($entity_type, $bundle, $name)) {
    $field_settings = $type === 'entity_reference' ? ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['project' => 'project']]] : [];
    if ($type === 'file') {
      $field_settings = ['file_extensions' => 'pdf', 'file_directory' => 'institutional-documents', 'description_field' => TRUE];
    }
    if ($type === 'image') {
      $field_settings = ['alt_field' => TRUE, 'alt_field_required' => TRUE, 'file_extensions' => 'png gif jpg jpeg webp', 'file_directory' => 'projects'];
    }
    FieldConfig::create(['entity_type' => $entity_type, 'bundle' => $bundle, 'field_name' => $name, 'label' => $label, 'required' => FALSE, 'settings' => $field_settings])->save();
  }
}
function institution_display(string $entity_type, string $bundle, string $mode, array $components, bool $form = FALSE): void {
  $class = $form ? EntityFormDisplay::class : EntityViewDisplay::class;
  $display = $class::load("$entity_type.$bundle.$mode") ?? $class::create(['targetEntityType' => $entity_type, 'bundle' => $bundle, 'mode' => $mode, 'status' => TRUE]);
  foreach ($components as $name => $options) {
    $display->setComponent($name, $options);
  }
  $display->save();
}
function institution_alias(string $source, string $alias): void {
  $storage = \Drupal::entityTypeManager()->getStorage('path_alias');
  $existing = $storage->loadByProperties(['alias' => $alias, 'langcode' => 'pt-br']);
  foreach ($existing as $entity) {
    if ($entity->getPath() !== $source) {
      throw new RuntimeException('Alias already belongs to another resource: ' . $alias);
    }
    return;
  }
  PathAlias::create(['path' => $source, 'alias' => $alias, 'langcode' => 'pt-br'])->save();
}
function institution_node(string $key, string $type, string $title, string $body, string $path, bool $published = TRUE, array $extra = []): Node {
  global $state;
  if (!empty($state['nodes'][$key])) {
    return Node::load($state['nodes'][$key]);
  }
  // Recover a partial installation by its exact alias without overwriting content.
  $aliases = \Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['alias' => $path, 'langcode' => 'pt-br']);
  foreach ($aliases as $alias) {
    if (preg_match('@^/node/(\d+)$@', $alias->getPath(), $matches)) {
      $existing = Node::load($matches[1]);
      if ($existing && $existing->bundle() === $type && $existing->label() === $title) {
        $state['nodes'][$key] = (int) $existing->id();
        \Drupal::state()->set('aculta.institution_setup', $state);
        return $existing;
      }
    }
    throw new RuntimeException('Existing alias must be reviewed before installation: ' . $path);
  }
  $node = Node::create(['type' => $type, 'title' => $title, 'langcode' => 'pt-br', 'uid' => 1, 'status' => $published, 'promote' => FALSE, 'body' => ['value' => $body, 'format' => 'full_html']] + $extra);
  $node->save();
  $state['nodes'][$key] = (int) $node->id();
  \Drupal::state()->set('aculta.institution_setup', $state);
  institution_alias('/node/' . $node->id(), $path);
  echo 'Node: ' . $title . ' / ' . $path . PHP_EOL;
  return $node;
}
function institution_place(string $id, string $plugin, string $region, int $weight, string $label, string $paths = '', array $extra = []): void {
  if (Block::load($id)) {
    return;
  }
  $visibility = $paths ? ['request_path' => ['id' => 'request_path', 'negate' => FALSE, 'pages' => $paths]] : [];
  Block::create(['id' => $id, 'theme' => 'aculta420', 'region' => $region, 'plugin' => $plugin, 'weight' => $weight, 'status' => TRUE, 'visibility' => $visibility, 'settings' => array_replace(['id' => $plugin, 'label' => $label, 'label_display' => '0'], $extra)])->save();
}
function institution_block(string $key, string $label, string $html, int $weight, string $paths = '<front>', string $region = 'content'): void {
  global $state;
  if (empty($state['blocks'][$key])) {
    $existing = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['type' => 'basic', 'info' => $label]);
    $entity = $existing ? reset($existing) : BlockContent::create(['type' => 'basic', 'info' => $label, 'langcode' => 'pt-br', 'body' => ['value' => $html, 'format' => 'full_html']]);
    $entity->save();
    $state['blocks'][$key] = (int) $entity->id();
    \Drupal::state()->set('aculta.institution_setup', $state);
  }
  $entity = BlockContent::load($state['blocks'][$key]);
  institution_place('aculta_' . $key, 'block_content:' . $entity->uuid(), $region, $weight, $label, $paths, ['view_mode' => 'full']);
}

// Core content types; no pre-existing type or content is removed.
foreach (['page' => 'Página institucional', 'project' => 'Projeto', 'activity' => 'Atividade', 'article' => 'Notícia', 'document' => 'Documento institucional'] as $id => $label) {
  if (!NodeType::load($id)) {
    $type = NodeType::create(['type' => $id, 'name' => $label, 'new_revision' => TRUE, 'display_submitted' => FALSE]);
    $type->save();
  }
  institution_field('node', $id, 'body', 'text_with_summary', 'Conteúdo');
  institution_display('node', $id, 'default', ['body' => ['type' => 'text_textarea_with_summary', 'weight' => 5]], TRUE);
  institution_display('node', $id, 'default', ['body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 5]]);
  institution_display('node', $id, 'full', ['body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 5]]);
}
institution_field('node', 'project', 'field_category', 'string', 'Categoria');
institution_field('node', 'project', 'field_link_label', 'string', 'Chamada do link');
institution_field('node', 'project', 'field_project_order', 'integer', 'Ordem editorial');
institution_field('node', 'project', 'field_image', 'image', 'Imagem', ['uri_scheme' => 'public', 'target_type' => 'file']);
foreach (['history' => 'História', 'objectives' => 'Objetivos', 'activities' => 'Atividades', 'records' => 'Registros'] as $suffix => $label) {
  institution_field('node', 'project', 'field_project_' . $suffix, 'text_long', $label);
}
foreach (['article', 'activity'] as $bundle) {
  institution_field('node', $bundle, 'field_project', 'entity_reference', 'Projetos relacionados', ['target_type' => 'node'], -1);
}
institution_field('node', 'activity', 'field_activity_date', 'datetime', 'Data da atividade', ['datetime_type' => 'date']);
institution_field('node', 'document', 'field_document', 'file', 'Documento PDF', ['uri_scheme' => 'public', 'target_type' => 'file']);
institution_field('node', 'document', 'field_category', 'string', 'Categoria');
foreach (['project', 'article', 'activity', 'document'] as $bundle) {
  $widgets = ['body' => ['type' => 'text_textarea_with_summary', 'weight' => 5]];
  $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
  $weight = 10;
  foreach ($fields as $name => $definition) {
    if (!str_starts_with($name, 'field_')) {
      continue;
    }
    $widget = ['string' => 'string_textfield', 'integer' => 'number', 'entity_reference' => 'entity_reference_autocomplete', 'image' => 'image_image', 'file' => 'file_generic', 'datetime' => 'datetime_default', 'text_long' => 'text_textarea'][$definition->getType()];
    $widgets[$name] = ['type' => $widget, 'weight' => $weight++];
  }
  institution_display('node', $bundle, 'default', $widgets, TRUE);
  $components = ['body' => ['type' => 'text_default', 'label' => 'hidden', 'weight' => 5]];
  if (isset($fields['field_category'])) {
    $components['field_category'] = ['type' => 'string', 'label' => 'hidden', 'weight' => -5];
  }
  if (isset($fields['field_image'])) {
    $components['field_image'] = ['type' => 'image', 'label' => 'hidden', 'weight' => 0, 'settings' => ['image_link' => '', 'image_style' => 'large', 'image_loading' => ['attribute' => 'lazy']]];
  }
  if (isset($fields['field_document'])) {
    $components['field_document'] = ['type' => 'file_default', 'label' => 'above', 'weight' => 10, 'settings' => ['use_description_as_link_text' => TRUE]];
  }
  foreach (['default', 'full', 'teaser'] as $mode) {
    $mode_components = $components;
    if ($bundle === 'project' && $mode !== 'teaser') {
      foreach (['history', 'objectives', 'activities', 'records'] as $weight => $suffix) {
        $mode_components['field_project_' . $suffix] = ['type' => 'text_default', 'label' => 'above', 'weight' => 20 + $weight];
      }
    }
    institution_display('node', $bundle, $mode, $mode_components);
  }
}

// Verified content supplied by the responsible person; no legal identifiers invented.
$home_body = '<section class="aculta-hero"><p class="aculta-eyebrow">' . institution_escape($data['hero']['eyebrow']) . '</p><h1>Associação Cultural<br>Antiproibicionista</h1><p class="aculta-hero-lead">' . institution_escape($data['hero']['text']) . '</p><div class="aculta-actions">' . institution_link('/institucional', 'CONHEÇA NOSSA HISTÓRIA', 'btn btn-primary') . institution_link('/projetos', 'NOSSOS PROJETOS', 'btn btn-outline-primary') . '</div></section>';
$home = institution_node('home', 'page', $data['organization'], $home_body, '/inicio');
$who = institution_section($data['who'], '<p>' . institution_link('/institucional', 'SAIBA MAIS SOBRE A ASSOCIAÇÃO', 'aculta-editorial-link') . '</p>');
$mission = '<section class="aculta-mission"><h2>NOSSA MISSÃO</h2><p>' . institution_escape($data['mission']) . '</p></section>';
institution_block('home_who', 'Home — Quem somos e missão', $who . $mission, 10);
$work = '<section class="aculta-editorial-section"><h2>' . institution_escape($data['work']['title']) . '</h2><p class="aculta-prose">' . institution_escape($data['work']['intro']) . '</p><div class="aculta-areas">';
foreach ($data['work']['areas'] as $area) {
  $work .= '<section><h3>' . institution_escape($area['title']) . '</h3><p>' . institution_escape($area['text']) . '</p></section>';
}
institution_block('home_work', 'Home — O que fazemos', $work . '</div></section>', 20);
foreach ($data['projects'] as $order => $project) {
  institution_node($project['key'], 'project', $project['name'], institution_paragraphs($project['paragraphs']), $project['path'], TRUE, ['field_category' => $project['category'], 'field_link_label' => $project['link'], 'field_project_order' => $order]);
}
institution_block('home_city', 'Home — Cultura que ocupa a cidade', institution_section($data['city'], '<p class="aculta-callout">' . institution_escape($data['city']['callout']) . '</p><p>' . institution_link('/atividades', 'VEJA NOSSAS ATIVIDADES', 'btn btn-primary') . '</p>', 'aculta-city'), 40);
institution_block('home_knowledge', 'Home — Comunicação e conhecimento', institution_section($data['knowledge'], '<p>' . institution_link('/noticias', 'ACOMPANHE AS PUBLICAÇÕES', 'aculta-editorial-link') . '</p>'), 50);
institution_block('home_care', 'Home — Cuidado, informação e direitos', institution_section($data['care'], '<p class="aculta-institutional-note">' . institution_escape($data['care']['note']) . '</p>'), 60);
institution_block('home_research', 'Home — Cultura, cuidado e conhecimento', institution_section($data['research']), 70);
institution_block('home_transparency', 'Home — Transparência', institution_section($data['transparency'], '<p>' . institution_link('/transparencia', 'CONHEÇA NOSSA TRANSPARÊNCIA', 'aculta-editorial-link') . '</p>'), 80);

$institution = '<h2>Quem somos</h2>' . institution_paragraphs($data['who']['paragraphs']) . '<h2>Nossa missão</h2><p>' . institution_escape($data['mission']) . '</p><h2>Nossa história</h2><p>A trajetória dos projetos culturais e a constituição jurídica da Associação são etapas distintas.</p><ol class="aculta-timeline"><li><strong>2023 — Baque Sativa.</strong> ' . institution_escape($data['projects'][1]['paragraphs'][0]) . '</li><li><strong>2026 — Cultura e produção de conhecimento.</strong> ' . institution_escape($data['research']['paragraphs'][1]) . '</li></ol><h2>Objetivos</h2><p>' . institution_escape($data['mission']) . '</p><h2>Áreas de atuação</h2>';
foreach ($data['work']['areas'] as $area) {
  $institution .= '<h3>' . institution_escape($area['title']) . '</h3><p>' . institution_escape($area['text']) . '</p>';
}
$institution .= '<h2>Organização institucional</h2>' . institution_paragraphs([$data['participation']['paragraphs'][0]]) . '<p>' . institution_link('/transparencia', 'Informações institucionais e transparência') . '</p>';
institution_node('institutional', 'page', 'Institucional', $institution, '/institucional');
institution_node('projects', 'page', 'Projetos', '<p class="aculta-page-intro">' . institution_escape($data['projects_intro']) . '</p>', '/projetos');
institution_node('activities', 'page', 'Atividades', institution_paragraphs($data['city']['paragraphs']) . '<h2>Cultura e arte</h2><p>' . institution_escape($data['work']['areas'][0]['text']) . '</p>', '/atividades');
institution_node('news', 'page', 'Notícias', '<h2>' . institution_escape($data['knowledge']['title']) . '</h2>' . institution_paragraphs($data['knowledge']['paragraphs']) . '<p>' . institution_link('/projetos/podplant420', 'Conheça o PodPlant420') . '</p>', '/noticias');
institution_node('transparency', 'page', 'Transparência', '<p class="aculta-page-intro">' . institution_escape($data['transparency']['intro']) . '</p>' . institution_paragraphs($data['transparency']['paragraphs']), '/transparencia');
$privacy = file_get_contents(__DIR__ . '/institution/politica-de-privacidade.html');
institution_node('privacy', 'page', 'Política de Privacidade', $privacy, '/politica-de-privacidade');

// Reusable structured institutional data: empty fields are never rendered.
if (!BlockContentType::load('aculta_institution')) {
  BlockContentType::create(['id' => 'aculta_institution', 'label' => 'Dados institucionais', 'revision' => TRUE])->save();
}
$data_fields = [
  'field_org_name' => ['string', 'Denominação'],
  'field_org_description' => ['string_long', 'Apresentação curta'],
  'field_legal_nature' => ['string', 'Natureza jurídica'],
  'field_cnpj' => ['string', 'CNPJ'],
  'field_headquarters' => ['string', 'Sede'],
  'field_address' => ['string_long', 'Endereço institucional'],
  'field_email' => ['email', 'E-mail'],
  'field_phone' => ['string', 'Telefone/WhatsApp'],
  'field_site' => ['link', 'Site oficial'],
  'field_governance' => ['text_long', 'Governança'],
];
$widgets = [];
$components = [];
foreach ($data_fields as $name => [$type, $label]) {
  institution_field('block_content', 'aculta_institution', $name, $type, $label);
  $widgets[$name] = ['type' => ['string' => 'string_textfield', 'string_long' => 'string_textarea', 'email' => 'email_default', 'link' => 'link_default', 'text_long' => 'text_textarea'][$type], 'weight' => count($widgets)];
  $components[$name] = ['type' => ['string' => 'string', 'string_long' => 'basic_string', 'email' => 'email_mailto', 'link' => 'link', 'text_long' => 'text_default'][$type], 'label' => 'hidden', 'weight' => count($components)];
}
institution_display('block_content', 'aculta_institution', 'default', $widgets, TRUE);
$public_components = $components;
unset($public_components['field_org_description']);
institution_display('block_content', 'aculta_institution', 'default', $public_components);
institution_display('block_content', 'aculta_institution', 'full', $public_components);
if (empty($state['blocks']['institution_data'])) {
  $entity = BlockContent::create(['type' => 'aculta_institution', 'info' => 'Dados oficiais da Associação', 'langcode' => 'pt-br', 'field_org_name' => $data['organization'], 'field_org_description' => 'Cultura, informação, cuidado e participação social.', 'field_legal_nature' => 'Associação privada sem fins lucrativos', 'field_headquarters' => 'Goiânia - Goiás - Brasil', 'field_email' => $data['email'], 'field_site' => ['uri' => 'https://aculta.org/', 'title' => 'aculta.org']]);
  $entity->save();
  $state['blocks']['institution_data'] = (int) $entity->id();
  \Drupal::state()->set('aculta.institution_setup', $state);
}
$institution_data = BlockContent::load($state['blocks']['institution_data']);
institution_place('aculta_institution_data', 'block_content:' . $institution_data->uuid(), 'content', 90, 'INFORMAÇÕES INSTITUCIONAIS', "<front>\n/institucional\n/transparencia", ['view_mode' => 'full', 'label_display' => 'visible']);

// Footer data uses the same entity, with an appropriate display mode.
foreach (['footer', 'contact'] as $mode) {
  if (!EntityViewMode::load('block_content.' . $mode)) {
    EntityViewMode::create(['id' => 'block_content.' . $mode, 'label' => ucfirst($mode), 'targetEntityType' => 'block_content'])->save();
  }
  $selected = $components;
  unset($selected['field_governance']);
  if ($mode === 'contact') {
    unset($selected['field_cnpj'], $selected['field_legal_nature'], $selected['field_org_description']);
  }
  institution_display('block_content', 'aculta_institution', $mode, $selected);
}
institution_place('aculta_footer_data', 'block_content:' . $institution_data->uuid(), 'footer', 0, $data['organization'], '', ['view_mode' => 'footer']);

// Core Views use published nodes and allow future projects, news and documents.
function institution_view(string $id, string $label, array $bundles, string $paths, int $weight, string $header = '', string $sort = 'created', string $row_mode = 'teaser'): void {
  if (!View::load($id)) {
    $filters = [
      'status' => ['id' => 'status', 'table' => 'node_field_data', 'field' => 'status', 'value' => '1', 'entity_type' => 'node', 'entity_field' => 'status', 'plugin_id' => 'boolean'],
      'type' => ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'value' => array_combine($bundles, $bundles), 'entity_type' => 'node', 'entity_field' => 'type', 'plugin_id' => 'bundle'],
    ];
    $sorting = $sort === 'field_project_order' ? ['field_project_order_value' => ['id' => 'field_project_order_value', 'table' => 'node__field_project_order', 'field' => 'field_project_order_value', 'plugin_id' => 'standard', 'order' => 'ASC']] : ['created' => ['id' => 'created', 'table' => 'node_field_data', 'field' => 'created', 'entity_type' => 'node', 'entity_field' => 'created', 'plugin_id' => 'date', 'order' => 'DESC']];
    $options = ['title' => $label, 'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']], 'cache' => ['type' => 'tag', 'options' => []], 'query' => ['type' => 'views_query', 'options' => []], 'pager' => ['type' => 'none', 'options' => ['offset' => 0]], 'style' => ['type' => 'default', 'options' => ['default_row_class' => TRUE]], 'row' => ['type' => 'entity:node', 'options' => ['view_mode' => $row_mode]], 'filters' => $filters, 'sorts' => $sorting, 'css_class' => $id === 'aculta_projects' ? 'aculta-project-list' : 'aculta-editorial-list'];
    if ($header) {
      $options['header'] = ['area' => ['id' => 'area', 'table' => 'views', 'field' => 'area', 'plugin_id' => 'text', 'empty' => FALSE, 'content' => ['value' => $header, 'format' => 'full_html'], 'tokenize' => FALSE]];
    }
    View::create(['id' => $id, 'label' => $label, 'module' => 'views', 'base_table' => 'node_field_data', 'base_field' => 'nid', 'status' => TRUE, 'display' => ['default' => ['id' => 'default', 'display_title' => 'Padrão', 'display_plugin' => 'default', 'position' => 0, 'display_options' => $options], 'block_1' => ['id' => 'block_1', 'display_title' => 'Bloco', 'display_plugin' => 'block', 'position' => 1, 'display_options' => ['block_description' => $label, 'display_extenders' => []]]]])->save();
  }
  institution_place($id, 'views_block:' . $id . '-block_1', 'content', $weight, $label, $paths);
}
institution_view('aculta_projects', 'Projetos da Associação', ['project'], "<front>\n/projetos", 30, '<h2 class="aculta-section-title">NOSSOS PROJETOS</h2><p class="aculta-prose">' . institution_escape($data['projects_intro']) . '</p>', 'field_project_order');
institution_view('aculta_news', 'Publicações da Associação', ['article'], '/noticias', 30);
institution_view('aculta_activities', 'Registros de atividades', ['activity'], '/atividades', 30);
institution_view('aculta_documents', 'Documentos institucionais', ['document'], '/transparencia', 100, '<h2>DOCUMENTOS</h2>');

// Real menu destinations: canonical entity URLs are resolved through aliases.
function institution_menu_link(string $menu, string $key, string $label, string $uri, int $weight): void {
  global $state;
  if (!empty($state['menus'][$key])) {
    return;
  }
  $existing = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => $menu]);
  foreach ($existing as $link) {
    if ($link->get('link')->uri === $uri || $link->getUrlObject()->toString() === \Drupal\Core\Url::fromUri($uri)->toString()) {
      $state['menus'][$key] = (int) $link->id();
      \Drupal::state()->set('aculta.institution_setup', $state);
      return;
    }
  }
  $link = MenuLinkContent::create(['title' => $label, 'menu_name' => $menu, 'langcode' => 'pt-br', 'link' => ['uri' => $uri], 'weight' => $weight, 'enabled' => TRUE]);
  $link->save();
  $state['menus'][$key] = (int) $link->id();
  \Drupal::state()->set('aculta.institution_setup', $state);
}
if (!\Drupal::service('plugin.manager.menu.link')->getDefinition('standard.front_page', FALSE)) {
  institution_menu_link('main', 'main_home', 'Início', 'route:<front>', 0);
}
foreach (['institutional' => 'Institucional', 'projects' => 'Projetos', 'activities' => 'Atividades', 'news' => 'Notícias', 'transparency' => 'Transparência'] as $key => $label) {
  institution_menu_link('main', 'main_' . $key, $label, 'entity:node/' . $state['nodes'][$key], count($state['menus']));
}

// The institutional Webform owns /contato; no legacy node or Core Contact form is created.
institution_menu_link('main', 'main_contact', 'Contato', 'internal:/contato', 99);
institution_place('aculta_contact_data', 'block_content:' . $institution_data->uuid(), 'content', -10, 'Contato institucional', '/contato', ['view_mode' => 'contact']);

$footer_menus = [
  'aculta-footer-institution' => ['INSTITUCIONAL', ['institutional' => 'Quem somos', 'projects' => 'Projetos', 'transparency' => 'Transparência']],
  'aculta-footer-content' => ['CONTEÚDO', ['news' => 'Notícias', 'activities' => 'Atividades', 'podplant420' => 'PodPlant420']],
];
foreach ($footer_menus as $id => [$label, $links]) {
  if (!Menu::load($id)) {
    Menu::create(['id' => $id, 'label' => $label])->save();
  }
  foreach ($links as $key => $title) {
    $uri = $key === 'contact' ? 'internal:/contato' : 'entity:node/' . $state['nodes'][$key];
    institution_menu_link($id, $id . '_' . $key, $title, $uri, array_search($key, array_keys($links), TRUE));
  }
  institution_place(str_replace('-', '_', $id), 'system_menu_block:' . $id, 'footer', 10 + array_search($id, array_keys($footer_menus), TRUE), $label, '', ['label_display' => 'visible', 'level' => 1, 'depth' => 1, 'expand_all_items' => FALSE]);
}
institution_menu_link('aculta-footer-institution', 'footer_privacy', 'Política de Privacidade', 'entity:node/' . $state['nodes']['privacy'], 3);

// Replace the default empty landing route; preserve its View and all existing blocks.
\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/node/' . $home->id())->set('name', $data['organization'])->save();
$powered = Block::load('aculta_powered');
if ($powered) {
  $powered->disable()->save();
}
$account = Block::load('aculta_account_menu');
if ($account) {
  $visibility = $account->getVisibility();
  $visibility['user_role'] = ['id' => 'user_role', 'negate' => FALSE, 'roles' => ['authenticated' => 'authenticated'], 'context_mapping' => ['user' => '@user.current_user_context:current_user']];
  $account->setVisibilityConfig('user_role', $visibility['user_role'])->save();
}
\Drupal::configFactory()->getEditable('aculta_portal.settings')->set('institution_data_uuid', $institution_data->uuid())->save();
$state['complete'] = TRUE;
// Preserve section wrappers and their classes when editing via CKEditor 5.
$editor = \Drupal\editor\Entity\Editor::load('full_html');
if ($editor) {
  $settings = $editor->getSettings();
  $allowed = $settings['plugins']['ckeditor5_sourceEditing']['allowed_tags'] ?? [];
  $settings['plugins']['ckeditor5_sourceEditing']['allowed_tags'] = array_values(array_unique(array_merge($allowed, ['<section class>', '<div class>', '<p class>', '<h1>', '<h2 class>', '<h3 class>', '<a class>', '<ol class>'])));
  $editor->setSettings($settings)->save();
}
\Drupal::state()->set('aculta.institution_setup', $state);
echo 'Institutional setup complete. Public contact sending remains disabled until an official recipient is provided.' . PHP_EOL;
if (!empty($data['official'])) {
  require __DIR__ . '/apply-official-institution.php';
  require __DIR__ . '/refine-institution.php';
}
