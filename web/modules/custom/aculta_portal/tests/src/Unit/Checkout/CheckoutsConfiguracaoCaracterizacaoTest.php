<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Checkout;

use Drupal\aculta_portal\EventSubscriber\DonationFriendlyRouteSubscriber;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;

/**
 * Caracteriza os três checkouts existentes na configuração versionada (P1 do plano de ingressos).
 *
 * Loja, LMS e Doações: tipo de pedido, checkout flow, painéis de envio e rotas. Serve de modelo para o checkout
 * próprio de Eventos (D-38, D-42). Se a configuração ou as rotas públicas mudarem, o teste falha.
 */
#[Group('aculta_portal')]
final class CheckoutsConfiguracaoCaracterizacaoTest extends UnitTestCase {

  /**
   * Raiz do repositório.
   */
  private string $repoRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->repoRoot = dirname(__DIR__, 8);
  }

  /**
   * Lê um arquivo de configuração versionada.
   */
  private function config(string $nome): array {
    $caminho = $this->repoRoot . '/config/sync/' . $nome . '.yml';
    $this->assertFileExists($caminho);
    $valor = Yaml::parseFile($caminho);
    $this->assertIsArray($valor);
    return $valor;
  }

  /**
   * Lê um arquivo de rotas de um módulo.
   */
  private function routing(string $moduloRelativo, string $arquivo): array {
    $caminho = $this->repoRoot . '/web/modules/custom/' . $moduloRelativo . '/' . $arquivo;
    $this->assertFileExists($caminho);
    $valor = Yaml::parseFile($caminho);
    $this->assertIsArray($valor);
    return $valor;
  }

  /**
   * Painéis de envio e cobrança de um flow.
   *
   * @return array<string, mixed>
   */
  private function panes(string $flow): array {
    return $this->config('commerce_checkout.commerce_checkout_flow.' . $flow)['configuration']['panes'] ?? [];
  }

  /**
   * Tipo de pedido → checkout flow declarado no tipo.
   */
  public static function tiposDePedido(): array {
    return [
      'loja (default) não declara flow: o Commerce usa o default' => ['default', NULL],
      'curso usa o flow próprio do LMS (D-43)' => ['curso', 'curso'],
      'doação usa o flow doacao' => ['doacao', 'doacao'],
      'assinatura usa o flow assinatura_flow' => ['assinatura', 'assinatura_flow'],
    ];
  }

  #[DataProvider('tiposDePedido')]
  public function testTipoDePedidoApontaParaOCheckoutFlow(string $tipo, ?string $flowEsperado): void {
    $config = $this->config('commerce_order.commerce_order_type.' . $tipo);
    $flow = $config['third_party_settings']['commerce_checkout']['checkout_flow'] ?? NULL;
    $this->assertSame($flowEsperado, $flow);
  }

  /**
   * Loja: o flow default tem painéis de cobrança e de envio na etapa de informações do pedido.
   */
  public function testFluxoDaLojaTemPaineisDeEnvio(): void {
    $panes = $this->panes('default');

    $this->assertArrayHasKey('aculta_billing_address', $panes);
    $this->assertSame('order_information', $panes['aculta_billing_address']['step']);
    $this->assertArrayHasKey('aculta_shipping_information', $panes);
    $this->assertSame('order_information', $panes['aculta_shipping_information']['step']);
    $this->assertTrue($panes['aculta_shipping_information']['require_shipping_profile']);
    $this->assertArrayHasKey('aculta_fiscal_identity', $panes);
    $this->assertSame('review', $panes['aculta_fiscal_identity']['step']);
  }

  /**
   * Doações: fluxo próprio, sem painéis de envio nem de cobrança postal.
   */
  public function testFluxoDeDoacaoNaoTemPaineisDeEnvio(): void {
    $flow = $this->config('commerce_checkout.commerce_checkout_flow.doacao');
    $panes = $flow['configuration']['panes'];

    $this->assertSame('fluxo_doacao', $flow['plugin']);
    $this->assertArrayNotHasKey('aculta_shipping_information', $panes);
    $this->assertArrayNotHasKey('aculta_billing_address', $panes);
    $this->assertArrayNotHasKey('shipping_information', $panes);
    $this->assertArrayHasKey('commerce_donation_pane', $panes);
    $this->assertSame('donation', $panes['commerce_donation_pane']['step']);
    $this->assertArrayHasKey('payment_process', $panes);
    $this->assertSame('payment', $panes['payment_process']['step']);
  }

  /**
   * Assinatura: mesmo plugin de doação e também sem envio.
   */
  public function testFluxoDeAssinaturaNaoTemPaineisDeEnvio(): void {
    $flow = $this->config('commerce_checkout.commerce_checkout_flow.assinatura_flow');
    $panes = $flow['configuration']['panes'];

    $this->assertSame('fluxo_doacao', $flow['plugin']);
    $this->assertArrayNotHasKey('aculta_shipping_information', $panes);
    $this->assertArrayHasKey('donation_recurring_authorization', $panes);
  }

  /**
   * O fluxo de envio é separado e não é usado por nenhum tipo de pedido.
   */
  public function testFluxoDeEnvioNaoEstaLigadoATipoDePedido(): void {
    $this->assertArrayHasKey('aculta_shipping_information', $this->panes('shipping'));
    foreach (['default', 'curso', 'doacao', 'assinatura'] as $tipo) {
      $config = $this->config('commerce_order.commerce_order_type.' . $tipo);
      $this->assertNotSame('shipping', $config['third_party_settings']['commerce_checkout']['checkout_flow'] ?? NULL);
    }
  }

  /**
   * A configuração da Doação escolhe os tipos e o flow usados no checkout dedicado.
   */
  public function testConfiguracaoDaDoacao(): void {
    $config = $this->config('aculta_donation.settings');

    $this->assertSame('both', $config['donation_route']);
    $this->assertSame('doacao', $config['donate_order_type']);
    $this->assertSame('doacao', $config['donate_checkout_flow']);
    $this->assertNull($config['cart_order_type']);
    $this->assertNull($config['cart_checkout_flow']);
  }

  /**
   * Rotas públicas: o carrinho finaliza em /carrinho/finalizar e a doação em /apoiar/{pedido}/{etapa}.
   */
  public function testRotasPublicasDoCarrinhoEDoCheckoutDeDoacao(): void {
    $portal = $this->routing('aculta_portal', 'aculta_portal.routing.yml');
    $this->assertSame('/carrinho/finalizar', $portal['aculta_portal.cart_checkout']['path']);
    $this->assertSame('TRUE', $portal['aculta_portal.cart_checkout']['requirements']['_access']);

    $donation = $this->routing('aculta_portal/modules/aculta_donation', 'aculta_donation.routing.yml');
    $this->assertSame('/donate/{commerce_order}/{step}', $donation['aculta_donation.donation_controller_formPage']['path']);

    $colecao = new RouteCollection();
    foreach ($donation as $nome => $definicao) {
      $colecao->add($nome, new Route($definicao['path'], $definicao['defaults'] ?? [], $definicao['requirements'] ?? []));
    }
    (new DonationFriendlyRouteSubscriber())->onAlterRoutes(new RouteBuildEvent($colecao));
    $this->assertSame('/apoiar/{commerce_order}/{step}', $colecao->get('aculta_donation.donation_controller_formPage')->getPath());
  }

  /**
   * As rotas globais do Commerce e as do checkout dedicado têm nomes e caminhos distintos.
   */
  public function testRotasGlobaisEDedicadasNaoSeSobrepõem(): void {
    $portal = $this->routing('aculta_portal', 'aculta_portal.routing.yml');

    $caminhos = [
      $portal['aculta_portal.cart_checkout']['path'],
      '/checkout',
      '/checkout/{commerce_order}/{step}',
      '/apoiar/{commerce_order}/{step}',
    ];
    $this->assertSame(count($caminhos), count(array_unique($caminhos)));
  }

}
