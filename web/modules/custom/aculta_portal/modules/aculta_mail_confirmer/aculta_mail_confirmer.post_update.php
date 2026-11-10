<?php

/**
 * @file
 * Post-update hooks for ACULTA Mail Confirmer.
 */

/**
 * Moves user confirmation settings to the consolidated module configuration.
 */
function aculta_mail_confirmer_post_update_move_user_settings_to_module(): string {
  $config_factory = \Drupal::configFactory();
  $legacy = $config_factory->get('aculta_mail_confirmer_user.settings');
  if ($legacy->isNew()) {
    return 'No legacy user confirmation settings were present.';
  }

  $settings = $config_factory->getEditable('aculta_mail_confirmer.settings');
  foreach (['user_email_change', 'user_login'] as $key) {
    $value = $legacy->get($key);
    if ($value !== NULL) {
      $settings->set($key, $value);
    }
  }
  $settings->save();
  $config_factory->getEditable('aculta_mail_confirmer_user.settings')->delete();

  return 'Moved user confirmation settings to aculta_mail_confirmer.settings and removed the old config object.';
}
