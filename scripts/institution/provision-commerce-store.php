<?php

declare(strict_types=1);

use Drupal\commerce_store\Entity\Store;

/**
 * Provisions the single ACULTA Commerce store from the canonical site data.
 *
 * Run locally with:
 *   php vendor/drush/drush/drush.php php:script scripts/institution/provision-commerce-store.php
 *
 * This script creates a Commerce content entity. It is intentionally
 * idempotent and fails closed if the canonical institutional record changes,
 * if BRL is unavailable, or if another store already exists.
 */
$store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
$stores = $store_storage->loadMultiple();

if (count($stores) > 1) {
  throw new RuntimeException('More than one Commerce store exists; review them before provisioning.');
}

$site_name = (string) \Drupal::config('system.site')->get('name');
$institution = \Drupal::entityTypeManager()
  ->getStorage('block_content')
  ->loadByProperties(['uuid' => '80f3fc02-39b5-4386-8a32-78301b635007']);
$institution = reset($institution);

if (!$institution || $institution->bundle() !== 'aculta_institution') {
  throw new RuntimeException('The canonical institutional data block is missing.');
}

$read_value = static function (string $field_name) use ($institution): string {
  if (!$institution->hasField($field_name) || $institution->get($field_name)->isEmpty()) {
    throw new RuntimeException(sprintf('Canonical institutional field %s is missing.', $field_name));
  }
  return trim((string) $institution->get($field_name)->value);
};

$cnpj = $read_value('field_cnpj');
$email = $read_value('field_email');
$address = preg_split('/\R/u', $read_value('field_address')) ?: [];
$address = array_values(array_filter(array_map('trim', $address), static fn(string $line): bool => $line !== ''));

if ($site_name !== 'Associação Cultural Antiproibicionista'
  || $cnpj !== '68.238.467/0001-08'
  || $email !== '4e20coletivo@gmail.com'
  || $address !== [
    'Avenida Cristóvão Colombo, 736',
    'Quadra 205, Lote 27, Sala 3',
    'Jardim Novo Mundo',
    'Goiânia - GO',
    'CEP 74705-130',
    'Brasil',
  ]) {
  throw new RuntimeException('Canonical institution data differs from the reviewed local record; do not create a store until reviewed.');
}

if (!\Drupal::entityTypeManager()->getStorage('commerce_currency')->load('BRL')) {
  throw new RuntimeException('BRL is not configured in Commerce.');
}

$store_data = [
  'uuid' => '32f75ad2-5d45-48bb-898e-d657d47d04f8',
  'type' => 'online',
  'name' => $site_name,
  'mail' => $email,
  'default_currency' => 'BRL',
  'timezone' => 'America/Sao_Paulo',
  'address' => [
    'country_code' => 'BR',
    'administrative_area' => 'GO',
    'locality' => 'Goiânia',
    'postal_code' => '74705-130',
    'address_line1' => 'Avenida Cristóvão Colombo, 736',
    'address_line2' => 'Quadra 205, Lote 27, Sala 3, Jardim Novo Mundo',
    'organization' => $site_name,
  ],
  'status' => TRUE,
  'is_default' => TRUE,
];

if ($stores) {
  $store = reset($stores);
  $existing = [
    'uuid' => $store->uuid(),
    'type' => $store->bundle(),
    'name' => $store->label(),
    'mail' => (string) $store->getEmail(),
    'default_currency' => $store->getDefaultCurrencyCode(),
    'is_default' => (bool) $store->isDefault(),
    'status' => (bool) $store->get('status')->value,
  ];
  foreach (['uuid', 'type', 'name', 'mail', 'default_currency', 'is_default', 'status'] as $key) {
    if ($existing[$key] !== $store_data[$key]) {
      throw new RuntimeException('An existing Commerce store does not match the reviewed institutional store; no changes made.');
    }
  }
  $existing_address = $store->get('address')->first()?->getValue() ?? [];
  foreach ($store_data['address'] as $key => $value) {
    if (($existing_address[$key] ?? '') !== $value) {
      throw new RuntimeException('An existing Commerce store address differs from the reviewed institutional address; no changes made.');
    }
  }
  print sprintf("Commerce store already provisioned (id %s).\n", $store->id());
  return;
}

$store = Store::create($store_data);
$store->save();
print sprintf("Provisioned institutional Commerce store (id %s, BRL).\n", $store->id());
