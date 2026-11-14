<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Unit;

use Drupal\aculta_donation\Checkout\DonationCheckoutProvider;
use Drupal\aculta_portal\Checkout\CheckoutMode;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * O provider traduz `donation_route` para o vocabulário neutro sem alterar a configuração (P-CART C).
 */
#[Group('aculta_donation')]
final class DonationCheckoutProviderTest extends UnitTestCase {

  /**
   * Cria o provider de doação com as dependências simuladas.
   *
   * @param array $settings
   *   Configurações do módulo de doação.
   * @param string|null $subscriptionFlow
   *   Flow de checkout da assinatura, ou NULL se não houver.
   *
   * @return \Drupal\aculta_donation\Checkout\DonationCheckoutProvider
   *   Provider configurado.
   */
  private function provider(array $settings, ?string $subscriptionFlow = 'assinatura_flow'): DonationCheckoutProvider {
    $type = NULL;
    if ($subscriptionFlow !== NULL) {
      $type = $this->createMock(ConfigEntityInterface::class);
      $type->method('getThirdPartySetting')->willReturn($subscriptionFlow);
    }
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($type);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->willReturn(TRUE);
    $manager->method('getStorage')->willReturn($storage);
    return new DonationCheckoutProvider(
      $this->getConfigFactoryStub(['aculta_donation.settings' => $settings]),
      new OrderTypeCheckoutFlows($manager),
    );
  }

  /**
   * Verifica a tradução de donation_route para o modo neutro.
   *
   * @param string|null $route
   *   Valor configurado de donation_route.
   * @param \Drupal\aculta_portal\Checkout\CheckoutMode $esperado
   *   Modo neutro esperado.
   */
  #[DataProvider('modos')]
  public function testTraduzDonationRouteParaModoNeutro(?string $route, CheckoutMode $esperado): void {
    $this->assertSame($esperado, $this->provider(['donation_route' => $route])->configuration()->mode);
  }

  /**
   * Fornece valores de donation_route e o modo esperado.
   *
   * @return array<string, array{string|null, \Drupal\aculta_portal\Checkout\CheckoutMode}>
   *   Casos de teste indexados por descrição.
   */
  public static function modos(): array {
    return [
      'donate' => ['donate', CheckoutMode::Dedicated],
      'cart' => ['cart', CheckoutMode::Common],
      'both' => ['both', CheckoutMode::Both],
      'ausente' => [NULL, CheckoutMode::Dedicated],
      'inválido' => ['xyz', CheckoutMode::Dedicated],
    ];
  }

  /**
   * Verifica os tipos de pedido e fluxos da configuração mais a assinatura.
   */
  public function testDeclaraTiposDePedidoEFluxosDaConfiguracaoMaisAssinatura(): void {
    $config = $this->provider([
      'donation_route' => 'both',
      'donate_order_type' => 'doacao',
      'donate_checkout_flow' => 'doacao',
      'cart_order_type' => NULL,
      'cart_checkout_flow' => NULL,
    ])->configuration();
    $this->assertSame('donation', $config->contextId);
    $this->assertSame(['assinatura', 'doacao'], $config->orderTypeIds);
    $this->assertSame(['assinatura_flow', 'doacao'], $config->checkoutFlowIds);
  }

  /**
   * Verifica que, sem fluxo de assinatura no Commerce, nenhum é inventado.
   */
  public function testSemFluxoDeAssinaturaNoCommerceNaoInventaUm(): void {
    $config = $this->provider(['donation_route' => 'donate', 'donate_checkout_flow' => 'doacao'], NULL)->configuration();
    $this->assertSame(['doacao'], $config->checkoutFlowIds);
  }

}
