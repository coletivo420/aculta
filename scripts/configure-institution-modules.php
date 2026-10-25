<?php

/** Configure Composer-managed CMS enhancements on the local institutional site. */
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\metatag\Entity\MetatagDefaults;
use Drupal\node\Entity\Node;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\webform\Entity\Webform;

if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) {
  throw new RuntimeException('Local site only.');
}
$state = \Drupal::state()->get('aculta.institution_setup', []);
if (empty($state['official_applied'])) {
  throw new RuntimeException('Official institutional data must be installed first.');
}
if (!empty($state['cms_enhancements'])) {
  echo 'CMS enhancements already configured; edit through Drupal.' . PHP_EOL;
  return;
}
foreach (['webform', 'webform_ui', 'simple_sitemap', 'pathauto', 'metatag', 'metatag_open_graph', 'redirect', 'media', 'media_library'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    throw new RuntimeException('Enable the Composer-managed module first: ' . $module);
  }
}
$data = json_decode(file_get_contents(__DIR__ . '/institution/content.json'), TRUE, 512, JSON_THROW_ON_ERROR);

// Webform: real fields, restricted submission access, no visitor autoresponder.
$webform = Webform::load('aculta_contact');
if (!$webform) {
  $webform = Webform::create(['id' => 'aculta_contact', 'title' => 'Contato', 'langcode' => 'pt-br', 'status' => 'open']);
  $webform->setElements([
    'name' => ['#type' => 'textfield', '#title' => 'Nome', '#required' => TRUE, '#autocomplete' => 'name', '#maxlength' => 255],
    'email' => ['#type' => 'email', '#title' => 'E-mail', '#required' => TRUE, '#autocomplete' => 'email'],
    'subject' => ['#type' => 'textfield', '#title' => 'Assunto', '#required' => TRUE, '#maxlength' => 255],
    'message' => ['#type' => 'textarea', '#title' => 'Mensagem', '#required' => TRUE, '#rows' => 8],
    'actions' => ['#type' => 'webform_actions', '#submit__label' => 'ENVIAR MENSAGEM'],
  ]);
  $webform->setSettings([
    'page' => TRUE, 'page_submit_path' => '/contato', 'ajax' => FALSE, 'form_disable_remote_addr' => TRUE,
    'form_previous_submissions' => FALSE, 'submission_log' => FALSE,
    'confirmation_type' => 'inline', 'confirmation_message' => '<p>Sua mensagem foi registrada. Obrigado pelo contato.</p>',
  ]);
  $webform->setAccessRules(['create' => ['roles' => ['anonymous', 'authenticated'], 'users' => [], 'permissions' => []]]);
  $webform->save();
  $handler = \Drupal::service('plugin.manager.webform.handler')->createInstance('email', [
    'id' => 'email', 'handler_id' => 'institutional_notification', 'label' => 'Notificação institucional', 'status' => TRUE, 'weight' => 0,
    'settings' => [
      'to_mail' => $data['email'], 'from_mail' => $data['email'], 'from_name' => $data['organization'],
      'reply_to' => '[webform_submission:values:email:raw]',
      'subject' => '[webform_submission:values:subject:raw]',
      'body' => '[webform_submission:values]', 'html' => FALSE, 'attachments' => FALSE,
    ],
  ]);
  $webform->addWebformHandler($handler);
}
// Preserve the module's example configuration privately instead of deleting it.
if ($example = Webform::load('contact')) {
  $example->set('status', 'closed')->setSetting('page', FALSE)->save();
}
foreach (\Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['alias' => '/contato', 'langcode' => 'pt-br']) as $alias) {
  $alias->setPath('/webform/aculta_contact')->save();
}
foreach (['main_contact'] as $key) {
  $link = \Drupal\menu_link_content\Entity\MenuLinkContent::load($state['menus'][$key]);
  $link->set('link', ['uri' => 'entity:webform/aculta_contact'])->save();
}
foreach (['anonymous', 'authenticated'] as $role) {
  \Drupal\user\Entity\Role::load($role)->revokePermission('access site-wide contact form')->save();
}

// Pathauto applies to future content; existing reviewed aliases are protected.
\Drupal::configFactory()->getEditable('pathauto.settings')->set('update_action', \Drupal\pathauto\PathautoGeneratorInterface::UPDATE_ACTION_NO_NEW)->save();
$patterns = ['page' => '[node:title]', 'project' => 'projetos/[node:title]', 'activity' => 'atividades/[node:title]', 'article' => 'noticias/[node:title]', 'document' => 'transparencia/documentos/[node:title]'];
foreach ($patterns as $bundle => $pattern) {
  if (!PathautoPattern::load('aculta_' . $bundle)) {
    $condition_id = \Drupal::service('uuid')->generate();
    PathautoPattern::create([
      'id' => 'aculta_' . $bundle, 'label' => 'URLs — ' . $bundle, 'type' => 'canonical_entities:node', 'pattern' => $pattern,
      'selection_criteria' => [$condition_id => ['id' => 'entity_bundle:node', 'negate' => FALSE, 'bundles' => [$bundle => $bundle], 'context_mapping' => ['node' => 'node']]],
      'selection_logic' => 'and', 'weight' => 0, 'status' => TRUE,
    ])->save();
  }
}

