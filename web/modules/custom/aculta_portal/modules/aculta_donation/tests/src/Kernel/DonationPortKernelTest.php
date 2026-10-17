<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Kernel;

use Drupal\aculta_donation\Access\DonationRefundAccess;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Port do fluxo de doação: plugins descobertos, criação do pedido de doação e regra de não estorno.
 */
#[Group('aculta_donation')]
#[RunTestsInSeparateProcesses]
final class DonationPortKernelTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_cart', 'commerce_checkout', 'commerce_payment', 'aculta_donation'];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['aculta_donation']);
    $this->container->get('commerce_price.currency_importer')->import('BRL');
    if (!OrderItemType::load('donation')) {
      OrderItemType::create(['id' => 'donation', 'label' => 'Doação', 'orderType' => 'default'])->save();
    }
  }

  public function testPluginsDoFluxoEstaoDescobertos(): void {
    $fluxos = $this->container->get('plugin.manager.commerce_checkout_flow');
    $this->assertSame(
      'Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow\DonationCheckoutFlow',
      $fluxos->getDefinition('donation_checkout_flow', FALSE)['class'],
    );
    $paineis = $this->container->get('plugin.manager.commerce_checkout_pane');
    foreach (['commerce_donation_pane', 'commerce_memorial_pane', 'commerce_donation_add_pane', 'donation_thank_you'] as $id) {
      $this->assertStringStartsWith('Drupal\aculta_donation\\', $paineis->getDefinition($id, FALSE)['class']);
    }
    $this->assertStringStartsWith(
      'Drupal\aculta_donation\\',
      $this->container->get('plugin.manager.field.widget')->getDefinition('commerce_donation_flow_level_widget', FALSE)['class'],
    );
  }

  public function testBlocosDeDoacaoEstaoDescobertos(): void {
    $blocos = $this->container->get('plugin.manager.block');
    foreach (['aculta_donation_donate', 'aculta_donation_quick', 'aculta_donation_back'] as $id) {
      $this->assertStringStartsWith('Drupal\aculta_donation\\', $blocos->getDefinition($id, FALSE)['class']);
    }
  }

  public function testNovoPedidoTemItemDeDoacaoComValorZero(): void {
    $pedido = $this->container->get('aculta_donation.new_order')->get('single', 'donation');
    $pedido->save();
    $this->assertTrue(DonationRefundAccess::pedidoTemDoacao($pedido));
    $item = $pedido->getItems()[0];
    $this->assertSame('donation', $item->bundle());
    $this->assertSame(0.0, (float) $item->getUnitPrice()->getNumber());
    $this->assertSame('single', $item->get('field_gift_type')->value);
  }

  public function testEstornoDeDoacaoEhNegadoEOutrasOperacoesSeguem(): void {
    $pedido = $this->container->get('aculta_donation.new_order')->get('single', 'donation');
    $pedido->save();
    $rota = $this->container->get('router.route_provider')->getRouteByName('entity.commerce_payment.operation_form');
    $servico = $this->container->get('aculta_donation.refund_access');
    $usuario = $this->container->get('current_user');

    $estorno = new RouteMatch('entity.commerce_payment.operation_form', $rota,
      ['commerce_order' => $pedido, 'operation' => DonationRefundAccess::OPERACAO_ESTORNO],
      ['commerce_order' => $pedido, 'operation' => DonationRefundAccess::OPERACAO_ESTORNO]);
    $this->assertFalse($servico->access($estorno, $usuario)->isAllowed());

    $outra = new RouteMatch('entity.commerce_payment.operation_form', $rota,
      ['commerce_order' => $pedido, 'operation' => 'void-payment'],
      ['commerce_order' => $pedido, 'operation' => 'void-payment']);
    $this->assertTrue($servico->access($outra, $usuario)->isAllowed());
  }

}
