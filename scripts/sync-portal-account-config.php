<?php

/**
 * Explicitly reconcile reviewed account/portal configuration into config/sync.
 *
 * This intentionally does not perform a broad config export. It is local-only
 * and refuses to write a selected configuration object containing a value in
 * a secret-bearing property.
 */
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1', 'default'], TRUE)) {
  throw new RuntimeException('This reviewed configuration export is local-only.');
}

$names = [
  'aculta_portal.support',
  'captcha.settings',
  'core.extension',
  'core.entity_form_display.profile.participante.default',
  'core.entity_view_display.profile.participante.default',
  'core.entity_form_display.user.user.default',
  'core.entity_view_display.user.user.default',
  'field.storage.user.user_picture',
  'crop.type.aculta_avatar_1x1',
  'image.style.aculta_avatar',
  'key.key.google_oauth_client_id',
  'key.key.google_oauth_client_secret',
  'key.key.turnstile',
  'key.config_override.google_client_id',
  'key.config_override.google_client_secret',
  'profile.type.participante',
  'social_auth.settings',
  'mailer_transport.mailer_transport.smtp2go',
  'mailer_transport.settings',
  'turnstile.settings',
  'user.mail',
  'user.role.administrator',
  'user.role.authenticated',
  'user.settings',
  'user_registrationpassword.mail',
  'user_registrationpassword.mail_original',
  'user_registrationpassword.settings',
];

$active = \Drupal::service('config.storage');
$sync = new \Drupal\Core\Config\FileStorage(dirname(DRUPAL_ROOT) . '/config/sync');
$names = array_merge($names, $active->listAll('captcha.captcha_point.'));
$profile_field_configs = $active->listAll('field.field.profile.participante.');
$names = array_merge($names, $profile_field_configs);
foreach ($profile_field_configs as $field_config_name) {
  $field_data = $active->read($field_config_name);
  if (!empty($field_data['field_name'])) {
    $names[] = 'field.storage.profile.' . $field_data['field_name'];
  }
}
$names = array_values(array_unique($names));
sort($names);

$sensitive_values = [];
$inspect = static function (array $data, string $path = '') use (&$inspect, &$sensitive_values): void {
  foreach ($data as $key => $value) {
    $current = $path === '' ? (string) $key : $path . '.' . $key;
    if (is_array($value)) {
      $inspect($value, $current);
      continue;
    }
    if (preg_match('/(?:access.?token|client.?secret|webhook.?secret|smtp.?password|private.?key)/i', (string) $key) && $value !== NULL && $value !== '') {
      $sensitive_values[] = $current;
    }
  }
};

$exported = [];
foreach ($names as $name) {
  if (!$active->exists($name)) {
    continue;
  }
  $data = $active->read($name);
  $inspect($data);
  if ($sensitive_values) {
    throw new RuntimeException('Sensitive configuration value detected; export stopped.');
  }
  $sync->write($name, $data);
  $exported[] = $name;
}

// Remove only the obsolete configuration file owned by the retired module.
$sync->delete('aculta_apoio.settings');
$manifest = [
  'purpose' => 'Explicit local reconciliation of reviewed Portal/account configuration after consolidating the support feature into aculta_portal.',
  'configs' => $exported,
  'sensitive_config_values_exported' => FALSE,
  'gateway_or_store_configuration_exported' => FALSE,
];
$manifest_path = dirname(__DIR__) . '/scripts/institution/PORTAL-ACCOUNT-CONFIG-MANIFEST.json';
file_put_contents($manifest_path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);

echo count($exported) . " reviewed configuration objects reconciled; sensitive config values and Commerce gateway/store config were not exported.\n";
