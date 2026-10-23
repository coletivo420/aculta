<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Checkout\ShopCheckoutProvider;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Provider de checkout do contexto Shop (P-CART C).
 */
#[Group('aculta_commerce')]
final class ShopCheckoutProviderTest extends UnitTestCase {

  private function provider(?string $flow, array $settings = []): ShopCheckoutProvider {
    $type = $this->createMock(ConfigEntityInterface::class);
    $type->method('getThirdPartySetting')->willReturn($flow);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($type);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->willReturn(TRUE);
    $manager->method('getStorage')->willReturn($storage);
    return new ShopCheckoutProvider(new OrderTypeCheckoutFlows($manager), new CheckoutSettings($this->getConfigFactoryStub([CheckoutSettings::CONFIG => $settings])));
  }

  public function testDeclaraContextoModoTipoEFluxo(): void {
    $provider = $this->provider('default');
    $this->assertSame('shop', $provider->id());
    $this->assertSame(10, $provider->weight());
    $config = $provider->configuration();
    $this->assertSame(CheckoutMode::Common, $config->mode);
    $this->assertSame(['default'], $config->orderTypeIds);
    $this->assertSame(['default'], $config->checkoutFlowIds);
  }

  public function testSemFluxoNoCommerceNaoInventaUm(): void {
    $this->assertSame([], $this->provider(NULL)->configuration()->checkoutFlowIds);
  }

  public function testTipoConfiguradoSubstituiODefault(): void {
    $config = $this->provider('loja', ['shop_order_type' => 'loja'])->configuration();
    $this->assertSame(['loja'], $config->orderTypeIds);
  }

}
