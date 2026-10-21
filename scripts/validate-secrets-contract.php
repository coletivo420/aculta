<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$parse = static function (string $path) use ($assert): array {
  $data = Yaml::parseFile($path);
  $assert(is_array($data), 'YAML configuration is readable: ' . basename($path));
  return $data;
};

$expected = [
  'google_oauth_client_id' => ['GOOGLE_OAUTH_CLIENT_ID', FALSE],
  'google_oauth_client_secret' => ['GOOGLE_OAUTH_CLIENT_SECRET', FALSE],
  'mercadopago_production_public_key' => ['MERCADOPAGO_PRODUCTION_PUBLIC_KEY', FALSE],
  'mercadopago_production_access_token' => ['MERCADOPAGO_PRODUCTION_ACCESS_TOKEN', FALSE],
  'mercadopago_test_public_key' => ['MERCADOPAGO_TEST_PUBLIC_KEY', FALSE],
  'mercadopago_test_access_token' => ['MERCADOPAGO_TEST_ACCESS_TOKEN', FALSE],
  'mercadopago_test_buyer_password' => ['MERCADOPAGO_TEST_BUYER_PASSWORD', FALSE],
  'mercadopago_webhook_secret' => ['MERCADOPAGO_WEBHOOK_SECRET', FALSE],
  'turnstile' => ['TURNSTILE_KEYS_JSON', TRUE],
  'turnstile_test' => ['TURNSTILE_TEST_KEYS_JSON', TRUE],
  // Chaves de aplicação em texto puro (docs/operations/SECRETS.md e config/secrets-contract.json).
  'aculta_document_encryption_key' => ['ACULTA_DOCUMENT_ENCRYPTION_KEY', FALSE],
  'aculta_id_cpf_fingerprint' => ['ACULTA_ID_CPF_FINGERPRINT_KEY', FALSE],
];

// Credenciais de envio ficam no arquivo de segredos do servidor, sem Key (regra de segredos do AGENTS.md).
$file_secrets = [
  'ga4_property_id' => 'GA4_PROPERTY_ID',
  'ga4_service_account_json' => 'GA4_SERVICE_ACCOUNT_JSON',
  'gsc_site_url' => 'GSC_SITE_URL',
  'smtp2go_username' => 'SMTP2GO_USERNAME',
  'smtp2go_password' => 'SMTP2GO_PASSWORD',
  'brevo_api_key' => 'BREVO_API_KEY',
  'brevo_webhook_token' => 'BREVO_WEBHOOK_TOKEN',
  'mailer_payload_key' => 'MAILER_PAYLOAD_KEY',
  'imap_password' => 'IMAP_PASSWORD',
  'smtp_password' => 'SMTP_PASSWORD',
];

$key_files = glob($root . '/config/sync/key.key.*.yml') ?: [];
$actual_ids = [];
foreach ($key_files as $path) {
  $config = $parse($path);
  $id = (string) ($config['id'] ?? '');
  $actual_ids[] = $id;
  $assert(($config['key_provider'] ?? NULL) === 'env', 'Key provider is Environment: ' . $id);
  $assert(isset($expected[$id]), 'Key ID is present in the approved contract: ' . $id);
  [$environment, $base64] = $expected[$id];
  $provider_settings = $config['key_provider_settings'] ?? [];
  $assert(($provider_settings['env_variable'] ?? NULL) === $environment, 'Key environment name matches contract: ' . $id);
  $assert(($provider_settings['base64_encoded'] ?? FALSE) === $base64, 'Key encoding matches contract: ' . $id);
  $assert(!array_key_exists('key_value', $config), 'Key sync contains no inline value: ' . $id);
}
sort($actual_ids);
$expected_ids = array_keys($expected);
sort($expected_ids);
$assert($actual_ids === $expected_ids, 'All and only the approved Key IDs are versioned.');

$contract = json_decode((string) file_get_contents($root . '/config/secrets-contract.json'), TRUE);
$assert(is_array($contract) && is_array($contract['variables'] ?? NULL), 'Secrets contract JSON is readable.');
$contract_names = [];
foreach ($contract['variables'] as $variable) {
  $name = (string) ($variable['name'] ?? '');
  $contract_names[] = $name;
  $environments = (array) ($variable['environments'] ?? []);
  $assert($environments !== [] && array_diff($environments, ['production', 'test']) === [], 'Contract environments are production or test: ' . $name);
  $assert(array_diff((array) ($variable['required_in'] ?? []), $environments) === [], 'Contract required_in is a subset of environments: ' . $name);
  $key_id = (string) ($variable['key_id'] ?? '');
  $is_key = isset($expected[$key_id]) && $expected[$key_id][0] === $name;
  $is_file_secret = isset($file_secrets[$key_id]) && $file_secrets[$key_id] === $name;
  $assert($is_key || $is_file_secret, 'Contract variable matches the approved Key or file secret and name: ' . $name);
}
sort($contract_names);
$expected_names = array_merge(array_map(static fn(array $pair): string => $pair[0], array_values($expected)), array_values($file_secrets));
sort($expected_names);
$assert($contract_names === $expected_names, 'Contract variables are the approved Keys plus the file secrets, and nothing else.');

