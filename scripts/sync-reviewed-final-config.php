<?php
/** Export ONLY the differences already enumerated and reviewed in tmp/final-config-review.json. */
use Symfony\Component\Yaml\Yaml;
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
$review = json_decode(file_get_contents(dirname(__DIR__) . '/tmp/final-config-review.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$active = \Drupal::service('config.storage');
$sync = new \Drupal\Core\Config\FileStorage(dirname(__DIR__) . '/config/sync');
$exported = [];
foreach ($review as $collection => $configs) {
  $a = $collection === 'default' ? $active : $active->createCollection($collection);
  $s = $collection === 'default' ? $sync : $sync->createCollection($collection);
  foreach ($configs as $name => $details) {
    if (!$a->exists($name)) {
      if (!in_array($name, ['olivero.settings', 'core.date_format.olivero_medium'], TRUE)) throw new RuntimeException('Unexpected deletion: ' . $name);
      $s->delete($name);
    } else $s->write($name, $a->read($name));
    $exported[$collection][] = $name;
  }
}
file_put_contents(__DIR__ . '/institution/FINAL-CONFIG-CHANGES.json', json_encode($exported, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
$count = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/config/sync', FilesystemIterator::SKIP_DOTS)) as $file) if ($file->getExtension() === 'yml') { Yaml::parseFile($file->getPathname()); $count++; }
echo "Reviewed differences exported; $count YAML files parsed.\n";
