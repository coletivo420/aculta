<?php
echo 'Modules: ' . implode(', ', array_keys(\Drupal::moduleHandler()->getModuleList())) . PHP_EOL;
foreach (['node_type', 'block_content_type', 'filter_format', 'contact_form'] as $type) {
  if (!\Drupal::entityTypeManager()->hasDefinition($type)) {
    continue;
  }
  foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) {
    echo $type . ': ' . $entity->id() . ' / ' . $entity->label() . PHP_EOL;
  }
}
echo 'Nodes: ' . \Drupal::entityTypeManager()->getStorage('node')->getQuery()->accessCheck(FALSE)->count()->execute() . PHP_EOL;
echo 'Custom blocks: ' . \Drupal::entityTypeManager()->getStorage('block_content')->getQuery()->accessCheck(FALSE)->count()->execute() . PHP_EOL;
echo 'Front: ' . \Drupal::config('system.site')->get('page.front') . PHP_EOL;
echo 'Full HTML filters: ' . json_encode(\Drupal::config('filter.format.full_html')->get('filters')) . PHP_EOL;
foreach (\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => 'aculta420']) as $block) {
  echo 'Block: ' . $block->id() . ' / ' . $block->getPluginId() . ' / ' . $block->getRegion() . PHP_EOL;
}
