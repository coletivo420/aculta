<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Entity;

use Drupal\aculta_commerce\ShippingMethodAccessControlHandler;
use Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod\ShippingMethodInterface as ShippingMethodPluginInterface;
use Drupal\commerce\ConditionGroup;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface;
use Drupal\commerce\Plugin\Commerce\Condition\ParentEntityAwareInterface;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the ACULTA shipping method entity.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Uses the
 * ACULTA-owned aculta_shipping_method plugin type and its own plugin field;
 * it does not depend on the contributed commerce_shipping module.
 */
#[ContentEntityType(
  id: 'aculta_shipping_method',
  label: new TranslatableMarkup('Shipping method'),
  label_collection: new TranslatableMarkup('Shipping methods'),
  label_singular: new TranslatableMarkup('shipping method'),
  label_plural: new TranslatableMarkup('shipping methods'),
  entity_keys: [
    'id' => 'shipping_method_id',
    'label' => 'name',
    'uuid' => 'uuid',
    'status' => 'status',
    'published' => 'status',
  ],
  handlers: [
    'storage' => \Drupal\aculta_commerce\ShippingMethodStorage::class,
    'access' => ShippingMethodAccessControlHandler::class,
  ],
  base_table: 'aculta_shipping_method',
  admin_permission: 'administer aculta shipping methods',
  collection_permission: 'administer aculta shipping methods',
)]
class ShippingMethod extends ContentEntityBase implements ShippingMethodInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public function getStores(): array {
    return $this->get('stores')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function setStores(array $stores): static {
    $this->set('stores', $stores);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStoreIds(): array {
    $store_ids = [];
    foreach ($this->get('stores') as $field_item) {
      $store_ids[] = $field_item->target_id;
    }
    return $store_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function setStoreIds(array $store_ids): static {
    $this->set('stores', $store_ids);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(): ShippingMethodPluginInterface {
    /** @var \Drupal\commerce\Plugin\Field\FieldType\PluginItemInterface $field_item */
    $field_item = $this->get('plugin')->first();
    $plugin = $field_item->getTargetInstance();
    $plugin->setParentEntity($this);

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): ?string {
    if (!$this->get('name')->isEmpty()) {
      return $this->get('name')->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): static {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions(): array {
    $conditions = [];
    foreach ($this->get('conditions') as $field_item) {
      /** @var \Drupal\commerce\Plugin\Field\FieldType\PluginItemInterface $field_item */
      $condition = $field_item->getTargetInstance();
      if ($condition instanceof ParentEntityAwareInterface) {
        $condition->setParentEntity($this);
      }
      $conditions[] = $condition;
    }
    return $conditions;
  }

  /**
   * {@inheritdoc}
   */
  public function setConditions(array $conditions): static {
    $this->set('conditions', []);
    foreach ($conditions as $condition) {
      if ($condition instanceof ConditionInterface) {
        $this->get('conditions')->appendItem([
          'target_plugin_id' => $condition->getPluginId(),
          'target_plugin_configuration' => $condition->getConfiguration(),
        ]);
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditionOperator(): string {
    return (string) $this->get('condition_operator')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setConditionOperator(string $condition_operator): static {
    $this->set('condition_operator', $condition_operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return (int) $this->get('weight')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(int $weight): static {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled(bool $enabled): static {
    $this->set('status', $enabled);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(ShipmentInterface $shipment): bool {
    if ($this->getPlugin() && !$this->getPlugin()->applies($shipment)) {
      return FALSE;
    }
    $conditions = $this->getConditions();
    if (!$conditions) {
      // Shipping methods without conditions always apply.
      return TRUE;
    }
    $order_conditions = array_filter($conditions, static function ($condition): bool {
      /** @var \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface $condition */
      return $condition->getEntityTypeId() === 'commerce_order';
    });
    $shipment_conditions = array_filter($conditions, static function ($condition): bool {
      /** @var \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface $condition */
      return $condition->getEntityTypeId() === 'aculta_shipment';
    });
    $operator = $this->getConditionOperator();
    $order_conditions_group = new ConditionGroup($order_conditions, $operator);
    $shipment_conditions_group = new ConditionGroup($shipment_conditions, $operator);

    return $operator === 'OR'
      ? ($order_conditions && $order_conditions_group->evaluate($shipment->getOrder())) || ($shipment_conditions && $shipment_conditions_group->evaluate($shipment))
      : $order_conditions_group->evaluate($shipment->getOrder()) && $shipment_conditions_group->evaluate($shipment);
  }

  /**
   * Helper callback for uasort() to sort shipping methods by weight and label.
   */
  public static function sort(ShippingMethodInterface $a, ShippingMethodInterface $b): int {
    $a_weight = $a->getWeight();
    $b_weight = $b->getWeight();
    if ($a_weight == $b_weight) {
      return strnatcasecmp((string) $a->label(), (string) $b->label());
    }
    return ($a_weight < $b_weight) ? -1 : 1;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['stores'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Stores'))
      ->setDescription(t('The stores for which the shipping method is valid.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'commerce_store')
      ->setSetting('handler', 'default');

    $fields['plugin'] = BaseFieldDefinition::create('commerce_plugin_item:aculta_shipping_method')
      ->setLabel(t('Plugin'))
      ->setCardinality(1)
      ->setRequired(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The shipping method name.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['conditions'] = BaseFieldDefinition::create('commerce_plugin_item:commerce_condition')
      ->setLabel(t('Conditions'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE);

    $fields['condition_operator'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Condition operator'))
      ->setDescription(t('The condition operator.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'AND' => t('All conditions must pass'),
        'OR' => t('Only one condition must pass'),
      ])
      ->setDefaultValue('AND');

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight', [], ['context' => 'physical']))
      ->setDescription(t('The weight of this shipping method in relation to others.'))
      ->setDefaultValue(0);

    $fields['status']
      ->setLabel(t('Enabled'))
      ->setDescription(t('Whether the shipping method is enabled.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the shipping method was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the shipping method was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): static {
    $this->set('created', $timestamp);
    return $this;
  }

}
