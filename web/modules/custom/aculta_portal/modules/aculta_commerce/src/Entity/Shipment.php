<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Entity;

use Drupal\aculta_commerce\Shipping\PackageType;
use Drupal\aculta_commerce\Shipping\ProposedShipment;
use Drupal\aculta_commerce\Shipping\ShipmentItem as ShipmentItemValue;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\physical\Calculator;
use Drupal\physical\Weight;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;

/**
 * Defines the ACULTA shipment entity.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). The engine
 * lives inside aculta_commerce; the entity type, tables, field names and
 * plugin IDs are ACULTA-owned.
 */
#[ContentEntityType(
  id: 'aculta_shipment',
  label: new TranslatableMarkup('Shipment'),
  label_collection: new TranslatableMarkup('Shipments'),
  label_singular: new TranslatableMarkup('shipment'),
  label_plural: new TranslatableMarkup('shipments'),
  entity_keys: [
    'id' => 'shipment_id',
    'uuid' => 'uuid',
    'bundle' => 'type',
    'label' => 'title',
  ],
  handlers: [
    'access' => \Drupal\aculta_commerce\ShipmentAccessControlHandler::class,
  ],
  bundle_entity_type: 'aculta_shipment_type',
  bundle_label: new TranslatableMarkup('Shipment type'),
  base_table: 'aculta_shipment',
  admin_permission: 'administer aculta shipments',
  collection_permission: 'administer aculta shipments',
)]
class Shipment extends ContentEntityBase implements ShipmentInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getOrder(): ?OrderInterface {
    $order = $this->get('order_id')->entity;
    return $order instanceof OrderInterface ? $order : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderId(): ?int {
    $value = $this->get('order_id')->target_id;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackageType(): ?PackageType {
    $package_type = (string) $this->get('package_type')->value;
    if ($package_type === '') {
      return NULL;
    }
    return \Drupal::service('aculta_commerce.package_type_manager')->getPackageType($package_type);
  }

  /**
   * {@inheritdoc}
   */
  public function setPackageType(?PackageType $package_type): static {
    $this->set('package_type', $package_type?->getId() ?? '');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingMethod(): ?ShippingMethodInterface {
    $shipping_method = $this->get('shipping_method')->entity;
    return $shipping_method instanceof ShippingMethodInterface ? $shipping_method : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingMethodId(): ?int {
    $value = $this->get('shipping_method')->target_id;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingMethodId(?int $shipping_method_id): static {
    $this->set('shipping_method', $shipping_method_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingService(): ?string {
    $value = (string) $this->get('shipping_service')->value;
    return $value !== '' ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingService(?string $shipping_service): static {
    $this->set('shipping_service', $shipping_service ?? '');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingServiceLabel(): ?string {
    $value = (string) $this->get('service_label')->value;
    return $value !== '' ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingServiceLabel(?string $shipping_service_label): static {
    $this->set('service_label', $shipping_service_label ?? '');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingProfile(): ?ProfileInterface {
    $profile = $this->get('shipping_profile')->entity;
    return $profile instanceof ProfileInterface ? $profile : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingProfile(ProfileInterface $profile): static {
    $this->set('shipping_profile', $profile);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): ?string {
    $value = (string) $this->get('title')->value;
    return $value !== '' ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): static {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItems(): array {
    $items = [];
    foreach ($this->get('items') as $item) {
      $shipment_item = $item->getShipmentItem();
      if ($shipment_item) {
        $items[] = $shipment_item;
      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function hasItems(): bool {
    return !$this->get('items')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalQuantity(): string {
    $quantity = '0';
    foreach ($this->getItems() as $item) {
      $quantity = Calculator::add($quantity, $item->getQuantity());
    }
    return $quantity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalDeclaredValue(): ?Price {
    $total = NULL;
    foreach ($this->getItems() as $item) {
      $declared_value = $item->getDeclaredValue();
      $total = $total ? $total->add($declared_value) : $declared_value;
    }
    return $total;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): ?Weight {
    if (!$this->get('weight')->isEmpty()) {
      return $this->get('weight')->first()->toMeasurement();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(Weight $weight): static {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalAmount(): ?Price {
    if (!$this->get('original_amount')->isEmpty()) {
      return $this->get('original_amount')->first()->toPrice();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalAmount(?Price $amount): static {
    $this->set('original_amount', $amount);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmount(): ?Price {
    if (!$this->get('amount')->isEmpty()) {
      return $this->get('amount')->first()->toPrice();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setAmount(?Price $amount): static {
    $this->set('amount', $amount);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackingCode(): ?string {
    $value = (string) $this->get('tracking_code')->value;
    return $value !== '' ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setTrackingCode(?string $tracking_code): static {
    $this->set('tracking_code', $tracking_code ?? '');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): StateItemInterface {
    return $this->get('state')->first();
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

  /**
   * {@inheritdoc}
   */
  public function getData(string $key, mixed $default = NULL): mixed {
    $data = [];
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
    }
    return $data[$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setData(string $key, mixed $value): static {
    $this->get('data')->__set($key, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetData(string $key): static {
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
      unset($data[$key]);
      $this->set('data', $data);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clearRate(): static {
    $this->set('shipping_method', NULL);
    $this->set('shipping_service', '');
    $this->set('service_label', '');
    $this->set('original_amount', NULL);
    $this->set('amount', NULL);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function populateFromProposedShipment(ProposedShipment $proposed_shipment): static {
    $this->set('type', $proposed_shipment->getType());
    $this->set('order_id', $proposed_shipment->getOrderId());
    $this->set('title', $proposed_shipment->getTitle());
    $this->set('shipping_profile', $proposed_shipment->getShippingProfile());
    $this->set('items', []);
    foreach ($proposed_shipment->getItems() as $item) {
      /** @var \Drupal\aculta_commerce\Shipping\ShipmentItem $item */
      $this->get('items')->appendItem(['value' => $this->encodeShipmentItem($item)]);
    }
    $this->prepareFields();
    return $this;
  }

  /**
   * Encodes a shipment item value for the aculta_shipment_item field.
   */
  protected function encodeShipmentItem(ShipmentItemValue $item): string {
    $weight = $item->getWeight();
    $declared_value = $item->getDeclaredValue();
    return json_encode([
      'order_item_id' => $item->getOrderItemId(),
      'title' => $item->getTitle(),
      'quantity' => $item->getQuantity(),
      'weight' => $weight->getNumber(),
      'weight_unit' => $weight->getUnit(),
      'declared_value' => $declared_value->getNumber(),
      'declared_currency' => $declared_value->getCurrencyCode(),
    ], JSON_THROW_ON_ERROR);
  }

  /**
   * Ensures that the package type and weight fields are populated.
   */
  protected function prepareFields(): void {
    if (empty($this->get('package_type')->value) && $this->getShippingMethodId()) {
      $shipping_method = $this->getShippingMethod();
      if ($shipping_method) {
        $default_package_type = $shipping_method->getPlugin()->getDefaultPackageType();
        $this->set('package_type', $default_package_type->getId());
      }
    }
    $this->recalculateWeight();
  }

  /**
   * Recalculates the shipment weight from items and package type.
   */
  protected function recalculateWeight(): void {
    if (!$this->hasItems()) {
      return;
    }
    $weight = NULL;
    foreach ($this->getItems() as $shipment_item) {
      $shipment_item_weight = $shipment_item->getWeight();
      $weight = $weight ? $weight->add($shipment_item_weight) : $shipment_item_weight;
    }
    if ($package_type = $this->getPackageType()) {
      $weight = $weight->add($package_type->getWeight());
    }
    if ($weight) {
      $this->setWeight($weight);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    $this->prepareFields();
    if ($this->get('order_id')->isEmpty()) {
      throw new EntityMalformedException('Required shipment field "order_id" is empty.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);
    $profiles_to_delete = [];
    /** @var \Drupal\aculta_commerce\Entity\ShipmentInterface $shipment */
    foreach ($entities as $shipment) {
      $shipping_profile = $shipment->getShippingProfile();
      if ($shipping_profile && !$shipping_profile->getOwnerId()) {
        $profiles_to_delete[] = $shipping_profile;
      }
    }
    if ($profiles_to_delete) {
      \Drupal::entityTypeManager()->getStorage('profile')->delete($profiles_to_delete);
    }
  }

  /**
   * Gets the workflow ID for the state field.
   */
  public static function getWorkflowId(ShipmentInterface $shipment): string {
    if ($shipping_method = $shipment->getShippingMethod()) {
      if ($plugin = $shipping_method->getPlugin()) {
        return $plugin->getWorkflowId();
      }
    }
    return 'aculta_shipment_default';
  }

  /**
   * Returns the field definition for the order 'shipments' field.
   */
  public static function buildShipmentsFieldDefinition(?string $order_type_id = NULL): BundleFieldDefinition {
    return BundleFieldDefinition::create('entity_reference')
      ->setTargetEntityTypeId('commerce_order')
      ->setTargetBundle($order_type_id)
      ->setName('shipments')
      ->setLabel('Shipments')
      ->setCardinality(BundleFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'aculta_shipment')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'label' => 'above',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['order_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Order'))
      ->setDescription(t('The parent order.'))
      ->setSetting('target_type', 'commerce_order')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['package_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Package type'))
      ->setDescription(t('The package type.'))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['shipping_method'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Shipping method'))
      ->setSetting('target_type', 'aculta_shipping_method')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('view', TRUE);

    $fields['shipping_service'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Shipping service'))
      ->setDescription(t('The shipping service.'))
      ->setDefaultValue('')
      ->setSetting('max_length', 255);

    $fields['service_label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service label'))
      ->setDescription(t('The shipping service label.'))
      ->setSetting('max_length', 255);

    $fields['shipping_profile'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('Shipping information'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'profile')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('view', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The shipment title.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['items'] = BaseFieldDefinition::create('aculta_shipment_item')
      ->setLabel(t('Items'))
      ->setRequired(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('view', TRUE);

    $fields['weight'] = BaseFieldDefinition::create('physical_measurement')
      ->setLabel(t('Weight', [], ['context' => 'physical']))
      ->setRequired(TRUE)
      ->setSetting('measurement_type', 'weight')
      ->setDisplayConfigurable('view', TRUE);

    $fields['original_amount'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Original amount'))
      ->setDescription(t('The original amount.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['amount'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Amount'))
      ->setDescription(t('The amount.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['tracking_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tracking code'))
      ->setDescription(t('The shipment tracking code.'))
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['state'] = BaseFieldDefinition::create('state')
      ->setLabel(t('State'))
      ->setDescription(t('The shipment state.'))
      ->setRequired(TRUE)
      ->setSetting('workflow_callback', [static::class, 'getWorkflowId'])
      ->setDisplayConfigurable('view', TRUE);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('A serialized array of additional data.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the shipment was created.'))
      ->setRequired(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the shipment was last updated.'))
      ->setRequired(TRUE);

    $fields['shipped'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Shipped'))
      ->setDescription(t('The time when the shipment was shipped.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions): array {
    // The shipment type config may not be available yet while the entity
    // schema is first installed; fall back to the standard customer profile.
    $profile_type_id = ShipmentType::load($bundle)?->getProfileTypeId() ?? 'customer';
    $fields = [];
    $fields['shipping_profile'] = clone $base_field_definitions['shipping_profile'];
    $fields['shipping_profile']->setSetting('handler_settings', [
      'target_bundles' => [
        $profile_type_id => $profile_type_id,
      ],
    ]);
    return $fields;
  }

}
