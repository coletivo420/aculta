<?php

/**
 * @file
 * Read-only runtime inventory for Commerce Shipping / ACULTA Commerce.
 *
 * Run only through a bootstrapped, read-only Drush session:
 * vendor/bin/drush --root=web --uri=https://HOST php:script web/modules/custom/aculta_portal/modules/aculta_commerce/tests/scripts/shipping_runtime_preflight.php
 *
 * No secrets, addresses, order IDs, customer data, order loading or rates.
 */

declare(strict_types=1);

if (!class_exists(\Drupal::class)) {
  fwrite(STDERR, "Requires bootstrapped Drupal through Drush php:script.\n");
  exit(2);
}

$modules = \Drupal::moduleHandler();
$entities = \Drupal::entityTypeManager();
$configs = \Drupal::configFactory();

$inventory = [
  'scope' => 'runtime_read_only',
  'does_not_determine_real_rate_availability' => TRUE,
  'module_enabled' => [
    'aculta_commerce' => $modules->moduleExists('aculta_commerce'),
    'commerce_shipping' => $modules->moduleExists('commerce_shipping'),
    'aculta_commerce_melhorenvio' => $modules->moduleExists('aculta_commerce_melhorenvio'),
  ],
  'entity_definitions' => [
    'commerce_shipping_method' => $entities->hasDefinition('commerce_shipping_method'),
    'commerce_shipment' => $entities->hasDefinition('commerce_shipment'),
    'commerce_order' => $entities->hasDefinition('commerce_order'),
  ],
  'shipping_method_count' => NULL,
  'enabled_shipping_method_count' => NULL,
  'shipping_method_status_unknown_count' => NULL,
  'shipment_count' => NULL,
  'order_types' => [],
  'checkout_panes' => [],
  'variation_types_shippable' => [],
];

if ($entities->hasDefinition('commerce_shipping_method')) {
  $methods = $entities->getStorage('commerce_shipping_method')->loadMultiple();
  $inventory['shipping_method_count'] = count($methods);
  $enabled = 0;
  $unknown = 0;
  foreach ($methods as $method) {
    if (method_exists($method, 'isEnabled')) {
      $enabled += $method->isEnabled() ? 1 : 0;
    }
    elseif (method_exists($method, 'status')) {
      $enabled += $method->status() ? 1 : 0;
    }
    else {
      $unknown++;
    }
  }
  $inventory['enabled_shipping_method_count'] = $enabled;
  $inventory['shipping_method_status_unknown_count'] = $unknown;
}

if ($entities->hasDefinition('commerce_shipment')) {
  // An aggregate count only; do not load records or print entity identifiers.
  $inventory['shipment_count'] = (int) $entities->getStorage('commerce_shipment')->getQuery()
    ->accessCheck(FALSE)
    ->count()
    ->execute();
}

foreach ($configs->listAll('commerce_order.commerce_order_type.') as $name) {
  $config = $configs->get($name);
  $inventory['order_types'][(string) $config->get('id')] = [
    'traits' => array_values((array) $config->get('traits')),
    'workflow' => $config->get('workflow'),
  ];
}

foreach ($configs->listAll('commerce_checkout.commerce_checkout_flow.') as $name) {
  $config = $configs->get($name);
  $panes = array_keys((array) $config->get('configuration.panes'));
  $inventory['checkout_panes'][(string) $config->get('id')] = array_values(array_filter(
    $panes,
    static fn(string $id): bool => str_contains($id, 'shipping'),
  ));
}

foreach ($configs->listAll('commerce_product.commerce_product_variation_type.') as $name) {
  $config = $configs->get($name);
  $inventory['variation_types_shippable'][(string) $config->get('id')] =
    in_array('purchasable_entity_shippable', (array) $config->get('traits'), TRUE);
}

ksort($inventory['order_types']);
ksort($inventory['checkout_panes']);
ksort($inventory['variation_types_shippable']);

echo json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
