<?php

/**
 * @file
 * Read-only Config Sync preflight for the ACULTA shipping port.
 *
 * Run from any directory: php web/modules/custom/aculta_portal/modules/aculta_commerce/tests/scripts/shipping_sync_preflight.php
 * Does not inspect Runtime configuration or create a shipping rate.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__, 8);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
  fwrite(STDERR, "Composer autoload is missing; run from an installed ACULTA checkout.\n");
  exit(2);
}
require_once $autoload;

$config = $root . '/config/sync';
$read = static function (string $path): array {
  if (!is_file($path)) {
    return [];
  }
  $value = Yaml::parseFile($path);
  return is_array($value) ? $value : [];
};

$shippingMethods = glob($config . '/commerce_shipping.commerce_shipping_method.*.yml') ?: [];
sort($shippingMethods);
$order = $read($config . '/commerce_order.commerce_order_type.default.yml');
$defaultCheckout = $read($config . '/commerce_checkout.commerce_checkout_flow.default.yml');
$shippingCheckout = $read($config . '/commerce_checkout.commerce_checkout_flow.shipping.yml');
$shipment = $read($config . '/commerce_shipping.commerce_shipment_type.default.yml');

$variations = [];
$variationsAculta = [];
foreach (glob($config . '/commerce_product.commerce_product_variation_type.*.yml') ?: [] as $file) {
  $variation = $read($file);
  $id = (string) ($variation['id'] ?? basename($file));
  $traits = (array) ($variation['traits'] ?? []);
  $variations[$id] = in_array('purchasable_entity_shippable', $traits, TRUE);
  $variationsAculta[$id] = in_array('aculta_purchasable_entity_shippable', $traits, TRUE);
}
ksort($variations);
ksort($variationsAculta);

$result = [
  'scope' => 'versioned_config_only',
  'runtime_checked' => FALSE,
  'rates_calculated' => FALSE,
  'source' => 'config/sync',
  'shipping_methods_versioned' => count($shippingMethods),
  'shipping_method_config_files' => array_map('basename', $shippingMethods),
  'default_order_type_traits' => array_values((array) ($order['traits'] ?? [])),
  'default_order_type_shipment_type_aculta' => $order['third_party_settings']['aculta_commerce']['shipment_type'] ?? NULL,
  'default_order_type_shipment_type_commerce_shipping' => $order['third_party_settings']['commerce_shipping']['shipment_type'] ?? NULL,
  'shipment_type_default_present' => $shipment !== [],
  'aculta_shipment_type_versioned' => is_file($config . '/aculta_commerce.aculta_shipment_type.default.yml'),
  'shipments_field_storage_target' => $read($config . '/field.storage.commerce_order.shipments.yml')['settings']['target_type'] ?? NULL,
  'checkout_default_shipping_pane' => isset($defaultCheckout['configuration']['panes']['aculta_shipping_information']),
  'checkout_shipping_contrib_pane' => isset($shippingCheckout['configuration']['panes']['shipping_information']),
  'variation_types_shippable' => $variations,
  'variation_types_aculta_shippable' => $variationsAculta,
  'required_runtime_checks' => [
    'enabled_modules',
    'active_shipping_methods_and_store_assignment',
    'shipping_method_conditions',
    'order_type_shipping_traits_and_shipments_field',
    'order_checkout_flow_selected',
    'physical_order_item_weight_and_dimensions',
    'shipping_profile_and_valid_address',
    'packer_output_and_eligible_rates',
  ],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
