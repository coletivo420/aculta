<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Kernel;

use Drupal\aculta_commerce\Entity\Shipment;
use Drupal\aculta_commerce\Entity\ShippingMethod;
use Drupal\aculta_commerce\Plugin\Commerce\CheckoutPane\AcultaShippingInformation;
use Drupal\aculta_commerce\Shipping\ProposedShipment;
use Drupal\aculta_commerce\Shipping\ShipmentItem;
use Drupal\aculta_commerce\Shipping\ShipmentManagerInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_store\Entity\Store;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Validates the ACULTA shipping entity model and rate calculation.
 *
 * The full dependency closure of aculta_commerce is enabled because the
 * module is a Portal submodule and its services reference Portal, Messenger
 * and Studio services at container compile time.
 *
 * @group aculta_commerce
 */
#[RunTestsInSeparateProcesses]
final class ShippingEngineKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'aculta_commerce',
    'aculta_commerce_shipping_test',
    'aculta_document_viewer',
    'aculta_forum',
    'aculta_gallery',
    'aculta_image',
    'aculta_mail_confirmer',
    'aculta_mail_confirmer_user',
    'aculta_mailer',
    'aculta_messenger',
    'aculta_pagamentos_mercadopago',
    'aculta_pdf_viewer',
    'aculta_portal',
    'aculta_portal_cep',
    'aculta_studio',
    'aculta_user',
    'address',
    'block',
    'ckeditor5',
    'comment',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_product',
    'commerce_store',
    'content_moderation',
    'csp',
    'ctools',
    'ctools_views',
    'datetime',
    'datetime_range',
    'diff',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'editor',
    'embed',
    'entity',
    'entity_embed',
    'entity_reference_revisions',
    'entity_usage',
    'field',
    'file',
    'filter',
    'flag',
    'flexible_permissions',
    'group',
    'history',
    'image',
    'inline_entity_form',
    'key',
    'language',
    'link',
    'lms',
    'login_emailusername',
    'media',
    'media_duplicates',
    'media_gallery',
    'media_library',
    'menu_link_content',
    'menu_ui',
    'message',
    'message_notify',
    'message_subscribe',
    'metatag',
    'migrate',
    'node',
    'options',
    'path',
    'path_alias',
    'pathauto',
    'photoswipe',
    'physical',
    'private_message',
    'profile',
    'reporting',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_metatag',
    'schema_organization',
    'schema_product',
    'schema_web_page',
    'schema_web_site',
    'search_api',
    'search_api_db',
    'site_alert',
    'social_api',
    'social_auth',
    'state_machine',
    'system',
    'taxonomy',
    'text',
    'token',
    'user',
    'views',
    'views_bulk_operations',
    'vvj_core',
    'vvjs',
    'workflows',
  ];

  /**
   * The shipment manager.
   */
  protected ShipmentManagerInterface $shipmentManager;

  /**
   * The store.
   */
  protected Store $store;

  /**
   * The order.
   */
  protected Order $order;

  /**
   * The shipping profile.
   */
  protected Profile $profile;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('commerce_store');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('profile');
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('aculta_shipment');
    $this->installEntitySchema('aculta_shipping_method');
    $this->installConfig(['commerce_store', 'commerce_order', 'profile', 'aculta_commerce']);
    $this->container->get('commerce_price.currency_importer')->import('BRL');

    $this->shipmentManager = $this->container->get('aculta_commerce.shipment_manager');

    $this->store = Store::create([
      'type' => 'online',
      'name' => 'Loja de teste',
      'mail' => 'loja@example.com',
      'address' => [
        'country_code' => 'BR',
        'administrative_area' => 'GO',
        'locality' => 'Goiânia',
        'postal_code' => '74000000',
        'address_line1' => 'Rua da Loja, 1',
      ],
      'default_currency' => 'BRL',
      'billing_countries' => ['BR'],
    ]);
    $this->store->save();

    $this->order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'uid' => 0,
      'currency_code' => 'BRL',
      'email' => 'cliente@example.com',
    ]);
    $this->order->save();

    $this->profile = Profile::create([
      'type' => 'customer',
      'uid' => 0,
      'address' => [
        'country_code' => 'BR',
        'administrative_area' => 'SP',
        'locality' => 'São Paulo',
        'dependent_locality' => 'Centro',
        'postal_code' => '01001000',
        'address_line1' => 'Rua A, 1',
        'given_name' => 'Cliente',
        'family_name' => 'Teste',
      ],
    ]);
    $this->profile->save();
  }

  /**
   * Creates an enabled flat rate shipping method for the given store.
   */
  protected function createMethod($store_id, string $amount = '12.34'): ShippingMethod {
    $method = ShippingMethod::create([
      'name' => 'Frete de teste',
      'stores' => [$store_id],
      'status' => TRUE,
      'weight' => 0,
      'plugin' => [
        [
          'target_plugin_id' => 'aculta_flat_rate',
          'target_plugin_configuration' => [
            'rate_label' => 'Envio de teste',
            'rate_description' => '',
            'rate_amount' => [
              'number' => $amount,
              'currency_code' => 'BRL',
            ],
          ],
        ],
      ],
    ]);
    $method->save();
    return $method;
  }

  /**
   * Creates a packed shipment for the order.
   */
  protected function createShipment(): Shipment {
    $shipment = Shipment::create(['type' => 'default']);
    $shipment->populateFromProposedShipment(new ProposedShipment([
      'type' => 'default',
      'order_id' => $this->order->id(),
      'title' => 'Envio',
      'items' => [
        new ShipmentItem(NULL, 'Camiseta', '2', new Weight('0.5', WeightUnit::KILOGRAM), new Price('100', 'BRL')),
      ],
      'shipping_profile' => $this->profile,
    ]));
    $shipment->save();
    return $shipment;
  }

  /**
   * Covers entity installation, plugin discovery and rate calculation.
   */
  public function testShippingEngine(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->assertTrue($entity_type_manager->hasDefinition('aculta_shipment'));
    $this->assertTrue($entity_type_manager->hasDefinition('aculta_shipping_method'));
    $this->assertTrue($entity_type_manager->hasDefinition('aculta_shipment_type'));
    $this->assertSame('default', $entity_type_manager->getStorage('aculta_shipment_type')->load('default')->id());

    $definitions = $this->container->get('plugin.manager.aculta_shipping_method')->getDefinitions();
    $this->assertArrayHasKey('aculta_flat_rate', $definitions);
    $this->assertArrayHasKey('aculta_flat_rate_per_item', $definitions);

    $this->createMethod($this->store->id());
    $shipment = $this->createShipment();
    $this->assertSame('2', $shipment->getTotalQuantity());
    $this->assertSame('0.5', $shipment->getWeight()->getNumber());
    $this->assertSame(WeightUnit::KILOGRAM, $shipment->getWeight()->getUnit());

    $rates = $this->shipmentManager->calculateRates($shipment);
    $this->assertCount(1, $rates);
    $rate = reset($rates);
    $this->assertSame('12.34', $rate->getAmount()->getNumber());
    $this->assertSame('BRL', $rate->getAmount()->getCurrencyCode());

    $this->shipmentManager->applyRate($shipment, $rate);
    $this->assertSame('12.34', $shipment->getAmount()->getNumber());
    $this->assertSame('Envio de teste', $shipment->getShippingServiceLabel());
    $this->assertSame('custom_box', $shipment->get('package_type')->value);

    // The shipment workflow starts in draft and exposes only valid transitions.
    $this->assertSame('draft', $shipment->getState()->getId());
    $this->assertArrayHasKey('finalize', $shipment->getState()->getTransitions());
    $shipment->getState()->applyTransitionById('finalize');
    $shipment->save();
    $this->assertSame('ready', $shipment->getState()->getId());
    $this->assertArrayHasKey('ship', $shipment->getState()->getTransitions());
    $this->assertArrayHasKey('cancel', $shipment->getState()->getTransitions());
    $this->assertArrayNotHasKey('finalize', $shipment->getState()->getTransitions());
    $shipment->getState()->applyTransitionById('ship');
    $shipment->save();
    $this->assertSame('shipped', $shipment->getState()->getId());
    $this->assertSame([], $shipment->getState()->getTransitions());
  }

  /**
   * The checkout pane recalculates rates and rejects a tampered selection.
   */
  public function testCheckoutPaneRevalidatesSelection(): void {
    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('aculta_commerce', 'shipment_type', 'default');
    $order_type->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    // Reload the order so its field definitions include the dynamic shipments
    // bundle field enabled by the third-party setting above.
    $this->order = $this->container->get('entity_type.manager')
      ->getStorage('commerce_order')
      ->load($this->order->id());

    $variation_type = ProductVariationType::create([
      'id' => 'pane_fisico',
      'label' => 'Pane físico',
      'orderItemType' => 'default',
      'generateTitle' => FALSE,
      'traits' => ['aculta_purchasable_entity_shippable'],
    ]);
    $variation_type->save();
    $trait_manager = $this->container->get('plugin.manager.commerce_entity_trait');
    $trait_manager->installTrait(
      $trait_manager->createInstance('aculta_purchasable_entity_shippable'),
      'commerce_product_variation',
      'pane_fisico',
    );
    $product_type = ProductType::create([
      'id' => 'pane_fisico',
      'label' => 'Pane físico',
      'variationType' => 'pane_fisico',
      'orderItemType' => 'default',
    ]);
    $product_type->save();
    $product = Product::create([
      'type' => 'pane_fisico',
      'title' => 'Camiseta do pane',
      'stores' => [$this->store->id()],
    ]);
    $product->save();
    $variation = ProductVariation::create([
      'type' => 'pane_fisico',
      'sku' => 'PANE-1',
      'product_id' => $product->id(),
      'price' => new Price('50.00', 'BRL'),
      'weight' => new Weight('0.3', WeightUnit::KILOGRAM),
    ]);
    $variation->save();
    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $variation,
      'quantity' => 1,
      'unit_price' => new Price('50.00', 'BRL'),
    ]);
    $order_item->save();
    $this->order->setBillingProfile($this->profile);
    $this->order->addItem($order_item);
    $this->order->save();

    $this->createMethod($this->store->id());

    $flow = $this->createMock(CheckoutFlowWithPanesInterface::class);
    $flow->method('getOrder')->willReturn($this->order);
    $flow->method('getPane')->willReturn(NULL);
    $flow->method('getSteps')->willReturn([
      'order_information' => 'Order information',
      'review' => 'Review',
    ]);
    $definition = [
      'id' => 'aculta_shipping_information',
      'class' => AcultaShippingInformation::class,
      'provider' => 'aculta_commerce',
      'default_step' => 'order_information',
      'wrapper_element' => 'fieldset',
    ];
    $pane = AcultaShippingInformation::create(
      $this->container,
      ['require_shipping_profile' => TRUE, 'auto_recalculate' => TRUE],
      'aculta_shipping_information',
      $definition,
      $flow,
    );

    $pane_form = [
      '#parents' => ['aculta_shipping_information'],
      '#type' => 'fieldset',
    ];
    $this->assertTrue($this->order->hasField('shipments'));
    $shipping_order_manager = $this->container->get('aculta_commerce.shipping_order_manager');
    $this->assertTrue($shipping_order_manager->isShippable($this->order));
    $items = $this->order->getItems();
    $this->assertNotEmpty($items);
    $this->assertTrue(reset($items)->getPurchasedEntity()->hasField('weight'));
    $complete_form = [];
    $this->assertTrue($pane->isVisible());
    $profile_method = new \ReflectionMethod($pane, 'getShippingProfile');
    $profile_method->setAccessible(TRUE);
    $pane_profile = $profile_method->invoke($pane);
    $this->assertInstanceOf(ProfileInterface::class, $pane_profile);
    $this->assertNotNull($this->order->getBillingProfile());
    $this->assertFalse($this->order->getBillingProfile()->get('address')->isEmpty());
    $this->assertSame($this->profile->id(), $pane_profile->id());
    $can_calculate = new \ReflectionMethod($pane, 'canCalculateRates');
    $can_calculate->setAccessible(TRUE);
    $this->assertCount(0, $pane_profile->get('address')->validate());
    $this->assertTrue($can_calculate->invoke($pane, $pane_profile));
    [$packed] = $this->container->get('aculta_commerce.packer_manager')->packToShipments($this->order, $this->profile, []);
    $this->assertNotEmpty($packed);
    $direct_rates = $this->shipmentManager->calculateRates(reset($packed));
    $this->assertNotEmpty($direct_rates);
    $built = $pane->buildPaneForm($pane_form, new FormState(), $complete_form);
    $this->assertArrayHasKey('shipments', $built);
    $this->assertArrayNotHasKey('message', $built);
    $this->assertArrayHasKey('shipping_method', $built['shipments'][0] ?? []);
    $options = $built['shipments'][0]['shipping_method']['#options'];
    $this->assertCount(1, $options);
    $rate_id = array_key_first($options);

    $valid = new FormState();
    $valid->setValue(['aculta_shipping_information', 'shipments', 0, 'shipping_method'], $rate_id);
    $pane->validatePaneForm($built, $valid, $complete_form);
    $this->assertSame([], $valid->getErrors());

    $tampered = new FormState();
    $tampered->setValue(['aculta_shipping_information', 'shipments', 0, 'shipping_method'], '999--fake');
    $pane->validatePaneForm($built, $tampered, $complete_form);
    $this->assertNotSame([], $tampered->getErrors());

    $submit = new FormState();
    $submit->setValue(['aculta_shipping_information', 'shipments', 0, 'shipping_method'], $rate_id);
    $pane->submitPaneForm($built, $submit, $complete_form);
    $shipments = $this->order->get('shipments')->referencedEntities();
    $this->assertCount(1, $shipments);
    $this->assertSame('12.340000', reset($shipments)->getAmount()->getNumber());
  }

  /**
   * A failing external provider is skipped, never converted to a zero rate.
   */
  public function testExternalProviderFailureIsClosed(): void {
    $method = ShippingMethod::create([
      'name' => 'Transportadora com sucesso',
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'weight' => 0,
      'plugin' => [
        [
          'target_plugin_id' => 'aculta_test_mock_carrier',
          'target_plugin_configuration' => [
            'rate_amount' => [
              'number' => '7.77',
              'currency_code' => 'BRL',
            ],
            'fail' => FALSE,
          ],
        ],
      ],
    ]);
    $method->save();
    $failing_method = ShippingMethod::create([
      'name' => 'Transportadora com falha',
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'weight' => 1,
      'plugin' => [
        [
          'target_plugin_id' => 'aculta_test_mock_carrier',
          'target_plugin_configuration' => [
            'rate_amount' => [
              'number' => '0.00',
              'currency_code' => 'BRL',
            ],
            'fail' => TRUE,
          ],
        ],
      ],
    ]);
    $failing_method->save();

    $shipment = $this->createShipment();
    $rates = $this->shipmentManager->calculateRates($shipment);
    $this->assertCount(1, $rates);
    $rate = reset($rates);
    $this->assertSame('7.77', $rate->getAmount()->getNumber());
    $this->assertSame('mock_express', $rate->getService()->getId());

    $method->setEnabled(FALSE);
    $method->save();
    $this->assertSame([], $this->shipmentManager->calculateRates($shipment));
  }

  /**
   * A method from another store is never eligible.
   */
  public function testMethodFromAnotherStoreIsNotEligible(): void {
    $other_store = Store::create([
      'type' => 'online',
      'name' => 'Outra loja',
      'mail' => 'outra@example.com',
      'address' => [
        'country_code' => 'BR',
        'administrative_area' => 'GO',
        'locality' => 'Goiânia',
        'postal_code' => '74000000',
        'address_line1' => 'Rua da Loja, 2',
      ],
      'default_currency' => 'BRL',
      'billing_countries' => ['BR'],
    ]);
    $other_store->save();
    $this->createMethod($other_store->id());

    $shipment = $this->createShipment();
    $this->assertSame([], $this->shipmentManager->calculateRates($shipment));
  }

  /**
   * A disabled method is never eligible.
   */
  public function testDisabledMethodIsNotEligible(): void {
    $method = $this->createMethod($this->store->id());
    $method->setEnabled(FALSE);
    $method->save();

    $shipment = $this->createShipment();
    $this->assertSame([], $this->shipmentManager->calculateRates($shipment));
  }

  /**
   * A method without an amount produces no rate instead of zero.
   */
  public function testUnconfiguredMethodProducesNoRate(): void {
    $method = ShippingMethod::create([
      'name' => 'Frete sem valor',
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'weight' => 0,
      'plugin' => [
        [
          'target_plugin_id' => 'aculta_flat_rate',
          'target_plugin_configuration' => [
            'rate_label' => 'Sem valor',
          ],
        ],
      ],
    ]);
    $method->save();

    $shipment = $this->createShipment();
    $this->assertSame([], $this->shipmentManager->calculateRates($shipment));
  }

}
