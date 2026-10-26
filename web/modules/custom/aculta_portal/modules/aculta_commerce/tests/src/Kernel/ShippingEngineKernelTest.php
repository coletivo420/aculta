<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Kernel;

use Drupal\aculta_commerce\Entity\Shipment;
use Drupal\aculta_commerce\Entity\ShippingMethod;
use Drupal\aculta_commerce\Shipping\ProposedShipment;
use Drupal\aculta_commerce\Shipping\ShipmentItem;
use Drupal\aculta_commerce\Shipping\ShipmentManagerInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Entity\Store;
use Drupal\KernelTests\KernelTestBase;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\profile\Entity\Profile;
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
    $this->installEntitySchema('commerce_store');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('aculta_shipment');
    $this->installEntitySchema('aculta_shipping_method');
    $this->installConfig(['commerce_store', 'commerce_order', 'profile', 'aculta_commerce']);

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
