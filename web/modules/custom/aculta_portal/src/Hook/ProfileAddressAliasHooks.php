<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Commerce\MercadoPago\BillingAddressAliasItemList;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Expõe o endereço do perfil também como `address`, para o contrib do Mercado Pago (campo computado, sem armazenamento).
 */
final class ProfileAddressAliasHooks {

  /**
   * Adds the computed `address` alias to every profile bundle. A bundle without `field_address` gets an empty value.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param array $base_field_definitions
   *   The base field definitions of the entity type.
   *
   * @return array<string, \Drupal\Core\Field\FieldDefinitionInterface>
   *   The extra field definitions for the bundle.
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, string $bundle, array $base_field_definitions): array {
    if ($entity_type->id() !== 'profile') {
      return [];
    }
    return ['address' => BaseFieldDefinition::create('address')
      ->setName('address')
      ->setTargetEntityTypeId('profile')
      ->setTargetBundle($bundle)
      ->setLabel('Endereço (alias do campo field_address)')
      ->setDescription('Campo computado para integrações externas. Não é armazenado.')
      ->setComputed(TRUE)
      ->setReadOnly(TRUE)
      ->setClass(BillingAddressAliasItemList::class)];
  }

}
