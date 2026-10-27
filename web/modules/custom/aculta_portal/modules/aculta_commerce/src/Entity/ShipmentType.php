<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Entity;

use Drupal\commerce\Entity\CommerceBundleEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the ACULTA shipment type entity.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Admin UI is
 * intentionally not exposed here; Green Stash owns the operational interface.
 */
#[ConfigEntityType(
  id: 'aculta_shipment_type',
  label: new TranslatableMarkup('Shipment type'),
  label_collection: new TranslatableMarkup('Shipment types'),
  label_singular: new TranslatableMarkup('shipment type'),
  label_plural: new TranslatableMarkup('shipment types'),
  config_prefix: 'aculta_shipment_type',
  admin_permission: 'administer aculta shipments',
  bundle_of: 'aculta_shipment',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  config_export: [
    'id',
    'label',
    'uuid',
    'profileType',
    'traits',
    'sendConfirmation',
    'confirmationBcc',
  ],
)]
class ShipmentType extends CommerceBundleEntityBase implements ShipmentTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function getProfileTypeId(): string {
    return (string) ($this->profileType ?? 'customer');
  }

  /**
   * {@inheritdoc}
   */
  public function setProfileTypeId(string $profile_type_id): static {
    $this->profileType = $profile_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldSendConfirmation(): ?bool {
    return $this->sendConfirmation ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSendConfirmation(bool $send_confirmation): static {
    $this->sendConfirmation = $send_confirmation;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmationBcc(): ?string {
    return $this->confirmationBcc ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfirmationBcc(string $confirmation_bcc): static {
    $this->confirmationBcc = $confirmation_bcc;
    return $this;
  }

}
