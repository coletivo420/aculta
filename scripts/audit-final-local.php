<?php
/** Read-only final inventory; excludes credentials, users and private submissions. */
use Symfony\Component\Yaml\Yaml;
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
$result = ['site' => \Drupal::config('system.site')->getRawData(), 'theme' => \Drupal::config('system.theme')->getRawData(), 'modules' => array_keys(\Drupal::moduleHandler()->getModuleList()), 'content' => [], 'config_diff' => ['changed' => [], 'active_only' => [], 'sync_only' => []]];
foreach (['node','block_content','menu_link_content','path_alias','redirect','media','file'] as $type) {
  foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) {
    $result['content'][$type][$entity->id()] = ['label' => $entity->label(), 'uuid' => $entity->uuid(), 'bundle' => $entity->bundle(), 'values' => $entity->toArray()];
  }
}
$result['state'] = ['institution' => \Drupal::state()->get('aculta.institution_setup'), 'carousel' => \Drupal::state()->get('aculta.home_carousel_ready')];
$active = \Drupal::service('config.storage');
$sync = new \Drupal\Core\Config\FileStorage(dirname(__DIR__) . '/config/sync');
foreach (array_unique(array_merge($active->listAll(), $sync->listAll())) as $name) {
  $a = $active->read($name); $s = $sync->read($name);
  if ($a === FALSE) $result['config_diff']['sync_only'][] = $name;
  elseif ($s === FALSE) $result['config_diff']['active_only'][] = $name;
  elseif ($a != $s) $result['config_diff']['changed'][] = $name;
}
$result['collections'] = [];
foreach (array_unique(array_merge($active->getAllCollectionNames(), $sync->getAllCollectionNames())) as $collection) {
  $a = $active->createCollection($collection); $s = $sync->createCollection($collection);
  foreach (array_unique(array_merge($a->listAll(), $s->listAll())) as $name) if ($a->read($name) != $s->read($name)) $result['collections'][$collection][] = $name;
}
$result['views'] = [];
foreach (\Drupal\views\Entity\View::loadMultiple() as $view) $result['views'][$view->id()] = $view->toArray();
$result['blocks'] = [];
foreach (\Drupal\block\Entity\Block::loadMultiple() as $block) $result['blocks'][$block->id()] = $block->toArray();
$result['types'] = [];
foreach (['node_type','block_content_type','media_type','menu','webform'] as $type) foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) $result['types'][$type][$entity->id()] = $entity->toArray();
$result['assets'] = [];
$base = DRUPAL_ROOT . '/sites/default/files';
if (is_dir($base)) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $result['assets'][] = ['path' => str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1)), 'size' => $file->getSize()];
$result['editor'] = \Drupal::config('editor.editor.full_html')->getRawData();
$result['privacy_tools'] = ['filter' => \Drupal::config('filter.format.full_html')->getRawData(), 'mail_system' => \Drupal::config('system.mail')->getRawData()];
file_put_contents(dirname(__DIR__) . '/tmp/final-local-audit.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo json_encode(['mail' => $result['site']['mail'], 'front' => $result['site']['page']['front'], 'config_diff' => $result['config_diff'], 'collection_diff' => $result['collections'], 'counts' => array_map('count', $result['content']), 'asset_count' => count($result['assets'])], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
