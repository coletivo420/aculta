<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit\Shipping;

use Drupal\aculta_commerce\Entity\ShipmentInterface;
use Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod\FlatRate;
use Drupal\aculta_commerce\Shipping\PackageTypeManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod\FlatRate
 *
 * @group aculta_commerce
 */
final class FlatRatePluginTest extends UnitTestCase {

  /**
   * Builds a flat rate plugin with the given configuration.
   */
  private function plugin(array $configuration): FlatRate {
    $definition = [
      'id' => 'aculta_flat_rate',
      'label' => 'Tarifa fixa',
      'services' => ['default' => 'Tarifa fixa'],
      'workflow' => 'aculta_shipment_default',
      'class' => FlatRate::class,
      'provider' => 'aculta_commerce',
    ];
    $plugin = new FlatRate($configuration, 'aculta_flat_rate', $definition, new PackageTypeManager());
    $parent = $this->createMock(EntityInterface::class);
    $parent->method('id')->willReturn(7);
    $plugin->setParentEntity($parent);
    return $plugin;
  }

  /**
   * A configured amount becomes one trusted rate.
   */
  public function testConfiguredAmountProducesOneRate(): void {
    $plugin = $this->plugin([
      'rate_label' => 'Envio padrão',
      'rate_amount' => ['number' => '12.34', 'currency_code' => 'BRL'],
    ]);
    $rates = $plugin->calculateRates($this->createMock(ShipmentInterface::class));

    $this->assertCount(1, $rates);
    $this->assertSame('7--default', $rates[0]->getId());
    $this->assertSame('7', $rates[0]->getShippingMethodId());
    $this->assertSame('default', $rates[0]->getService()->getId());
    $this->assertSame('Envio padrão', $rates[0]->getService()->getLabel());
    $this->assertSame('12.34', $rates[0]->getAmount()->getNumber());
    $this->assertSame('BRL', $rates[0]->getAmount()->getCurrencyCode());
  }

  /**
   * A method without an amount fails closed instead of returning zero.
   */
  public function testMissingAmountFailsClosed(): void {
    $plugin = $this->plugin(['rate_label' => 'Envio padrão']);
    $this->expectException(\InvalidArgumentException::class);
    $plugin->calculateRates($this->createMock(ShipmentInterface::class));
  }

  /**
   * Selecting a rate sets the method, service and amount on the shipment.
   */
  public function testSelectRateSetsShipmentFields(): void {
    $plugin = $this->plugin([
      'rate_label' => 'Envio padrão',
      'rate_amount' => ['number' => '12.34', 'currency_code' => 'BRL'],
    ]);
    $rate = $plugin->calculateRates($this->createMock(ShipmentInterface::class))[0];

    $shipment = $this->createMock(ShipmentInterface::class);
    $shipment->expects($this->once())->method('setShippingMethodId')->with(7);
    $shipment->expects($this->once())->method('setShippingService')->with('default');
    $shipment->expects($this->once())->method('setShippingServiceLabel')->with('Envio padrão');
    $shipment->expects($this->once())->method('setOriginalAmount');
    $shipment->expects($this->once())->method('setAmount');
    $plugin->selectRate($shipment, $rate);
  }

  /**
   * The plugin exposes its enabled services and workflow.
   */
  public function testServicesAndWorkflow(): void {
    $plugin = $this->plugin([
      'rate_label' => 'Envio padrão',
      'rate_amount' => ['number' => '12.34', 'currency_code' => 'BRL'],
    ]);
    $this->assertSame(['default'], array_keys($plugin->getServices()));
    $this->assertSame('aculta_shipment_default', $plugin->getWorkflowId());
    $this->assertSame('custom_box', $plugin->getDefaultPackageType()->getId());
  }

}
