<?php
foreach (['footer', 'tools', 'content', 'navigation-user-links'] as $menu) {
  $links = \Drupal::service('plugin.manager.menu.link')->loadLinksByRoute('', [], $menu);
  $count = \Drupal::service('menu.link_tree')->load($menu, new \Drupal\Core\Menu\MenuTreeParameters());
  echo $menu . ': ' . count($count) . ' roots' . PHP_EOL;
}
echo 'Contact uninstall blockers: ' . json_encode(\Drupal::service('module_installer')->validateUninstall(['contact'])) . PHP_EOL;
foreach (['contact','aculta_contact'] as $id) {
  $count = \Drupal::entityTypeManager()->getStorage('webform_submission')->getQuery()->accessCheck(FALSE)->condition('webform_id', $id)->count()->execute();
  echo "Webform $id submissions: $count\n";
}
foreach (['archive', 'content_recent', 'frontpage', 'glossary', 'taxonomy_term', 'who_s_new', 'who_s_online', 'vvjb_example'] as $id) {
  $dependents = \Drupal::service('config.manager')->getConfigDependencyManager()->getDependentEntities('config', 'views.view.' . $id);
  echo $id . ' dependencies: ' . implode(', ', array_keys($dependents)) . PHP_EOL;
}
