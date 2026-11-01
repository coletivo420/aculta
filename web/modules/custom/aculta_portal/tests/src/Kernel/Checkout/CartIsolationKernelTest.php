<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Checkout;

use Drupal\commerce_order\Entity\OrderType;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Isolamento de carrinhos no Commerce: tipos de pedido e lojas diferentes não se misturam.
 *
 * Cobre o cenário de carrinhos simultâneos (loja, curso e doação) para a mesma conta, e a separação por loja (Domain).
 * O login durante o checkout e as abas do navegador não são simulados aqui: exigem teste funcional.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class CartIsolationKernelTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_cart', 'commerce_checkout'];

  /**
   * Conta do comprador.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->user = User::create(['name' => 'comprador', 'mail' => 'comprador@example.test', 'status' => 1]);
    $this->user->save();
    foreach (['default', 'curso', 'doacao'] as $type) {
      if (!OrderType::load($type)) {
        OrderType::create(['id' => $type, 'label' => $type, 'workflow' => 'order_default'])->save();
      }
    }
  }

  /**
   * Verifica que loja, curso e doação mantêm carrinhos distintos para a mesma conta.
   */
  public function testCarrinhosDeTiposDiferentesCoexistem(): void {
    $provider = $this->container->get('commerce_cart.cart_provider');
    $loja = $provider->createCart('default', $this->store, $this->user);
    $curso = $provider->createCart('curso', $this->store, $this->user);
    $doacao = $provider->createCart('doacao', $this->store, $this->user);

    $this->assertNotSame($loja->id(), $curso->id());
    $this->assertNotSame($curso->id(), $doacao->id());
    $this->assertSame('default', $provider->getCart('default', $this->store, $this->user)->bundle());
    $this->assertSame('curso', $provider->getCart('curso', $this->store, $this->user)->bundle());
    $this->assertSame('doacao', $provider->getCart('doacao', $this->store, $this->user)->bundle());
    $this->assertCount(3, $provider->getCarts($this->user, $this->store));
  }

  /**
   * Verifica que o mesmo tipo de carrinho não é duplicado para a mesma conta e loja.
   */
  public function testMesmoTipoNaoDuplicaCarrinho(): void {
    $provider = $this->container->get('commerce_cart.cart_provider');
    $provider->createCart('curso', $this->store, $this->user);
    $this->expectException(\Exception::class);
    $provider->createCart('curso', $this->store, $this->user);
  }

  /**
   * Verifica que um carrinho de uma loja não aparece na outra loja.
   */
  public function testCarrinhosDeLojasDiferentesSaoSeparados(): void {
    $provider = $this->container->get('commerce_cart.cart_provider');
    $outraLoja = $this->createStore('Outra loja', 'outra@example.test');
    $provider->createCart('default', $this->store, $this->user);

    $this->assertNull($provider->getCart('default', $outraLoja, $this->user));
    $this->assertCount(1, $provider->getCarts($this->user, $this->store));
    $this->assertCount(0, $provider->getCarts($this->user, $outraLoja));
  }

}
