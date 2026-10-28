<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Checkout da Loja e do LMS, lido do Config Sync sem banco.
 *
 * D-43 (2026-10-28): a Loja mantém o flow `default` com envio; o LMS tem o
 * flow `curso` próprio, sem envio, com identidade fiscal e pagamento. A
 * unificação de 2026-10-23 (curso no `default`) está revogada. Doações
 * continuam sem o pane fiscal.
 */
#[Group('aculta_portal')]
final class CheckoutFlowConfigTest extends UnitTestCase {

  /**
   * Lê um YAML de config/sync pelo nome da configuração.
   *
   * @return array<string,
   *   mixed> Conteúdo do arquivo.
   */
  private function sync(string $name): array {
    $root = dirname(__DIR__, 7);
    $path = $root . '/config/sync/' . $name . '.yml';
    $this->assertFileExists($path);
    return Yaml::parseFile($path);
  }

  /**
   * Verifies guest checkout and Core account creation in the shop flow.
   */
  public function testFlowDefaultHasFiscalPaneAndCoreGuestRegistration(): void {
    $configuration = $this->sync('commerce_checkout.commerce_checkout_flow.default')['configuration'];
    $panes = $configuration['panes'];
    $this->assertArrayHasKey('aculta_fiscal_identity', $panes);
    $this->assertSame('review', $panes['aculta_fiscal_identity']['step']);
    $this->assertTrue($panes['login']['allow_guest_checkout']);
    $this->assertFalse($panes['login']['allow_registration']);
    $this->assertTrue($configuration['guest_new_account']);
    $this->assertTrue($configuration['guest_new_account_notify']);
    $this->assertFalse($configuration['guest_order_assign']);
  }

  /**
   * O flow do LMS é próprio, sem envio, com identidade fiscal e pagamento.
   */
  public function testFlowCursoHasNoShippingAndHasFiscalAndPayment(): void {
    $configuration = $this->sync('commerce_checkout.commerce_checkout_flow.curso')['configuration'];
    $panes = $configuration['panes'];

    $this->assertSame('multistep_default', $this->sync('commerce_checkout.commerce_checkout_flow.curso')['plugin']);
    $this->assertArrayNotHasKey('aculta_shipping_information', $panes);
    $this->assertArrayNotHasKey('aculta_billing_address', $panes);
    $this->assertArrayHasKey('aculta_fiscal_identity', $panes);
    $this->assertSame('review', $panes['aculta_fiscal_identity']['step']);
    $this->assertArrayHasKey('aculta_personal_name', $panes);
    $this->assertSame('order_information', $panes['aculta_personal_name']['step']);
    $this->assertArrayHasKey('payment_information', $panes);
    $this->assertArrayHasKey('payment_process', $panes);
    $this->assertSame('payment', $panes['payment_process']['step']);
    $this->assertTrue($panes['payment_process']['capture']);
    $this->assertTrue($panes['login']['allow_guest_checkout']);
    $this->assertTrue($panes['login']['allow_registration']);
  }

  /**
   * Verifica que o tipo de pedido curso usa o flow próprio do LMS.
   */
  public function testCursoOrderTypeUsesOwnFlow(): void {
    $flow = $this->sync('commerce_order.commerce_order_type.curso')['third_party_settings']['commerce_checkout']['checkout_flow'];
    $this->assertSame('curso', $flow);
  }

  /**
   * O flow do LMS não reaproveita o flow da Loja.
   */
  public function testLmsFlowIsNotTheShopFlow(): void {
    $this->assertNotSame(
      $this->sync('commerce_checkout.commerce_checkout_flow.default'),
      $this->sync('commerce_checkout.commerce_checkout_flow.curso'),
    );
  }

  /**
   * Verifica que os flows de doação não têm o pane fiscal.
   */
  public function testDonationFlowsHaveNoFiscalPane(): void {
    $flows = [
      'commerce_checkout.commerce_checkout_flow.doacao',
      'commerce_checkout.commerce_checkout_flow.assinatura_flow',
    ];
    foreach ($flows as $flow) {
      $this->assertArrayNotHasKey('aculta_fiscal_identity', $this->sync($flow)['configuration']['panes'], $flow);
    }
  }

}
