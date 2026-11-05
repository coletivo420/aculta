<?php

/**
 * @file
 * Post-update functions for ACULTA Portal.
 */

/**
 * Populates the neutral active purposes state from existing Domain entities.
 */
function aculta_portal_post_update_active_purposes_state(array &$sandbox): string {
  /** @var \Drupal\aculta_portal\Domain\ActivePurposesState $activePurposesState */
  $activePurposesState = \Drupal::service('aculta_portal.active_purposes_state');
  $active = $activePurposesState->rebuild();
  return sprintf('Populated active purposes state with %d active purposes: %s.', count($active), implode(', ', $active));
}
