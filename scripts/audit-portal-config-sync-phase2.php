<?php

/** Read-only, value-free inventory of active-vs-canonical configuration. */
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1', 'default'], TRUE)) {
  throw new RuntimeException('Configuration inventory is local-only.');
}

$active = \Drupal::service('config.storage');
$sync = \Drupal::service('config.storage.sync');
$sort = static function (mixed &$value) use (&$sort): void {
  if (!is_array($value)) {
    return;
  }
  ksort($value);
  foreach ($value as &$child) {
    $sort($child);
  }
};
$category = static function (string $name): string {
  if (str_starts_with($name, 'views.view.')) {
    return 'VIEWS';
  }
  if (str_starts_with($name, 'commerce') || str_contains($name, 'commerce_') || str_contains($name, '.commerce_') || str_contains($name, 'profile.customer') || $name === 'profile.type.customer' || $name === 'field.storage.profile.address' || str_starts_with($name, 'core.entity_form_mode.profile.billing') || str_starts_with($name, 'core.entity_view_mode.profile.admin')) {
    return 'COMMERCE';
  }
  if (preg_match('/^(metatag|schema_|schema\.|simple_sitemap|pathauto|redirect\.)/', $name)) {
    return 'SEO';
  }
  if (str_starts_with($name, 'webform.')) {
    return 'WEBFORMS';
  }
  if (str_starts_with($name, 'node.type.') || str_starts_with($name, 'taxonomy.vocabulary.')) {
    return 'CONTENT TYPES';
  }
  if (str_starts_with($name, 'field.') || str_starts_with($name, 'core.entity_form_display.node.') || str_starts_with($name, 'core.entity_view_display.node.')) {
    return 'FIELDS';
  }
  if ($name === 'core.entity_view_display.user.user.compact' || $name === 'user.role.anonymous') {
    return 'CORE';
  }
  if (str_starts_with($name, 'aculta.') || str_starts_with($name, 'block.block.') || str_starts_with($name, 'system.theme') || str_starts_with($name, 'bootstrap5.')) {
    return 'THEME';
  }
  if (str_starts_with($name, 'aculta_portal.') || str_starts_with($name, 'profile.type.participante') || str_starts_with($name, 'core.entity_form_display.profile.participante') || str_starts_with($name, 'core.entity_view_display.profile.participante') || str_starts_with($name, 'core.entity_form_display.user.user') || str_starts_with($name, 'core.entity_view_display.user.user') || str_starts_with($name, 'field.storage.user.user_picture') || str_starts_with($name, 'crop.') || str_starts_with($name, 'image_widget_crop.') || str_starts_with($name, 'image.style.crop_thumbnail') || str_starts_with($name, 'key.') || str_starts_with($name, 'captcha.') || str_starts_with($name, 'turnstile.') || str_starts_with($name, 'smtp.') || str_starts_with($name, 'social_auth.') || str_starts_with($name, 'social_auth_google.') || str_starts_with($name, 'user_registrationpassword.') || str_starts_with($name, 'user.role.authenticated')) {
    return 'PORTAL';
  }
  if (str_starts_with($name, 'core.') || str_starts_with($name, 'system.') || str_starts_with($name, 'user.') || str_starts_with($name, 'language.')) {
    if (str_starts_with($name, 'system.action.profile_')) {
      return 'OUTROS CONTRIB';
    }
    return 'CORE';
  }
  return 'OUTROS CONTRIB';
};

$inventory = [
  'generated_at' => gmdate(DATE_ATOM),
  'sync_directory' => realpath(\Drupal\Core\Site\Settings::get('config_sync_directory')),
  'canonical_repository_sync' => realpath(dirname(DRUPAL_ROOT) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'sync'),
  'collections' => [],
];
$collections = array_unique(array_merge([''], $active->getAllCollectionNames(), $sync->getAllCollectionNames()));
sort($collections);
foreach ($collections as $collection_name) {
  $active_collection = $collection_name === '' ? $active : $active->createCollection($collection_name);
  $sync_collection = $collection_name === '' ? $sync : $sync->createCollection($collection_name);
  $names = array_unique(array_merge($active_collection->listAll(), $sync_collection->listAll()));
  sort($names);
  foreach ($names as $name) {
    $active_data = $active_collection->read($name);
    $sync_data = $sync_collection->read($name);
    $sort($active_data);
    $sort($sync_data);
    $state = $active_data === FALSE ? 'Only in sync' : ($sync_data === FALSE ? 'Only in DB' : ($active_data === $sync_data ? NULL : 'Different'));
    if ($state === NULL) {
      continue;
    }
    $group = $category($name);
    $collection_key = $collection_name === '' ? 'default' : $collection_name;
    $inventory['collections'][$collection_key][$group][$state][] = $name;
  }
}

foreach ($inventory['collections'] as &$groups) {
  foreach ($groups as &$states) {
    foreach ($states as &$names) {
      sort($names);
    }
  }
}
unset($groups, $states, $names);

$manifest_path = dirname(__DIR__) . '/scripts/institution/PORTAL-ACCOUNT-CONFIG-MANIFEST.json';
$manifest = json_decode(file_get_contents($manifest_path), TRUE, 512, JSON_THROW_ON_ERROR);
$active_matches = 0;
$missing_active = [];
$missing_sync = [];
$different = [];
foreach ($manifest['configs'] as $name) {
  $active_data = $active->read($name);
  $sync_data = $sync->read($name);
  if ($active_data === FALSE) {
    $missing_active[] = $name;
  }
  elseif ($sync_data === FALSE) {
    $missing_sync[] = $name;
  }
  else {
    $sort($active_data);
    $sort($sync_data);
    if ($active_data === $sync_data) {
      $active_matches++;
    }
    else {
      $different[] = $name;
    }
  }
}
$inventory['selected_manifest'] = [
  'count' => count($manifest['configs']),
  'matching' => $active_matches,
  'missing_active' => $missing_active,
  'missing_sync' => $missing_sync,
  'different' => $different,
];

$path = dirname(__DIR__) . '/scripts/institution/PORTAL-CONFIG-DIFF-INVENTORY.json';
file_put_contents($path, json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);

$summary = [];
foreach ($inventory['collections'] as $collection => $groups) {
  foreach ($groups as $group => $states) {
    foreach ($states as $state => $entries) {
      $summary[] = [
        'collection' => $collection,
        'group' => $group,
        'state' => $state,
        'count' => count($entries),
        'names' => $entries,
      ];
    }
  }
}
echo json_encode([
  'sync_directory_is_canonical' => $inventory['sync_directory'] === $inventory['canonical_repository_sync'],
  'selected_manifest' => $inventory['selected_manifest'],
  'differences' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
