<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Cart;

use Drupal\aculta_portal\Cart\CartConsolidator;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\CheckoutStepResolver;
use Drupal\aculta_portal\Controller\CartCheckoutController;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Caracteriza a rota /carrinho/finalizar (P1 do plano de ingressos).
 *
 * O controller consolida o carrinho e redireciona para o checkout do pedido único no domínio principal; sem
 * carrinho, volta para /carrinho. O teste é Kernel porque o ambiente não fornece SIMPLETEST_BASE_URL para teste
 * Functional.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class CartCheckoutControllerCaracterizacaoKernelTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_cart', 'commerce_checkout', 'domain', 'aculta_portal_cart_test'];

  /**
   * Conta compradora dos carrinhos.
   */
  private User $comprador;

  /**
   * Serviço em teste.
   */
  private CartCheckoutController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');
    $this->installConfig(['commerce_checkout']);
    $this->comprador = User::create([
      'name' => 'comprador',
      'mail' => 'comprador@example.test',
      'status' => 1,
    ]);
    $this->comprador->save();
    $this->setCurrentUser($this->comprador);

    Domain::create([
      'id' => 'aculta_org',
      'hostname' => 'principal.example.invalid',
      'name' => 'Principal',
      'scheme' => 'https',
      'weight' => 0,
      'status' => TRUE,
    ])->save();

    foreach (['default', 'curso', 'doacao'] as $tipo) {
      if (!OrderType::load($tipo)) {
        OrderType::create([
          'id' => $tipo,
          'label' => $tipo,
          'workflow' => 'order_default',
        ])->save();
      }
    }

    $purposeManager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );

    $consolidador = new CartConsolidator(
      $this->container->get('commerce_cart.cart_provider'),
      $this->createMock(CartSessionInterface::class),
      $this->container->get('commerce_store.current_store'),
      $this->container->get('current_user'),
      new CheckoutSettings($this->container->get('config.factory')),
    );

    $this->controller = new CartCheckoutController(
      $consolidador,
      $purposeManager,
      new CheckoutStepResolver($this->container->get('commerce_checkout.chain_checkout_flow_resolver')),
    );
  }

  /**
   * Cria um carrinho do tipo com um item.
   */
  private function carrinho(string $tipo): OrderInterface {
    $pedido = $this->container->get('commerce_cart.cart_provider')
      ->createCart($tipo, $this->store, $this->comprador);
    $item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('10.00', 'USD'),
    ]);
    $item->save();
    $pedido->addItem($item);
    $pedido->save();
    return $pedido;
  }

  /**
   * Sem carrinho, volta para a página do carrinho.
   */
  public function testSemCarrinhoRedirecionaParaOCarrinho(): void {
    $response = $this->controller->finalizar();

    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame('/carrinho', $response->getTargetUrl());
  }

  /**
   * Com carrinhos, consolida e abre o checkout do pedido único no domínio principal.
   */
  public function testComCarrinhoRedirecionaParaOCheckoutConsolidado(): void {
    $loja = $this->carrinho('default');
    $curso = $this->carrinho('curso');

    $response = $this->controller->finalizar();

    $this->assertInstanceOf(TrustedRedirectResponse::class, $response);
    $this->assertSame(302, $response->getStatusCode());
    $url = $response->getTargetUrl();
    $this->assertStringStartsWith('https://principal.example.invalid/checkout/' . $curso->id() . '/', $url);
    // O pedido da Loja foi fundido e apagado.
    $this->assertNull($this->container->get('entity_type.manager')->getStorage('commerce_order')->load((int) $loja->id()));
  }

  /**
   * O destino usa o domínio principal, não o domínio da requisição.
   */
  public function testRedirecionamentoUsaODominioPrincipal(): void {
    $this->carrinho('doacao');

    $response = $this->controller->finalizar();

    $this->assertStringStartsWith('https://principal.example.invalid/', $response->getTargetUrl());
  }

}
