<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityInterface;

/**
 * Defines the interface for ACULTA shipment types.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
interface ShipmentTypeInterface extends CommerceBundleEntityInterface {

  /**
   * Gets the profile type ID used for shipping profiles.
   */
  public function getProfileTypeId(): string;

  /**
   * Sets the profile type ID used for shipping profiles.
   */
  public function setProfileTypeId(string $profile_type_id): static;

  /**
   * Gets whether to email the customer when a shipment is shipped.
   */
  public function shouldSendConfirmation(): ?bool;

  /**
   * Sets whether to email the customer a shipment confirmation.
   */
  public function setSendConfirmation(bool $send_confirmation): static;

  /**
   * Gets the confirmation BCC email.
   */
  public function getConfirmationBcc(): ?string;

  /**
   * Sets the confirmation BCC email.
   */
  public function setConfirmationBcc(string $confirmation_bcc): static;

}
