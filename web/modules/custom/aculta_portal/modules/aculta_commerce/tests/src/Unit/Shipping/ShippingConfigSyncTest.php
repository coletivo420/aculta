<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit\Shipping;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guards the ACULTA shipping entries in the versioned configuration.
 */
#[Group('aculta_commerce')]
final class ShippingConfigSyncTest extends UnitTestCase {

  /**
   * The repository root.
   */
  private string $repoRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->repoRoot = dirname(__DIR__, 10);
  }

  /**
   * Reads a versioned configuration file.
   */
  private function config(string $name): array {
    $path = $this->repoRoot . '/config/sync/' . $name . '.yml';
    $this->assertFileExists($path);
    $value = Yaml::parseFile($path);
    $this->assertIsArray($value);
    return $value;
  }

  /**
   * The order type, variation trait and field storage point at ACULTA.
   */
  public function testShippingConfigTargetsAculta(): void {
    $order_type = $this->config('commerce_order.commerce_order_type.default');
    $this->assertSame('default', $order_type['third_party_settings']['aculta_commerce']['shipment_type'] ?? NULL);
    $this->assertArrayNotHasKey('commerce_shipping', $order_type['third_party_settings'] ?? []);

    $variation_type = $this->config('commerce_product.commerce_product_variation_type.camiseta_tamanho');
    $this->assertContains('aculta_purchasable_entity_shippable', $variation_type['traits']);
    $this->assertNotContains('purchasable_entity_shippable', $variation_type['traits']);

    $field_storage = $this->config('field.storage.commerce_order.shipments');
    $this->assertSame('aculta_shipment', $field_storage['settings']['target_type']);
    $this->assertContains('aculta_commerce', $field_storage['dependencies']['module']);
    $this->assertNotContains('commerce_shipping', $field_storage['dependencies']['module']);

    $this->assertFileExists($this->repoRoot . '/config/sync/aculta_commerce.aculta_shipment_type.default.yml');
  }

  /**
   * Both checkout flows use the ACULTA pane and no contributed pane.
   */
  public function testCheckoutFlowsUseAcultaPane(): void {
    foreach (['default', 'shipping'] as $flow_id) {
      $flow = $this->config('commerce_checkout.commerce_checkout_flow.' . $flow_id);
      $panes = $flow['configuration']['panes'];
      $this->assertArrayHasKey('aculta_shipping_information', $panes);
      $this->assertArrayNotHasKey('shipping_information', $panes);
      $this->assertContains('aculta_commerce', $flow['dependencies']['module']);
      $this->assertNotContains('commerce_shipping', $flow['dependencies']['module']);
    }
  }

  /**
   * The contributed module is not enabled or referenced by shipping config.
   */
  public function testContributedModuleIsNotEnabled(): void {
    $core_extension = $this->config('core.extension');
    $this->assertArrayNotHasKey('commerce_shipping', $core_extension['module']);

    $anonymous = $this->config('user.role.anonymous');
    $this->assertNotContains('view commerce_shipping_method', $anonymous['permissions']);
    $this->assertNotContains('commerce_shipping', $anonymous['dependencies']['module']);

    foreach (glob($this->repoRoot . '/config/sync/commerce_shipping.*.yml') ?: [] as $file) {
      $this->fail('Contributed shipping config remains: ' . basename($file));
    }
  }

}
