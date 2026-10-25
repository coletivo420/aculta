<?php

/** Read-only validation of the local institutional implementation. */

// Gate de Drupal: a validação de Views precisa do bootstrap. Execução: drush scr (ver docs/portal/FRONTEIRA-MODULO-TEMA.md).
if (!class_exists(\Drupal::class)) {
  fwrite(STDERR, "validate-institution é um gate de Drupal. Execute: php vendor/drush/drush/drush.php --uri=<host> scr scripts/validate-institution.php\n");
  exit(2);
}

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420';
if (is_dir($root . '/config/sync')) {
  $count = 0;
  foreach (glob($root . '/config/sync/*.yml') as $path) {
    Yaml::parseFile($path);
    $count++;
  }
  echo 'Exported YAML files parsed: ' . $count . PHP_EOL;
}
$twig = \Drupal::service('twig');
$twig->getLoader()->addLoader(new \Twig\Loader\FilesystemLoader($theme . '/templates'), TRUE);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme)) as $file) {
  if (!$file->isFile()) {
    continue;
  }
  if (in_array($file->getExtension(), ['yml', 'yaml'], TRUE)) {
    Yaml::parseFile($file->getPathname());
    echo 'YAML OK: ' . $file->getFilename() . PHP_EOL;
  }
  if (str_ends_with($file->getFilename(), '.html.twig')) {
    $source = new \Twig\Source(file_get_contents($file->getPathname()), $file->getFilename(), $file->getPathname());
    $twig->compile($twig->parse($twig->tokenize($source)));
    echo 'Twig OK: ' . $file->getFilename() . PHP_EOL;
  }
}
$state = \Drupal::state()->get('aculta.institution_setup', []);
if (empty($state['complete'])) {
  throw new RuntimeException('Installation incomplete.');
}
foreach ($state['nodes'] as $key => $id) {
  $node = \Drupal\node\Entity\Node::load($id);
  if ($key === 'contact') {
    // The historical node remains unpublished. The open institutional Webform
    // is the canonical public /contato experience.
    continue;
  }
  if ($key === 'cnpj_document') {
    if (!$node || $node->isPublished() || !$node->get('field_document')->isEmpty()) {
      throw new RuntimeException('CNPJ PDF draft should remain private until the document is supplied.');
    }
    echo 'CNPJ document: unpublished draft, no invented download.' . PHP_EOL;
    continue;
  }
  // The home composes its public content from hero fields and the home_* blocks
  // (home-content.json), so its body is intentionally empty.
  $has_content = $key === 'home'
    ? $node && !$node->get('field_hero_title')->isEmpty()
    : $node && !$node->get('body')->isEmpty();
  if (!$node || !$node->isPublished() || !$has_content) {
    throw new RuntimeException('Missing public content: ' . $key);
  }
  echo 'Content OK: ' . $key . ' / ' . $node->toUrl()->toString() . PHP_EOL;
}
if (!empty($state['cms_enhancements'])) {
  $contact_webform = \Drupal\webform\Entity\Webform::load('aculta_contact');
  $contact_path = $contact_webform ? parse_url($contact_webform->toUrl('canonical')->toString(), PHP_URL_PATH) : NULL;
  if (!$contact_webform || !$contact_webform->isOpen() || $contact_path !== '/contato') {
    throw new RuntimeException('The open institutional Webform must own the public /contato route.');
  }
  echo 'Public contact route is owned by the open aculta_contact Webform; legacy node is not required.' . PHP_EOL;
}
foreach (\Drupal::menuTree()->load('main', new \Drupal\Core\Menu\MenuTreeParameters()) as $item) {
  if (!$item->link->isEnabled()) {
    continue;
  }
  echo 'Main menu: ' . $item->link->getPluginId() . ' / ' . $item->link->getTitle() . ' / ' . $item->link->getUrlObject()->toString() . PHP_EOL;
}
if (!empty($state['cms_enhancements'])) {
  $webform = \Drupal\webform\Entity\Webform::load('aculta_contact');
  if (!$webform || !$webform->isOpen() || count($webform->getElementsDecoded()) !== 5) {
    throw new RuntimeException('Institutional Webform is not configured.');
  }
  if (!$webform->getSetting('form_disable_remote_addr')) {
    throw new RuntimeException('IP collection is unexpectedly enabled.');
  }
  if ($webform->getHandlers('email')->count() !== 1) {
    throw new RuntimeException('Expected one institutional email handler.');
  }
  foreach (['page', 'activity', 'document'] as $bundle) {
    if (!\Drupal\pathauto\Entity\PathautoPattern::load('aculta_' . $bundle) || !\Drupal\field\Entity\FieldConfig::loadByName('node', $bundle, 'field_meta_tags')) {
      throw new RuntimeException('Missing CMS pattern or SEO field: ' . $bundle);
    }
  }
  $xml = \Drupal::service('simple_sitemap.generator')->getContent();
  if (!$xml || !simplexml_load_string($xml)) {
    throw new RuntimeException('Sitemap XML is not generated.');
  }
  echo 'Webform, metadata fields, Pathauto patterns and sitemap XML: OK.' . PHP_EOL;
  $editor = \Drupal\editor\Entity\Editor::load('revista_editorial');
  $definitions = \Drupal::service('plugin.manager.ckeditor5.plugin')->getEnabledDefinitions($editor);
  if (!isset($definitions['media_library_mediaLibrary'])) {
    throw new RuntimeException('Media Library is not enabled in the content editor.');
  }
  echo 'CKEditor Media Library: OK.' . PHP_EOL;
}
// home_participation foi aposentado por decisão do responsável (2026-10-20): a home usa os blocos de parceria e de fortalecimento.
foreach ($state['blocks'] as $key => $id) {
  if ($key === 'home_participation') continue;
  $block = \Drupal\block_content\Entity\BlockContent::load($id);
  if (!$block || ($block->hasField('body') && $block->get('body')->isEmpty())) {
    throw new RuntimeException('Missing block content: ' . $key);
  }
}
foreach (['aculta_news', 'aculta_activities', 'aculta_documents'] as $id) {
  $view = \Drupal\views\Views::getView($id);
  $view->setDisplay('block_1');
  $view->execute();
  echo 'View OK: ' . $id . ' / rows: ' . count($view->result) . PHP_EOL;
}
echo 'Institutional local checks passed.' . PHP_EOL;
