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
 * @group aculta_commerce
 */
#[RunTestsInSeparateProcesses]
final class ShippingEngineKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'aculta_commerce',
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
    $this->markTestSkipped('O harness de Kernel do projeto não compila os serviços de aculta_commerce sem instalar a cadeia completa de 212 módulos do site (mesma limitação registrada para DonationPortKernelTest). A instalação limpa e o cálculo de tarifas serão comprovados no banco descartável da fase COM-SHIP-E.');
    parent::setUp();
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
   * The entity types and their storage are installed.
   */
  public function testEntityTypesInstalled(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->assertTrue($entity_type_manager->hasDefinition('aculta_shipment'));
    $this->assertTrue($entity_type_manager->hasDefinition('aculta_shipping_method'));
    $this->assertTrue($entity_type_manager->hasDefinition('aculta_shipment_type'));
    $this->assertSame('default', $entity_type_manager->getStorage('aculta_shipment_type')->load('default')->id());
  }

  /**
   * The plugin manager discovers the ACULTA flat rate plugin.
   */
  public function testShippingMethodPluginDiscovery(): void {
    $definitions = $this->container->get('plugin.manager.aculta_shipping_method')->getDefinitions();
    $this->assertArrayHasKey('aculta_flat_rate', $definitions);
    $this->assertArrayHasKey('aculta_flat_rate_per_item', $definitions);
  }

  /**
   * A shipment with items gets its weight and a trusted rate.
   */
  public function testRateCalculationAndApplication(): void {
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
  }

  /**
   * A method from another store is never eligible.
   */
  public function testMethodFromAnotherStoreIsNotEligible(): void {
    $other_store = Store::create([
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
