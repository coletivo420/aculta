<?php

namespace Drupal\aculta_mail_confirmer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;

/**
 * Email confirmation interface.
 */
interface AcultaMailConfirmationInterface extends ContentEntityInterface {

  /**
   * Email confirmation is active.
   */
  const ACTIVE = 1;

  /**
   * Email confirmation is cancelled.
   */
  const CANCELLED = 0;

  /**
   * Email confirmation is confirmed.
   */
  const CONFIRMED = 1;

  /**
   * Email confirmation is unconfirmed.
   */
  const UNCONFIRMED = 0;

  /**
   * Email confirmation is private.
   */
  const IS_PRIVATE = 1;

  /**
   * Email confirmation is not private.
   */
  const IS_PUBLIC = 0;

  /**
   * Returns if the confirmation is cancelled.
   *
   * @return bool
   *   TRUE if the confirmation is cancelled, FALSE if it is active.
   */
  public function isCancelled();

  /**
   * Returns if the confirmation is done.
   *
   * @return bool
   *   TRUE if the confirmation is done, FALSE if it is not confirmed.
   */
  public function isConfirmed();

  /**
   * Returns if the confirmation request was sent.
   *
   * @return bool
   *   TRUE if the confirmation request was sent, FALSE otherwise.
   */
  public function isRequestSent();

  /**
   * Returns the private status.
   *
   * @return bool
   *   TRUE if the confirmation is marked as private, FALSE otherwise.
   */
  public function isPrivate();

  /**
   * Mark the confirmation as private.
   *
   * A private confirmation can only be replied by their owner or by email
   * confirmation administrators. It has no effect on confirmations owned by
   * the anonymous user.
   *
   * @param bool $private
   *   The new private status. TRUE when no given argument.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The called email confirmation entity.
   */
  public function setPrivate($private = TRUE);

  /**
   * Returns the subscribers email address.
   *
   * @return string
   *   The subscribers email address.
   */
  public function getEmail();

  /**
   * Sets the subscribers email address.
   *
   * @param string $email
   *   The subscribers email address.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The called email confirmation entity.
   */
  public function setEmail($email);

  /**
   * Returns the realm to which this confirmation belongs.
   *
   * @return string
   *   The realm.
   */
  public function getRealm();

  /**
   * Sets the realm to which this confirmation belongs.
   *
   * @param string $realm
   *   The realm, tipically a module name.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The called email confirmation entity.
   */
  public function setRealm($realm);

  /**
   * Returns the IP address associated with this confirmation process.
   *
   * Commonly the IP address from where the confirmation was started.
   *
   * @return string
   *   The IP address, FALSE if empty.
   */
  public function getIp();

  /**
   * Sets the IP address associated with this confirmation process.
   *
   * @param string $ip
   *   The IP address.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The called email confirmation entity.
   *
   * @throws \InvalidArgumentException
   *   If the given value is not a valid IP address.
   */
  public function setIp($ip);

  /**
   * Returns a property value or an array with all defined properties.
   *
   * @param string $key
   *   Property key to get. Leave empty to get all defined properties.
   *
   * @return mixed
   *   The value of the given key, NULL if none set. Array of all available
   *   properties if no key specified.
   */
  public function getProperty($key);

  /**
   * Sets a property value.
   *
   * @param string $key
   *   Property key.
   * @param mixed $value
   *   Value to store. NULL will delete the property.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The called email confirmation entity.
   */
  public function setProperty($key, $value = NULL);

  /**
   * Get a keyed array with all the confirmation properties.
   *
   * @return array
   *   Array with all defined properties. Empty array if no one defined.
   */
  public function getProperties();

  /**
   * Returns the date of the last request sent.
   *
   * @return int
   *   Unix timestamp of the last request sent. FALSE if no request already
   *   sent.
   */
  public function getLastRequestDate();

  /**
   * Sets the date of the last request sent.
   *
   * @param int $timestamp
   *   Date timestamp to set. Unset current if NULL.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The called email confirmation entity.
   */
  public function setLastRequestDate($timestamp);

  /**
   * Gets the email confirmation creation timestamp.
   *
   * @return int
   *   Creation timestamp of the email confirmation.
   */
  public function getCreatedTime();

  /**
   * Sets the email confirmation creation timestamp.
   *
   * @param int $timestamp
   *   The email confirmation creation timestamp.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The called email confirmation entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Set a URL to go on confirmation response.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to go.
   * @param string $operation
   *   One of 'confirm', 'cancel' or 'error'. Empty to set for all operations.
   *
   * @return \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface
   *   The called email confirmation entity.
   */
  public function setResponseUrl(Url $url, $operation = NULL);

  /**
   * Get the response URL for a given operation.
   *
   * @param string $operation
   *   One of 'confirm', 'cancel' or 'error'.
   *
   * @return \Drupal\Core\Url
   *   The URL. NULL if not set.
   */
  public function getResponseUrl($operation);

}
