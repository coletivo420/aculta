<?php

/** Apply the approved shorter Home without replacing the full internal pages. */
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) {
  throw new RuntimeException('Local site only.');
}
$state = \Drupal::state()->get('aculta.institution_setup', []);
if (empty($state['complete'])) {
  throw new RuntimeException('Install the institutional site first.');
}
if (!empty($state['editorial_home_revised'])) {
  echo 'Home revision already applied; edit through Drupal.' . PHP_EOL;
  return;
}
$data = json_decode(file_get_contents(__DIR__ . '/institution/content.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$short = json_decode(file_get_contents(__DIR__ . '/institution/home-editorial.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$paragraphs = static fn(array $values): string => implode('', array_map(static fn(string $value): string => '<p>' . $escape($value) . '</p>', $values));
$link = static fn(string $path, string $label, string $class = 'aculta-editorial-link'): string => '<a href="' . $escape($path) . '" class="' . $escape($class) . '">' . $escape($label) . '</a>';
$home = Node::load($state['nodes']['home']);
$hero = '<section class="aculta-hero"><p class="aculta-eyebrow">' . $escape($data['hero']['eyebrow']) . '</p><h1>ASSOCIAÇÃO CULTURAL<br>ANTIPROIBICIONISTA</h1><p class="aculta-hero-slogan">' . $escape($short['slogan']) . '</p><p class="aculta-hero-lead">' . $escape($short['lead']) . '</p><div class="aculta-actions">' . $link('/institucional', 'CONHEÇA A ASSOCIAÇÃO', 'btn btn-primary') . $link('/projetos', 'NOSSOS PROJETOS', 'btn btn-outline-primary') . '</div></section>';
$home->set('body', ['value' => $hero, 'format' => 'full_html'])->setNewRevision(TRUE);
$home->save();
$bodies = [];
$bodies['home_who'] = '<section class="aculta-editorial-section"><h2 class="aculta-section-title">QUEM SOMOS</h2><h3 class="aculta-editorial-heading">' . $escape($data['who']['title']) . '</h3><div class="aculta-prose">' . $paragraphs($short['who']) . '<p>' . $link('/institucional', 'SAIBA MAIS SOBRE A ASSOCIAÇÃO') . '</p></div></section><section class="aculta-mission"><h2>NOSSA MISSÃO</h2><p>' . $escape($data['mission']) . '</p></section>';
$work = '<section class="aculta-editorial-section"><h2>O QUE FAZEMOS</h2><p class="aculta-prose">' . $escape($data['work']['intro']) . '</p><div class="aculta-areas">';
foreach ($data['work']['areas'] as $index => $area) {
  $work .= '<section><h3>' . $escape($area['title']) . '</h3><p>' . $escape($short['work'][$index]) . '</p></section>';
}
$bodies['home_work'] = $work . '</div><p>' . $link('/institucional', 'CONHEÇA NOSSA ATUAÇÃO') . '</p></section>';
foreach (['city' => ['/atividades', 'VEJA NOSSAS ATIVIDADES'], 'knowledge' => ['/noticias', 'ACOMPANHE AS PUBLICAÇÕES'], 'care' => ['/institucional', 'CONHEÇA NOSSA ATUAÇÃO'], 'research' => ['/institucional', 'CONHEÇA NOSSA HISTÓRIA'], 'transparency' => ['/transparencia', 'CONHEÇA NOSSA TRANSPARÊNCIA']] as $key => [$path, $label]) {
  $class = $key === 'city' ? 'aculta-city' : '';
  $heading = !empty($data[$key]['bar']) ? '<h2 class="aculta-section-title">' . $escape($data[$key]['bar']) . '</h2><h3 class="aculta-editorial-heading">' . $escape($data[$key]['title']) . '</h3>' : '<h2>' . $escape($data[$key]['title']) . '</h2>';
  $extra = $key === 'city' ? '<p class="aculta-callout">CULTURA SE FAZ JUNTO.</p>' : '';
  $extra .= '<p>' . $link($path, $label, $key === 'city' ? 'btn btn-primary' : 'aculta-editorial-link') . '</p>';
  $bodies['home_' . $key] = '<section class="aculta-editorial-section ' . $class . '">' . $heading . '<div class="aculta-prose">' . $paragraphs($short[$key]) . $extra . '</div></section>';
}
foreach ($bodies as $key => $body) {
  $block = BlockContent::load($state['blocks'][$key]);
  $block->set('body', ['value' => $body, 'format' => 'full_html'])->setNewRevision(TRUE);
  $block->setRevisionLogMessage('Home editorial resumida; textos completos preservados nas páginas internas.');
  $block->save();
}
if (!FieldStorageConfig::loadByName('node', 'field_display_title')) {
  FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'field_display_title', 'type' => 'string'])->save();
}
if (!FieldConfig::loadByName('node', 'project', 'field_display_title')) {
  FieldConfig::create(['entity_type' => 'node', 'bundle' => 'project', 'field_name' => 'field_display_title', 'label' => 'Título curto nas listagens'])->save();
}
EntityFormDisplay::load('node.project.default')->setComponent('field_display_title', ['type' => 'string_textfield', 'weight' => 1])->save();
EntityViewDisplay::load('node.project.teaser')->setComponent('body', ['type' => 'text_summary_or_trimmed', 'label' => 'hidden', 'weight' => 5, 'settings' => ['trim_length' => 280]])->save();
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
foreach ($short['projects'] as $key => $project) {
  \Drupal::entityTypeManager()->getStorage('node')->resetCache([$state['nodes'][$key]]);
  $node = Node::load($state['nodes'][$key]);
  $body = $node->get('body')->first()->getValue();
  $body['summary'] = '<p>' . $escape($project['summary']) . '</p>';
  $node->set('body', $body)->set('field_display_title', $project['title'])->setNewRevision(TRUE);
  $node->save();
}
$view = \Drupal\views\Entity\View::load('aculta_projects');
$display = $view->get('display');
$display['default']['display_options']['header']['area']['content']['value'] = '<h2 class="aculta-section-title">NOSSOS PROJETOS</h2><p class="aculta-prose">' . $escape($short['projects_intro']) . '</p>';
$view->set('display', $display)->save();
// Keep the complete care and research copy on the institutional page.
$institution = Node::load($state['nodes']['institutional']);
$body = $institution->get('body')->first()->getValue();
$body['value'] .= '<h2>' . $escape($data['care']['title']) . '</h2>' . $paragraphs($data['care']['paragraphs']) . '<p class="aculta-institutional-note">' . $escape($data['care']['note']) . '</p><h2>' . $escape($data['research']['title']) . '</h2>' . $paragraphs($data['research']['paragraphs']);
$institution->set('body', $body)->setNewRevision(TRUE);
$institution->save();
$state['editorial_home_revised'] = TRUE;
\Drupal::state()->set('aculta.institution_setup', $state);
echo 'Short editorial Home applied; full project and institutional bodies preserved.' . PHP_EOL;