$google_sync = $parse($root . '/config/sync/social_auth_google.settings.yml');
$assert(trim((string) ($google_sync['client_id'] ?? '')) === '', 'Google OAuth client ID is empty in sync.');
$assert(trim((string) ($google_sync['client_secret'] ?? '')) === '', 'Google OAuth client secret is empty in sync.');

$overrides = [
  'google_client_id' => ['client_id', 'google_oauth_client_id'],
  'google_client_secret' => ['client_secret', 'google_oauth_client_secret'],
];
foreach ($overrides as $id => [$item, $key_id]) {
  $override = $parse($root . '/config/sync/key.config_override.' . $id . '.yml');
  $assert(($override['status'] ?? FALSE) === TRUE, 'Google Key Config Override is enabled: ' . $id);
  $assert(($override['config_name'] ?? NULL) === 'social_auth_google.settings', 'Google override targets Social Auth Google: ' . $id);
  $assert(($override['config_item'] ?? NULL) === $item, 'Google override targets the expected config item: ' . $id);
  $assert(($override['key_id'] ?? NULL) === $key_id, 'Google override uses the expected Key ID: ' . $id);
}

$loader_path = $root . '/web/sites/default/aculta.secrets.php';
$assert(is_file($loader_path), 'Portable secrets loader exists.');
$loader = file_get_contents($loader_path);
$assert(is_string($loader), 'Portable secrets loader is readable.');
foreach (array_keys($expected) as $id) {
  $assert(str_contains($loader, $expected[$id][0]), 'Loader allowlist includes contract variable: ' . $expected[$id][0]);
}
foreach ($file_secrets as $name) {
  $assert(str_contains($loader, "'" . $name . "'"), 'Loader allowlist includes file secret: ' . $name);
}
$assert(str_contains($loader, 'Unknown ACULTA secret variable'), 'Loader rejects unknown names.');
$assert(str_contains($loader, 'getenv($name)') && str_contains($loader, 'putenv($name .'), 'Loader preserves native environment precedence.');
$assert(str_contains($loader, "!defined('DRUPAL_ROOT')"), 'Loader requires Drupal bootstrap context.');
$assert(str_contains($loader, 'realpath($configured_path)') && str_contains($loader, 'is_file($secrets_path)'), 'Loader resolves and validates the configured file.');
$assert(str_contains($loader, 'str_starts_with($secrets_path, $document_prefix)'), 'Loader rejects files inside the document root.');
$assert(str_contains($loader, '($permissions & 0007)') && str_contains($loader, '($permissions & 0020)'), 'Loader rejects other access and group-writable secret files.');
$assert(str_contains($loader, 'parse_ini_file($secrets_path, FALSE, INI_SCANNER_RAW)'), 'Loader uses a non-evaluating INI parser.');
$assert(!preg_match('/\b(?:shell_exec|exec|eval)\s*\(/i', $loader), 'Loader does not execute shell commands or evaluate input.');
$assert(!str_contains($loader, '/etc/aculta') && !stripos($loader, 'Hostinger'), 'Loader has no environment-specific path or provider dependency.');

$examples = [
  $root . '/web/sites/default/settings.secrets.php.example',
  $root . '/web/sites/default/settings.homelab.php.example',
  $root . '/web/sites/default/settings.hostinger.php.example',
];
foreach ($examples as $path) {
  $assert(is_file($path), 'Settings example exists: ' . basename($path));
  $contents = file_get_contents($path);
  $assert(is_string($contents) && str_contains($contents, 'aculta.secrets.php'), 'Settings example references the portable loader: ' . basename($path));
  $assert(!preg_match('/(?:client_secret|secret_key)\s*[:=]\s*[\'\"]?[^\s\'\"]{8,}/i', $contents), 'Settings example contains no credential value: ' . basename($path));
}
$hostinger_example = file_get_contents($root . '/web/sites/default/settings.hostinger.php.example');
$assert(is_string($hostinger_example) && str_contains($hostinger_example, '/home/ACCOUNT/') && str_contains($hostinger_example, 'public_html'), 'Hostinger example uses only a generic outside-document-root placeholder.');

$gitignore = file_get_contents($root . '/.gitignore');
foreach (['secrets.env', '*.secrets.env', '/web/sites/*/settings.hostinger.php', '/web/sites/*/settings.secrets.php'] as $pattern) {
  $assert(is_string($gitignore) && preg_match('/^' . preg_quote($pattern, '/') . '$/m', $gitignore) === 1, 'Git ignores local secret artifact: ' . $pattern);
}
foreach (['settings.secrets.php.example', 'settings.hostinger.php.example', 'aculta.secrets.php'] as $versioned) {
  $assert(is_file($root . '/web/sites/default/' . $versioned), 'Safe contract artifact remains versionable: ' . $versioned);
}

print "ACULTA Secrets Contract static checks passed.\n";
