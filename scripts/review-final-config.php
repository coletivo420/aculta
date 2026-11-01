<?php
/** Read and classify every active/sync difference before any export. */
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
$active = \Drupal::service('config.storage');
$sync = new \Drupal\Core\Config\FileStorage(dirname(__DIR__) . '/config/sync');
$paths = static function($a, $b, string $prefix = '') use (&$paths): array {
  if (!is_array($a) || !is_array($b)) return $a == $b ? [] : [$prefix];
  $out = [];
  foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $key) $out = array_merge($out, $paths($a[$key] ?? NULL, $b[$key] ?? NULL, ltrim($prefix . '.' . $key, '.')));
  return $out;
};
$diff = [];
foreach (array_merge([''], $active->getAllCollectionNames(), $sync->getAllCollectionNames()) as $collection) {
  $a = $collection === '' ? $active : $active->createCollection($collection);
  $s = $collection === '' ? $sync : $sync->createCollection($collection);
  foreach (array_unique(array_merge($a->listAll(), $s->listAll())) as $name) {
    if ($a->read($name) != $s->read($name)) $diff[$collection ?: 'default'][$name] = ['paths' => $paths($a->read($name), $s->read($name)), 'active_exists' => $a->exists($name), 'sync_exists' => $s->exists($name)];
  }
}
file_put_contents(dirname(__DIR__) . '/tmp/final-config-review.json', json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
