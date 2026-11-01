<?php

/** @file Logical local backup before additive editorial changes. */
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) {
  throw new RuntimeException('Local execution only.');
}
$directory = dirname(__DIR__) . '/tmp/seo-content-apoio/before';
if (is_file($directory . '/manifest.json')) {
  throw new RuntimeException('Backup already exists; do not overwrite.');
}
mkdir($directory, 0775, TRUE);
$storage = \Drupal::service('config.storage');
$manifest = ['config' => [], 'content' => [], 'created' => date(DATE_ATOM)];
foreach (array_merge([''], $storage->getAllCollectionNames()) as $collection) {
  $source = $collection === '' ? $storage : $storage->createCollection($collection);
  $target = new \Drupal\Core\Config\FileStorage($directory . '/config', $collection);
  foreach ($source->listAll() as $name) {
    $target->write($name, $source->read($name));
    $manifest['config'][$collection ?: 'default'][] = $name;
  }
}
foreach (['node', 'block_content', 'menu_link_content', 'path_alias', 'redirect', 'taxonomy_term'] as $type) {
  $values = [];
  foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) {
    $values[$entity->uuid()] = $entity->toArray();
    $manifest['content'][$type][] = ['uuid' => $entity->uuid(), 'id' => $entity->id(), 'bundle' => $entity->bundle(), 'label' => $entity->label()];
  }
  file_put_contents($directory . '/' . $type . '.json', json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}
file_put_contents($directory . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo "Logical backup written to ignored tmp directory.\n";
