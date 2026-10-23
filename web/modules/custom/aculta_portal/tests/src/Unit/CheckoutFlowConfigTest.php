<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Fiação do checkout comercial compartilhado (Loja e LMS), lida do config/sync: sem banco.
 *
 * Garante que o flow `default` tem o pane de identidade fiscal e a política de
 * login decidida, que o flow `curso` duplicado saiu, e que Doações continuam
 * sem o pane. A aplicação em runtime é feita por config:import autorizado.
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
   * Verifica que o flow default tem o pane fiscal, sem guest e com cadastro.
   */
  public function testFlowDefaultHasFiscalPaneAndNoGuest(): void {
    $panes = $this->sync('commerce_checkout.commerce_checkout_flow.default')['configuration']['panes'];
    $this->assertArrayHasKey('aculta_fiscal_identity', $panes);
    $this->assertSame('review', $panes['aculta_fiscal_identity']['step']);
    $this->assertFalse($panes['login']['allow_guest_checkout']);
    $this->assertTrue($panes['login']['allow_registration']);
  }

  /**
   * Verifica que o flow curso duplicado foi removido.
   */
  public function testFlowCursoDuplicadoFoiRemovido(): void {
    $root = dirname(__DIR__, 7);
    $this->assertFileDoesNotExist($root . '/config/sync/commerce_checkout.commerce_checkout_flow.curso.yml');
  }

  /**
   * Verifica que o tipo de pedido curso usa o flow compartilhado.
   */
  public function testCursoOrderTypeUsesSharedFlow(): void {
    $flow = $this->sync('commerce_order.commerce_order_type.curso')['third_party_settings']['commerce_checkout']['checkout_flow'];
    $this->assertSame('default', $flow);
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