// Editable SEO fields plus site-wide defaults; no fabricated social imagery.
$defaults = [
  'global' => ['title' => '[current-page:title] | [site:name]', 'description' => 'Site oficial da Associação Cultural Antiproibicionista em Goiânia: cultura, informação, cuidado e participação social.', 'canonical_url' => '[current-page:url]', 'og_site_name' => $data['organization'], 'og_type' => 'website', 'og_title' => '[current-page:title]', 'og_url' => '[current-page:url]'],
  'front' => ['title' => 'Associação Cultural Antiproibicionista | Goiânia - GO', 'description' => $data['hero']['text'], 'canonical_url' => 'https://aculta.org/', 'og_title' => $data['organization'], 'og_description' => $data['hero']['text'], 'og_url' => 'https://aculta.org/'],
  'node' => ['title' => '[node:title] | [site:name]', 'description' => '[node:summary]', 'canonical_url' => '[node:url]', 'og_title' => '[node:title]', 'og_description' => '[node:summary]', 'og_url' => '[node:url]'],
];
foreach ($defaults as $id => $tags) {
  $entity = MetatagDefaults::load($id) ?? MetatagDefaults::create(['id' => $id, 'label' => $id]);
  $entity->overwriteTags($tags);
  $entity->save();
}
if (!FieldStorageConfig::loadByName('node', 'field_meta_tags')) {
  FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'field_meta_tags', 'type' => 'metatag'])->save();
}
foreach (array_keys($patterns) as $bundle) {
  if (!FieldConfig::loadByName('node', $bundle, 'field_meta_tags')) {
    FieldConfig::create(['entity_type' => 'node', 'bundle' => $bundle, 'field_name' => 'field_meta_tags', 'label' => 'SEO — títulos e descrições'])->save();
  }
  $display = EntityFormDisplay::load('node.' . $bundle . '.default');
  $display->setComponent('field_meta_tags', ['type' => 'metatag_firehose', 'weight' => 50])->save();
}
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
foreach ($state['nodes'] as $key => $id) {
  \Drupal::entityTypeManager()->getStorage('node')->resetCache([$id]);
  $node = Node::load($id);
  $node->get('path')->pathauto = \Drupal\pathauto\PathautoState::SKIP;
  if ($key !== 'home') {
    preg_match('@<p[^>]*>(.*?)</p>@s', $node->get('body')->value, $matches);
    $description = html_entity_decode(strip_tags($matches[1] ?? ''), ENT_QUOTES, 'UTF-8');
    $description = mb_substr($description, 0, 180);
    $node->set('field_meta_tags', ['value' => metatag_data_encode(['description' => $description])]);
  }
  $node->setNewRevision(TRUE);
  $node->save();
}

// Actual contact collection now uses Webform storage with restricted access.
$privacy = Node::load($state['nodes']['privacy']);
$body = str_replace('O formulário do Drupal não cria uma publicação pública da mensagem.', 'As mensagens são registradas no Webform e encaminhadas ao e-mail institucional. O acesso aos registros é restrito à administração; as mensagens não são publicadas no site. O formulário não registra o endereço IP do visitante.', $privacy->get('body')->value);
$privacy->set('body', ['value' => $body, 'format' => 'full_html'])->setNewRevision(TRUE);
$privacy->save();

// Sitemap includes public editorial content; excludes drafts and old contact node.
\Drupal::configFactory()->getEditable('simple_sitemap.settings')->set('base_url', 'https://aculta.org')->set('enabled_entity_types', ['node'])->set('cron_generate', TRUE)->save();
$generator = \Drupal::service('simple_sitemap.generator');
foreach (array_keys($patterns) as $bundle) {
  $generator->entityManager()->setBundleSettings('node', $bundle, ['index' => TRUE, 'priority' => '0.5', 'changefreq' => '', 'include_images' => FALSE]);
}
foreach (['home', 'contact'] as $key) {
  $generator->entityManager()->setEntityInstanceSettings('node', (string) $state['nodes'][$key], ['index' => FALSE]);
}
$generator->customLinkManager()->add('/', ['priority' => '1.0'])->add('/contato', ['priority' => '0.5']);
$generator->rebuildQueue()->generate('backend');

$state['cms_enhancements'] = TRUE;
\Drupal::state()->set('aculta.institution_setup', $state);
echo 'Webform, SEO metadata, automatic URL patterns, media library and sitemap configured locally.' . PHP_EOL;
