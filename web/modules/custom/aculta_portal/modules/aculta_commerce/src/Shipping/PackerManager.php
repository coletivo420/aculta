<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Manages shipment packers and converts proposed shipments to entities.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
class PackerManager implements PackerManagerInterface {

  /**
   * The registered packers.
   *
   * @var \Drupal\aculta_commerce\Shipping\PackerInterface[]
   */
  protected array $packers = [];

  /**
   * Constructs a new PackerManager object.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function addPacker(PackerInterface $packer): void {
    $this->packers[] = $packer;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackers(): array {
    return $this->packers;
  }

  /**
   * {@inheritdoc}
   */
  public function pack(OrderInterface $order, ProfileInterface $shipping_profile): array {
    foreach ($this->packers as $packer) {
      if (!$packer->applies($order, $shipping_profile)) {
        continue;
      }
      $proposed_shipments = $packer->pack($order, $shipping_profile);
      if (!empty($proposed_shipments)) {
        return $proposed_shipments;
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function packToShipments(OrderInterface $order, ProfileInterface $shipping_profile, array $shipments): array {
    $shipment_storage = $this->entityTypeManager->getStorage('aculta_shipment');
    $populated_shipments = [];
    foreach ($this->pack($order, $shipping_profile) as $index => $proposed_shipment) {
      $shipment = NULL;
      foreach ($shipments as $existing_index => $existing_shipment) {
        if ($existing_shipment->bundle() === $proposed_shipment->getType()) {
          $shipment = $existing_shipment;
          unset($shipments[$existing_index]);
          break;
        }
      }
      if (!$shipment) {
        $shipment = $shipment_storage->create([
          'type' => $proposed_shipment->getType(),
        ]);
      }
      $shipment->populateFromProposedShipment($proposed_shipment);
      $shipment->setData('owned_by_packer', TRUE);
      $populated_shipments[$index] = $shipment;
    }
    $removed_shipments = array_filter($shipments, static function ($shipment): bool {
      return !$shipment->isNew();
    });

    return [$populated_shipments, array_values($removed_shipments)];
  }

}
