<?php

namespace Drupal\aculta_mail_confirmer;

/**
 * Email confirmation service interface.
 */
interface AcultaMailConfirmerManagerInterface {

  /**
   * Starts an email confirmation process.
   *
   * Existing confirmation for the given email address will be used if it is in
   * pending status, otherwise a new one will be created. Confirmation request
   * email is sent by this method.
   *
   * @param string $email
   *   Email address to be confirmed.
   * @param array $data
   *   An optional property map to store with the confirmation.
   * @param string $realm
   *   A realm to which this confirmation belongs. Tipically, a module name.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The email confirmation entity.
   */
  public function confirm($email, array $data = [], $realm = '');

  /**
   * Search for existent confirmation entities for a given email address.
   *
   * @param string $email
   *   The email address.
   * @param string $status
   *   Filter by confirmation status.
   * @param int $limit
   *   Max results count. Defaults to no limit.
   * @param string $realm
   *   A realm to filter by.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface[]
   *   Array of confirmations found, most recent first. Empty array if none
   *   found.
   */
  public function getConfirmations($email, $status = FALSE, $limit = 0, $realm = '');

  /**
   * Search for the recent confirmation for a given email address.
   *
   * @param string $email
   *   The email address.
   * @param string $status
   *   Filter by confirmation status.
   * @param string $realm
   *   A realm to filter by.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The email confirmation entity. NULL if not found.
   */
  public function getConfirmation($email, $status = FALSE, $realm = '');

  /**
   * Creates a new email confirmation entity.
   *
   * @param string $email
   *   The email address to confirm.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The new email confirmation entity.
   */
  public function createConfirmation($email);

  /**
   * Returns the most representative current status of the confirmation.
   *
   * - pending: confirmation is pending response.
   * - confirmed: the confirmation was positively responded.
   * - cancelled: the confirmation process was cancelled.
   * - expired: the confirmation age is over hash_expiration, regardless of any
   *   other status.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation
   *   The confirmation.
   *
   * @return string
   *   The current status.
   */
  public function getStatus(AcultaMailConfirmationInterface $confirmation);

  /**
   * Returns if the confirmation is expired.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation
   *   The confirmation.
   *
   * @return bool
   *   TRUE if the confirmation is expired, FALSE if it is active.
   */
  public function isExpired(AcultaMailConfirmationInterface $confirmation);

  /**
   * Returns if the confirmation is in a proper status to get a response.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation
   *   The confirmation.
   *
   * @return bool
   *   TRUE if the confirmation can be responded, FALSE otherwise.
   */
  public function isPending(AcultaMailConfirmationInterface $confirmation);

  /**
   * Calculates the hash of the confirmation.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation
   *   The confirmation.
   *
   * @return string
   *   The hash.
   */
  public function getHash(AcultaMailConfirmationInterface $confirmation);

  /**
   * Sends the email confirmation request.
   *
   * More than one request can be sent, but a request inside the resend delay
   * is queued instead of being sent again.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation
   *   The confirmation.
   *
   * @return bool
   *   TRUE if the request was sent or queued, FALSE on sending error.
   *
   * @throws \Drupal\aculta_mail_confirmer\InvalidConfirmationStateException
   *   If the confirmation is cancelled, expired or already confirmed.
   */
  public function sendRequest(AcultaMailConfirmationInterface $confirmation);

  /**
   * Processes the response of the confirmation.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation
   *   The confirmation.
   * @param string $hash
   *   The received hash.
   *
   * @return bool
   *   TRUE if the confirmation was processed, FALSE on hash mismatch.
   *
   * @throws \Drupal\aculta_mail_confirmer\InvalidConfirmationStateException
   *   If the confirmation is cancelled, expired or already confirmed.
   */
  public function confirmWithHash(AcultaMailConfirmationInterface $confirmation, $hash);

  /**
   * Cancels the email confirmation.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation
   *   The confirmation.
   *
   * @throws \Drupal\aculta_mail_confirmer\InvalidConfirmationStateException
   *   If the confirmation is expired, confirmed or already cancelled.
   */
  public function cancelConfirmation(AcultaMailConfirmationInterface $confirmation);

}
