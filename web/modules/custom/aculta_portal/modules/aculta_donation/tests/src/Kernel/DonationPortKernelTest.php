<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Kernel;

use Drupal\aculta_donation\Access\DonationRefundAccess;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\MercadoPago\MercadoPagoRecurrenceService;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Port do fluxo de doação: plugins descobertos, criação do pedido de doação e regra de não estorno.
 *
 * O `aculta_donation` depende do `aculta_portal` (pane de assinatura, blocos e rotas), mas este teste não habilita o
 * Portal inteiro: a árvore de dependências dele (group, lms, commerce_mercado_pago, domain, etc.) não é necessária para
 * estes casos. Os três serviços que o pane consome são registrados como sintéticos e recebem instâncias de teste.
 */
#[Group('aculta_donation')]
#[RunTestsInSeparateProcesses]
final class DonationPortKernelTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_cart', 'commerce_checkout', 'commerce_payment', 'aculta_donation'];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    foreach (['aculta_portal.domain_purpose', 'aculta_portal.mercado_pago.logger', 'aculta_portal.mercado_pago.recurrence_service'] as $id) {
      $container->register($id)->setSynthetic(TRUE);
    }
  }

  protected function setUp(): void {
    parent::setUp();
    $this->container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    ));
    $this->container->set('aculta_portal.mercado_pago.logger', $this->createMock(LoggerChannelInterface::class));
    $this->container->set('aculta_portal.mercado_pago.recurrence_service', $this->createMock(MercadoPagoRecurrenceService::class));
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
      $fluxos->getDefinition('fluxo_doacao', FALSE)['class'],
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
