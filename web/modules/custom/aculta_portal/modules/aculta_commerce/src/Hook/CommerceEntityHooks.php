<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Hook;

use Drupal\aculta_commerce\Entity\Shipment;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hooks de traits, de tipos de pedido e de campos do Commerce no ACULTA Commerce.
 *
 * O motor de frete é porte do commerce_shipping 3.0.5 (GPL-2.0-or-later); estes hooks não dependem do módulo contrib.
 */
class CommerceEntityHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_commerce_entity_trait_info_alter().
   *
   * @param array<string, array<string, mixed>> $definitions
   */
  #[Hook('commerce_entity_trait_info_alter')]
  public function commerceEntityTraitInfoAlter(array &$definitions): void {
    // Expõe os traits de item comprável do ACULTA para toda entidade comprável.
    $entity_type_ids = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type->entityClassImplements(PurchasableEntityInterface::class)) {
        $entity_type_ids[] = $entity_type->id();
      }
    }
    $definitions['aculta_purchasable_entity_shippable']['entity_types'] = $entity_type_ids;
    $definitions['aculta_purchasable_entity_dimensions']['entity_types'] = $entity_type_ids;
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   *
   * @param array<string, array<string, array<string, mixed>>> $bundles
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    if (empty($bundles['commerce_order'])) {
      return;
    }
    $order_type_ids = array_keys($bundles['commerce_order']);
    $order_types = $this->entityTypeManager
      ->getStorage('commerce_order_type')
      ->loadMultiple($order_type_ids);
    $shipment_type_storage = $this->entityTypeManager->getStorage('aculta_shipment_type');
    foreach ($bundles['commerce_order'] as $bundle => $info) {
      if (!isset($order_types[$bundle])) {
        continue;
      }
      $shipment_type_id = $order_types[$bundle]->getThirdPartySetting('aculta_commerce', 'shipment_type');
      if (!$shipment_type_id) {
        continue;
      }
      $shipment_type = $shipment_type_storage->load($shipment_type_id);
      if (!$shipment_type) {
        continue;
      }
      $shipping_profile_type_id = $shipment_type->getProfileTypeId();
      if ($shipping_profile_type_id !== 'customer') {
        $bundles['commerce_order'][$bundle]['shipping_profile_type'] = $shipping_profile_type_id;
      }
    }
  }

  /**
   * Implements hook_entity_bundle_field_info().
   *
   * @return array<string, \Drupal\Core\Field\BaseFieldDefinition>
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, string $bundle): array {
    if ($entity_type->id() !== 'commerce_order') {
      return [];
    }
    $order_type = $this->entityTypeManager
      ->getStorage('commerce_order_type')
      ->load($bundle);
    if (!$order_type) {
      return [];
    }
    if (empty($order_type->getThirdPartySetting('aculta_commerce', 'shipment_type'))) {
      return [];
    }
    $field_definition = Shipment::buildShipmentsFieldDefinition($bundle);
    return [$field_definition->getName() => $field_definition];
  }

}
