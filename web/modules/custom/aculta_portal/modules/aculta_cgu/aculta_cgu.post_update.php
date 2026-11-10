<?php

/**
 * @file
 * Post-update hooks for ACULTA CGU.
 */

/**
 * Moves flood settings to the consolidated CGU configuration owner.
 */
function aculta_cgu_post_update_move_flood_settings_to_cgu(): string {
  $config_factory = \Drupal::configFactory();
  $legacy = $config_factory->get('aculta_contributions.settings');
  if ($legacy->isNew()) {
    return 'No legacy contribution settings were present.';
  }

  $settings = $config_factory->getEditable('aculta_cgu.settings');
  foreach (['flood_limit', 'flood_window'] as $key) {
    $value = $legacy->get($key);
    if ($value !== NULL) {
      $settings->set($key, $value);
    }
  }
  $settings->save();
  $config_factory->getEditable('aculta_contributions.settings')->delete();

  return 'Moved flood settings to aculta_cgu.settings and removed the old config object.';
}
