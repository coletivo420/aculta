<?php

/**
 * @file
 * Creates a safe, synthetic Checkout Pro preference for browser smoke tests.
 */

declare(strict_types=1);

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway\CheckoutPro;
use MercadoPago\Exceptions\MPApiException;

/**
 * Creates a synthetic test-mode preference and stores its init_point privately.
 *
 * This script creates no Drupal order or payment and does not charge a buyer.
 */
$gateway = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->load('mercado_pago');
$plugin = $gateway instanceof PaymentGatewayInterface ? $gateway->getPlugin() : NULL;
if (!$gateway || !$gateway->status() || !$plugin instanceof CheckoutPro || $plugin->getMode() !== 'test' || $plugin->getAccessToken() === '') {
  fwrite(STDERR, "An enabled test-mode Mercado Pago gateway with a test token is required.\n");
  exit(1);
}

$output = getenv('ACULTA_MP_INIT_POINT_FILE');
if ($output === FALSE || $output === '') {
  $suite_out = getenv('SUITE_OUT') ?: '/tmp/aculta-mercadopago-preference';
  $output = rtrim($suite_out, '/') . '/init-point.url';
}
$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
  fwrite(STDERR, "Could not create the preference output directory.\n");
  exit(1);
}

$suffix = bin2hex(random_bytes(6));
$request = [
  'items' => [
    [
      'id' => 'aculta-smoke-' . $suffix,
      'title' => 'ACULTA sandbox preference test',
      'quantity' => 1,
      'unit_price' => 1.0,
      'currency_id' => 'BRL',
    ],
  ],
  'external_reference' => 'aculta-mp-smoke-' . $suffix,
  'binary_mode' => FALSE,
  'auto_return' => 'approved',
  'back_urls' => [
    'success' => 'https://aculta.toca.net.br/',
    'pending' => 'https://aculta.toca.net.br/',
    'failure' => 'https://aculta.toca.net.br/',
  ],
];

try {
  $client = \Drupal::service('aculta_pagamentos_mercadopago.client_factory')->criar('preferencias', $gateway);
  $preference = $client->create($request);
}
catch (MPApiException $exception) {
  fwrite(STDERR, 'Preference creation failed (HTTP ' . $exception->getStatusCode() . ").\n");
  exit(1);
}
catch (Throwable) {
  fwrite(STDERR, "Preference creation failed during transport or client initialization.\n");
  exit(1);
}

$init_point = (string) ($preference->init_point ?? '');
$host = strtolower((string) parse_url($init_point, PHP_URL_HOST));
$valid = str_starts_with($init_point, 'https://')
  && ($host === 'mercadopago.com.br' || str_ends_with($host, '.mercadopago.com.br'));
if (!$valid || file_put_contents($output, $init_point, LOCK_EX) === FALSE) {
  fwrite(STDERR, "The provider response did not contain a safe init_point or could not be saved.\n");
  exit(1);
}

if (!chmod($output, 0600)) {
  @unlink($output);
  fwrite(STDERR, "The init_point file permissions could not be restricted.\n");
  exit(1);
}
fwrite(STDOUT, "Sandbox preference created; init_point saved outside the repository. No payment was submitted.\n");
