<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Plugin\Commerce\EntityTrait;

use Drupal\commerce\Attribute\CommerceEntityTrait;
use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\physical\MeasurementType;

/**
 * Provides the "aculta_purchasable_entity_shippable" trait.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). The ACULTA
 * trait ID is used so the contributed trait can coexist during the port. It
 * adds the same `weight` field, preserving existing variation data.
 */
#[CommerceEntityTrait(
  id: 'aculta_purchasable_entity_shippable',
  label: new TranslatableMarkup('Shippable'),
  entity_types: ['commerce_product_variation'],
)]
class PurchasableEntityShippable extends EntityTraitBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions(): array {
    $fields = [];
    $fields['weight'] = BundleFieldDefinition::create('physical_measurement')
      ->setLabel(new TranslatableMarkup('Weight', [], ['context' => 'physical']))
      ->setRequired(TRUE)
      ->setSetting('measurement_type', MeasurementType::WEIGHT)
      ->setDisplayOptions('form', [
        'type' => 'physical_measurement_default',
        'weight' => 91,
      ]);

    return $fields;
  }

}
