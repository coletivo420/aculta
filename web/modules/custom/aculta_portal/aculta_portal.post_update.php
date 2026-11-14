<?php

/**
 * @file
 * Post-update functions for ACULTA Portal.
 */

/**
 * Populates the neutral active site keys state from existing Domain entities.
 */
function aculta_portal_post_update_active_site_keys_state(array &$sandbox): string {
  /** @var \Drupal\aculta_portal\Domain\ActiveSiteKeysState $activeSiteKeysState */
  $activeSiteKeysState = \Drupal::service('aculta_portal.active_site_keys_state');
  $active = $activeSiteKeysState->rebuild();
  return sprintf('Populated active site keys state with %d active site keys: %s.', count($active), implode(', ', $active));
}
