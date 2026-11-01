<?php

/**
 * @file
 * Document api for email confirmer module.
 */

use Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface;

/**
 * Acts on email confirmation responses.
 *
 * This hook allows a module to get notified when a confirmation response
 * is received.
 *
 * @param string $op
 *   Either "confirm" or "cancel".
 * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation
 *   The confirmation process.
 */
function hook_aculta_mail_confirmer($op, AcultaMailConfirmationInterface $confirmation) {
  // Log the event.
  \Drupal::logger('aculta_mail_confirmer')->info('Email confirmation @cid ' . ($op == 'confirm' ? 'confirmed' : 'cancelled'), ['@cid' => $confirmation->id() ?: '-not saved-']);
}
