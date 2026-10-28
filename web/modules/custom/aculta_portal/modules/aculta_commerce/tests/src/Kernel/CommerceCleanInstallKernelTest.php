<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Kernel;

use Drupal\aculta_commerce\Attribute\AcultaShippingMethod;
use Drupal\aculta_commerce\Shipping\ShipmentManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves a clean aculta_commerce install without the legacy shipping module.
 *
 * Starts from a minimal system-only Kernel. The module installer resolves the
 * real dependency closure from aculta_commerce.info.yml, installs schemas and
 * config, and the assertions verify that the ACULTA shipping engine is
 * available while commerce_shipping is neither required nor enabled.
 */
#[Group('aculta_commerce')]
#[RunTestsInSeparateProcesses]
final class CommerceCleanInstallKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'entity', 'views', 'profile'];

  /**
   * Installs aculta_commerce from scratch and checks the shipping engine.
   */
  public function testCleanInstallProvidesAcultaShippingWithoutLegacyModule(): void {
    // system.menu.admin and the other system config are prerequisites for
    // configs installed later by the dependency closure (for example lms).
    $this->installConfig(['system', 'user']);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('profile');

    $installer = $this->container->get('module_installer');
    self::assertTrue($installer->install(['aculta_commerce'], TRUE));

    $module_handler = $this->container->get('module_handler');
    self::assertTrue($module_handler->moduleExists('aculta_commerce'));
    self::assertTrue($module_handler->moduleExists('aculta_studio'));
    self::assertTrue($module_handler->moduleExists('commerce_checkout'));
    self::assertTrue($module_handler->moduleExists('commerce_cart'), 'commerce_cart must remain available transitively through commerce_checkout.');
    self::assertFalse($module_handler->moduleExists('commerce_shipping'), 'A clean install must not enable commerce_shipping.');
    // commerce_payment may still arrive through another Portal module; the
    // manifest regression test guards that aculta_commerce itself no longer
    // declares it.
    $entity_type_manager = $this->container->get('entity_type.manager');
    self::assertTrue($entity_type_manager->hasDefinition('aculta_shipment'));
    self::assertTrue($entity_type_manager->hasDefinition('aculta_shipping_method'));
    self::assertTrue($entity_type_manager->hasDefinition('aculta_shipment_type'));
    self::assertFalse($entity_type_manager->hasDefinition('commerce_shipping_method'));
    self::assertFalse($entity_type_manager->hasDefinition('commerce_shipment'));

    self::assertInstanceOf(
      ShipmentManagerInterface::class,
      $this->container->get('aculta_commerce.shipment_manager'),
    );
    self::assertTrue($this->container->has('plugin.manager.aculta_shipping_method'));
    self::assertSame(
      'aculta_flat_rate',
      $this->container->get('plugin.manager.aculta_shipping_method')->getDefinition('aculta_flat_rate')['id'] ?? NULL,
    );
    self::assertTrue(class_exists(AcultaShippingMethod::class));

    $field_storage = $entity_type_manager->getStorage('field_storage_config')->load('commerce_order.shipments');
    self::assertNotNull($field_storage);
    self::assertSame('aculta_shipment', $field_storage->getSetting('target_type'));

    self::assertFalse(
      $module_handler->hasImplementations('requirements', ['aculta_commerce']),
      'aculta_commerce must not reintroduce legacy hook_requirements().',
    );
  }

}
