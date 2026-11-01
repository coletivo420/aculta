<?php
$manager = \Drupal::entityTypeManager();
foreach ([
  'aculta_contribution' => 'Contribuições customizadas',
  'commerce_order' => 'Pedidos Commerce',
  'commerce_payment' => 'Pagamentos Commerce',
  'commerce_store' => 'Lojas Commerce',
  'profile' => 'Perfis',
] as $id => $label) {
  if (!$manager->hasDefinition($id)) { print $label . ': entity ausente' . PHP_EOL; continue; }
  $count = $manager->getStorage($id)->getQuery()->accessCheck(FALSE)->count()->execute();
  print $label . ': ' . $count . PHP_EOL;
}
