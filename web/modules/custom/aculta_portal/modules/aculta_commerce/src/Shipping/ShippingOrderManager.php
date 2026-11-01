<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Shipping;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Coordinates packing and shipment persistence for orders.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
class ShippingOrderManager implements ShippingOrderManagerInterface {

  /**
   * Constructs a new ShippingOrderManager object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected PackerManagerInterface $packerManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createProfile(OrderInterface $order, array $values = []): ProfileInterface {
    $values += [
      'type' => 'customer',
      'uid' => 0,
    ];
    $order_type_id = $order->bundle();
    $order_bundle_info = $this->entityTypeBundleInfo->getBundleInfo('commerce_order');
    if (!empty($order_bundle_info[$order_type_id]['shipping_profile_type'])) {
      $values['type'] = $order_bundle_info[$order_type_id]['shipping_profile_type'];
    }
    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    return $profile_storage->create($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(OrderInterface $order): ?ProfileInterface {
    $profiles = $order->collectProfiles();
    $profile = $profiles['shipping'] ?? NULL;
    return $profile instanceof ProfileInterface ? $profile : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasShipments(OrderInterface $order): bool {
    return $order->hasField('shipments') && !$order->get('shipments')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function isShippable(OrderInterface $order): bool {
    if (!$order->hasField('shipments')) {
      return FALSE;
    }
    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity && $purchased_entity->hasField('weight')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function pack(OrderInterface $order, ?ProfileInterface $profile = NULL): array {
    if (!$profile) {
      $profile = $this->getProfile($order) ?: $this->createProfile($order);
    }
    $shipments = $order->get('shipments')->referencedEntities();
    [$shipments, $removed_shipments] = $this->packerManager->packToShipments($order, $profile, $shipments);
    if (!empty($removed_shipments)) {
      $this->entityTypeManager->getStorage('aculta_shipment')->delete($removed_shipments);
    }
    return $shipments;
  }

}
