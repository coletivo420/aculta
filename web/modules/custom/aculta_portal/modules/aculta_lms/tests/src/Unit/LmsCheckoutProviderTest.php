<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Checkout\LmsCheckoutProvider;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Provider de checkout do contexto Lms (P-CART C).
 */
#[Group('aculta_lms')]
final class LmsCheckoutProviderTest extends UnitTestCase {

  private function provider(?string $flow, array $settings = []): LmsCheckoutProvider {
    $type = $this->createMock(ConfigEntityInterface::class);
    $type->method('getThirdPartySetting')->willReturn($flow);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($type);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->willReturn(TRUE);
    $manager->method('getStorage')->willReturn($storage);
    return new LmsCheckoutProvider(new OrderTypeCheckoutFlows($manager), new CheckoutSettings($this->getConfigFactoryStub([CheckoutSettings::CONFIG => $settings])));
  }

  public function testDeclaraContextoModoTipoEFluxo(): void {
    $provider = $this->provider('curso');
    $this->assertSame('lms', $provider->id());
    $this->assertSame(20, $provider->weight());
    $config = $provider->configuration();
    $this->assertSame(CheckoutMode::Common, $config->mode);
    $this->assertSame(['curso'], $config->orderTypeIds);
    $this->assertSame(['curso'], $config->checkoutFlowIds);
  }

  public function testSemFluxoNoCommerceNaoInventaUm(): void {
    $this->assertSame([], $this->provider(NULL)->configuration()->checkoutFlowIds);
  }

  public function testTipoConfiguradoVemPrimeiroEOHistoricoCursoContinuaDeclarado(): void {
    $config = $this->provider('curso_novo', ['lms_order_type' => 'curso_novo'])->configuration();
    $this->assertSame(['curso', 'curso_novo'], $config->orderTypeIds);
    $this->assertSame(['curso_novo'], $config->checkoutFlowIds);
  }

}
