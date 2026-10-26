<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Plugin\Commerce\EntityTrait;

use Drupal\commerce\Attribute\CommerceEntityTrait;
use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;

/**
 * Provides the "aculta_purchasable_entity_dimensions" trait.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later).
 */
#[CommerceEntityTrait(
  id: 'aculta_purchasable_entity_dimensions',
  label: new TranslatableMarkup('Has dimensions'),
  entity_types: ['commerce_product_variation'],
)]
class PurchasableEntityDimensions extends EntityTraitBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions(): array {
    $fields = [];
    $fields['dimensions'] = BundleFieldDefinition::create('physical_dimensions')
      ->setLabel(new TranslatableMarkup('Dimensions'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'physical_dimensions_default',
        'weight' => 90,
      ]);

    return $fields;
  }

}
