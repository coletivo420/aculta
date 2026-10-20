<?php

declare(strict_types=1);

/**
 * Loads the approved ACULTA secrets contract during Drupal bootstrap.
 *
 * Environment-specific paths belong in an ignored local settings file.
 */

if (!defined('DRUPAL_ROOT')) {
  throw new RuntimeException('ACULTA secrets loader may only run during Drupal bootstrap.');
}

if (!isset($settings) || !is_array($settings) || !array_key_exists('aculta_secrets_file', $settings)) {
  return;
}

$configured_path = $settings['aculta_secrets_file'];
if (!is_string($configured_path) || $configured_path === '' || $configured_path[0] !== DIRECTORY_SEPARATOR) {
  throw new RuntimeException('ACULTA secrets file must be configured with an absolute path.');
}

$secrets_path = realpath($configured_path);
if ($secrets_path === FALSE || !is_file($secrets_path) || !is_readable($secrets_path)) {
  throw new RuntimeException('Configured ACULTA secrets file is unavailable.');
}

$document_root = realpath(DRUPAL_ROOT);
if ($document_root === FALSE) {
  throw new RuntimeException('Drupal document root is unavailable to the ACULTA secrets loader.');
}
$document_prefix = rtrim($document_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if ($secrets_path === $document_root || str_starts_with($secrets_path, $document_prefix)) {
  throw new RuntimeException('ACULTA secrets file must be outside the Drupal document root.');
}

$permissions = fileperms($secrets_path);
if ($permissions === FALSE || ($permissions & 0007) !== 0 || ($permissions & 0020) !== 0) {
  throw new RuntimeException('ACULTA secrets file permissions are too broad.');
}

$allowed_variables = [
  'GOOGLE_OAUTH_CLIENT_ID',
  'GOOGLE_OAUTH_CLIENT_SECRET',
  'MERCADOPAGO_PRODUCTION_PUBLIC_KEY',
  'MERCADOPAGO_PRODUCTION_ACCESS_TOKEN',
  'MERCADOPAGO_WEBHOOK_SECRET',
  'MERCADOPAGO_TEST_PUBLIC_KEY',
  'MERCADOPAGO_TEST_ACCESS_TOKEN',
  'MERCADOPAGO_TEST_BUYER_PASSWORD',
  'SMTP2GO_USERNAME',
  'SMTP2GO_PASSWORD',
  'BREVO_API_KEY',
  'IMAP_PASSWORD',
  'SMTP_PASSWORD',
  'TURNSTILE_KEYS_JSON',
  'TURNSTILE_TEST_KEYS_JSON',
  'ACULTA_DOCUMENT_ENCRYPTION_KEY',
  'ACULTA_ID_CPF_FINGERPRINT_KEY',
];

$values = parse_ini_file($secrets_path, FALSE, INI_SCANNER_RAW);
if (!is_array($values)) {
  throw new RuntimeException('Configured ACULTA secrets file has invalid NAME=value syntax.');
}

foreach ($values as $name => $value) {
  if (!is_string($name) || !in_array($name, $allowed_variables, TRUE)) {
    throw new RuntimeException('Unknown ACULTA secret variable: ' . (string) $name);
  }
  if (!is_string($value)) {
    throw new RuntimeException('ACULTA secret values must use NAME=value syntax.');
  }

  $native_value = getenv($name);
  if ($native_value !== FALSE && $native_value !== '') {
    $_ENV[$name] = $native_value;
    $_SERVER[$name] = $native_value;
    continue;
  }

  putenv($name . '=' . $value);
  $_ENV[$name] = $value;
  $_SERVER[$name] = $value;
}
