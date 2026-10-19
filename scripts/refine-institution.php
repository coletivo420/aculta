<?php

/** Non-destructive local refinements after the first institutional installation. */
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) {
  throw new RuntimeException('Local site only.');
}
$state = \Drupal::state()->get('aculta.institution_setup', []);
if (!empty($state['complete'])) {
  $default_front = \Drupal\views\Entity\View::load('frontpage');
  if ($default_front && $default_front->status()) {
    $default_front->disable()->save();
    echo 'Disabled unused default frontpage View and its RSS display; configuration preserved.' . PHP_EOL;
  }
}
$home_link = \Drupal\menu_link_content\Entity\MenuLinkContent::load($state['menus']['main_home'] ?? 0);
if ($home_link) {
  $standard = \Drupal::service('plugin.manager.menu.link')->getDefinition('standard.front_page', FALSE);
  if ($standard && $home_link->get('link')->uri === 'route:<front>') {
    $home_link->set('enabled', FALSE)->save();
    echo 'Disabled installer Home link; preserved standard.front_page.' . PHP_EOL;
  }
  foreach (\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'main', 'enabled' => TRUE]) as $link) {
    if ($link->id() !== $home_link->id() && $link->getUrlObject()->toString() === $home_link->getUrlObject()->toString()) {
      $home_link->set('enabled', FALSE)->save();
      echo 'Disabled duplicate installer Home link; preserved existing Home link.' . PHP_EOL;
      break;
    }
  }
}
if (!empty($state['official_applied'])) {
  $data_entity = \Drupal\block_content\Entity\BlockContent::load($state['blocks']['institution_data']);
  foreach (\Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['type' => 'aculta_institution']) as $record) {
    if ($record->id() !== $data_entity->id() && $record->label() === 'Dados oficiais da Associação') {
      $record->set('info', 'Dados institucionais anteriores — preservados, sem uso público')->save();
    }
  }
  \Drupal::configFactory()->getEditable('aculta_portal.settings')->set('institution_data_uuid', $data_entity->uuid())->save();
  foreach (['aculta_institution_data', 'aculta_footer_data', 'aculta_contact_data', 'aculta_data_institutional', 'aculta_data_registration'] as $id) {
    $placement = \Drupal\block\Entity\Block::load($id);
    if ($placement && $placement->getPluginId() !== 'block_content:' . $data_entity->uuid()) {
      $placement->set('plugin', 'block_content:' . $data_entity->uuid())->save();
    }
  }
  // Preserve the active form module, retaining the earlier page and revisions.
  $contact_path = !empty($state['cms_enhancements']) ? '/webform/aculta_contact' : '/contact/aculta_contact';
  $contact_uri = !empty($state['cms_enhancements']) ? 'entity:webform/aculta_contact' : 'internal:/contact/aculta_contact';
  $aliases = \Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['alias' => '/contato', 'langcode' => 'pt-br']);
  foreach ($aliases as $alias) {
    if ($alias->getPath() !== $contact_path) {
      $alias->setPath($contact_path)->save();
    }
  }
  foreach (['main_contact'] as $key) {
    $link = \Drupal\menu_link_content\Entity\MenuLinkContent::load($state['menus'][$key] ?? 0);
    if ($link && $link->get('link')->uri !== $contact_uri) {
      $link->set('link', ['uri' => $contact_uri])->save();
    }
  }
}
if (!empty($state['cms_enhancements']) && \Drupal::moduleHandler()->moduleExists('media_library')) {
  $metatags = \Drupal\metatag\Entity\MetatagDefaults::load('global');
  if ($metatags && empty($metatags->get('tags')['description'])) {
    $metatags->overwriteTags(['description' => 'Site oficial da Associação Cultural Antiproibicionista em Goiânia: cultura, informação, cuidado e participação social.']);
    $metatags->save();
  }
  $webform = \Drupal\webform\Entity\Webform::load('aculta_contact');
  if ($webform && $webform->getSetting('page_submit_path') !== '/contato') {
    $webform->setSetting('page_submit_path', '/contato');
    $webform->set('description', 'Entre em contato com a Associação Cultural Antiproibicionista. Informações institucionais, endereço oficial e formulário de contato.');
    $webform->save();
    echo 'Webform canonical page set to /contato.' . PHP_EOL;
  }
  // A partial migration can leave more than one alias for the same form route.
  // Align all retained records so Drupal cannot select a competing canonical URL.
  foreach (\Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['path' => '/webform/aculta_contact']) as $alias) {
    if ($alias->getAlias() !== '/contato') {
      $alias->setAlias('/contato')->save();
    }
  }
  $format = \Drupal\filter\Entity\FilterFormat::load('full_html');
  $editor = \Drupal\editor\Entity\Editor::load('full_html');
  if ($format && $editor && empty($state['media_editor_ready'])) {
    $filters = $format->get('filters');
    $filters['media_embed'] = ['id' => 'media_embed', 'provider' => 'media', 'status' => TRUE, 'weight' => 20, 'settings' => ['default_view_mode' => 'default', 'allowed_view_modes' => [], 'allowed_media_types' => ['image', 'document', 'remote_video']]];
    $format->set('filters', $filters)->save();
    $settings = $editor->getSettings();
    $settings['toolbar']['items'][] = 'drupalMedia';
    $settings['toolbar']['items'] = array_values(array_unique($settings['toolbar']['items']));
    $editor->setSettings($settings)->save();
    $state['media_editor_ready'] = TRUE;
    \Drupal::state()->set('aculta.institution_setup', $state);
    echo 'Media Library insertion enabled in CKEditor 5 Full HTML.' . PHP_EOL;
  }
}
echo 'Local refinement complete.' . PHP_EOL;
