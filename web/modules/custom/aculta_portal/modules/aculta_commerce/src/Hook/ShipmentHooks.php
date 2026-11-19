<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Hook;

use Drupal\aculta_commerce\Entity\Shipment;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Handles lifecycle operations for ACULTA shipments.
 */
final class ShipmentHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Removes anonymous shipping profiles after their shipment is deleted.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (!$entity instanceof Shipment) {
      return;
    }
    $profile = $entity->getShippingProfile();
    if ($profile && !$profile->getOwnerId()) {
      $this->entityTypeManager->getStorage('profile')->delete([$profile]);
    }
  }

}
