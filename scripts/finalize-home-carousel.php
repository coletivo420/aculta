<?php
/** Local targeted configuration export after Olivero removal. */
use Symfony\Component\Yaml\Yaml;
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
$view = \Drupal\views\Entity\View::load('home_editorial_highlights');
$displays = $view->get('display');
$displays['block_1']['display_options']['block_hide_empty'] = TRUE;
$view->set('display', $displays)->save();
$storage = \Drupal::service('locale.storage');
foreach ([
  'Previous Slide' => 'Anterior', 'Next Slide' => 'Próximo', 'Pause carousel' => 'Pausar carrossel',
  'Play carousel' => 'Reproduzir carrossel', 'Carousel Controls' => 'Controles do carrossel',
  'Slide navigation' => 'Navegação dos destaques', 'Slide @index of @count' => 'Destaque @index de @count',
  'Go to slide group @num' => 'Ir para o grupo de destaques @num',
  'Carousel paused due to reduced motion preference' => 'Carrossel pausado pela preferência de movimento reduzido',
] as $source => $translation) {
  $string = $storage->findString(['source' => $source]) ?? $storage->createString(['source' => $source])->save();
  $translated = $storage->findTranslation(['lid' => $string->lid, 'language' => 'pt-br']) ?? $storage->createTranslation(['lid' => $string->lid, 'language' => 'pt-br']);
  $translated->setValues(['language' => 'pt-br', 'lid' => $string->lid, 'customized' => 1])->setString($translation)->save();
}
$names = \Drupal::state()->get('aculta.home_carousel_ready')['config'];
$active = \Drupal::service('config.storage');
$directory = dirname(__DIR__) . '/config/sync/';
foreach ($names as $name) file_put_contents($directory . $name . '.yml', Yaml::dump($active->read($name), 12, 2));
// Only remove exported configs actually removed by the explicitly requested uninstall.
$removed = [];
foreach (glob($directory . 'block.block.olivero_*.yml') as $file) {
  $name = basename($file, '.yml');
  if (!$active->exists($name)) {unlink($file); $removed[] = $name;}
}
$localized = $active->createCollection('language.pt-br');
foreach (glob($directory . 'language/pt-br/block.block.olivero_*.yml') as $file) {
  $name = basename($file, '.yml');
  if (!$localized->exists($name)) unlink($file);
}
\Drupal::state()->set('aculta.home_carousel_removed_config', $removed);
\Drupal::service('cache.render')->invalidateAll();
echo json_encode(['exported' => $names, 'removed' => $removed], JSON_PRETTY_PRINT) . PHP_EOL;
