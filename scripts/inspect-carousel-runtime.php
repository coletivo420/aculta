<?php
if (in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) {
  // The local HTTP runtime uses prod; Drush uses a separate CLI container.
  $kernel = \Drupal\Core\DrupalKernel::createFromRequest(\Drupal::request(), \Drupal::service('class_loader'), 'prod');
  $kernel->boot();
  $kernel->rebuildContainer();
  echo "Local HTTP container rebuilt.\n";
  return;
}
$class = \Drupal\vvjb\Plugin\views\style\BasicCarousel::class;
$loader = \Drupal::service('class_loader');
echo json_encode(['class' => $class, 'file' => $loader->findFile($class), 'exists' => class_exists($class), 'modules' => array_filter(\Drupal::getContainer()->getParameter('container.namespaces'), static fn($k) => str_contains($k, 'vvj'), ARRAY_FILTER_USE_KEY)]) . PHP_EOL;
$view = \Drupal\views\Views::getView('home_editorial_highlights');
$view->setDisplay('block_1'); $view->execute();
echo 'rows=' . count($view->result) . PHP_EOL;
