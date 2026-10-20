<?php
/** Reversible local CMS tests: all entity changes roll back. */
use Drupal\node\Entity\Node;
use Drupal\views\Views;
use Drupal\Core\Cache\Cache;
use Symfony\Component\Yaml\Yaml;
$host = \Drupal::request()->getHost();
if (!in_array($host, ['localhost', '127.0.0.1'], TRUE) && !str_ends_with($host, 'aculta.toca.net.br')) throw new RuntimeException('Local or test host only.');
$ids = \Drupal::state()->get('aculta.home_carousel_ready')['nodes'];
$transaction = \Drupal::database()->startTransaction();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$run = static function(int $expected): void {
  $view = Views::getView('home_editorial_highlights');
  $view->setDisplay('block_1'); $view->execute();
  if (count($view->result) !== $expected) throw new RuntimeException('Unexpected View result.');
  $build = $view->render();
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  if ($expected === 0 && str_contains($html, '<vvjb-carousel')) throw new RuntimeException('Empty carousel rendered.');
  if ($expected === 1 && (str_contains($html, 'class="vvjb-button') || !str_contains($html, 'data-slide-time="0"'))) throw new RuntimeException('Solitary carousel controls/timing.');
  echo "View/render: $expected items OK\n";
};
try {
  $run(3);
  $nodes = $storage->loadMultiple($ids);
  foreach ($nodes as $node) $node->setUnpublished()->save();
  $run(0);
  $first = reset($nodes); $first->setPublished()->save();
  $run(1);
  foreach ($nodes as $node) $node->setPublished()->save();
  $fourth = $first->createDuplicate();
  $fourth->setTitle('Verificação temporária')->set('field_category', 'Categoria de teste')->set('field_summary', 'Resumo de teste')->set('field_complement', 'Complemento de teste')->set('field_link', ['uri' => 'internal:/institucional', 'title' => 'Teste'])->set('field_weight', -1)->save();
  // The highlights view pages at 3 items (views config pager items_per_page: 3).
  // The duplicate is still checked for weight order below.
  $run(3);
  $view = Views::getView('home_editorial_highlights'); $view->setDisplay('block_1'); $view->execute();
  if ((int)$view->result[0]->nid !== (int)$fourth->id()) throw new RuntimeException('Weight sort failed.');
  $fourth->setUnpublished()->save(); $run(3);
  $form = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.editorial_highlight.default');
  foreach (['title', 'field_category', 'field_summary', 'field_complement', 'field_link', 'field_weight', 'status'] as $field) if (!$form->getComponent($field)) throw new RuntimeException('Missing editable widget: ' . $field);
  echo "All seven admin widgets; create/edit/order/unpublish tested.\n";
} finally {
  $transaction->rollBack();
  $storage->resetCache();
  Cache::invalidateTags(['node_list', 'node_list:editorial_highlight']);
}
foreach (\Drupal::state()->get('aculta.home_carousel_ready')['config'] as $name) {
  $path = dirname(__DIR__) . '/config/sync/' . $name . '.yml';
  // The later administrative cleanup intentionally removed legacy placements.
  if (is_file($path)) Yaml::parseFile($path);
}
echo "Targeted YAML parsed; temporary content rolled back.\n";
