<?php
/** Read-only inventory of administrative structures and actual usage. */
$result = [];
foreach (['node_type', 'block_content_type', 'menu', 'view', 'block', 'taxonomy_vocabulary', 'media_type', 'contact_form', 'webform', 'filter_format'] as $type) {
  if (!\Drupal::entityTypeManager()->hasDefinition($type)) continue;
  foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) {
    $entry = ['label' => $entity->label(), 'enabled' => $entity->status()];
    if ($type === 'block') $entry += ['theme' => $entity->getTheme(), 'region' => $entity->getRegion(), 'plugin' => $entity->getPluginId()];
    if ($type === 'view') $entry['displays'] = array_map(static fn($d) => ['plugin' => $d['display_plugin'], 'path' => $d['display_options']['path'] ?? NULL], $entity->get('display'));
    if ($type === 'menu') $entry['links'] = count(\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => $entity->id()]));
    $content_type = ['node_type' => 'node', 'block_content_type' => 'block_content', 'media_type' => 'media', 'taxonomy_vocabulary' => 'taxonomy_term'][$type] ?? NULL;
    if ($content_type) {
      $key = \Drupal::entityTypeManager()->getDefinition($content_type)->getKey('bundle');
      $entry['entities'] = \Drupal::entityTypeManager()->getStorage($content_type)->getQuery()->accessCheck(FALSE)->condition($key, $entity->id())->count()->execute();
    }
    $result[$type][$entity->id()] = $entry;
  }
}
foreach (['node', 'block_content', 'menu_link_content'] as $type) {
  foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) {
    $result['content'][$type][$entity->id()] = ['label' => $entity->label(), 'bundle' => $entity->bundle(), 'uuid' => $entity->uuid()];
    if ($type === 'menu_link_content') $result['content'][$type][$entity->id()] += ['menu' => $entity->getMenuName(), 'enabled' => $entity->isEnabled(), 'uri' => $entity->get('link')->uri];
  }
}
$storage = \Drupal::service('config.storage');
$configs = [];
foreach ($storage->listAll() as $name) $configs[$name] = $storage->read($name);
$result['configuration'] = $configs;
$result['themes'] = \Drupal::config('system.theme')->getRawData();
file_put_contents(dirname(__DIR__) . '/tmp/admin-structure-audit.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
foreach (['node_type','block_content_type','menu','taxonomy_vocabulary','media_type','contact_form','webform'] as $type) echo $type . ': ' . json_encode($result[$type] ?? [], JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo 'Views: ' . implode(', ', array_keys($result['view'])) . PHP_EOL;
echo "Full local inventory saved in ignored tmp/.\n";
