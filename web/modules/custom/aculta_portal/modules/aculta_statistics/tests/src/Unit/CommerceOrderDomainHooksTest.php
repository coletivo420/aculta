<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Domain\PurposeResolverInterface;
use Drupal\aculta_statistics\Hook\CommerceOrderDomainHooks;
use Drupal\commerce_order\Entity\OrderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Purpose de origem gravado em pedido novo.
 */
#[CoversClass(CommerceOrderDomainHooks::class)]
#[Group('aculta_statistics')]
final class CommerceOrderDomainHooksTest extends TestCase {

  /**
   * Pedido novo recebe o purpose atual.
   */
  public function testNewOrderGetsCurrentPurpose(): void {
    $order = $this->order(TRUE, NULL);
    $order->expects($this->once())->method('setData')->with(CommerceOrderDomainHooks::DATA_KEY, 'main');

    $this->hooks('main')->presave($order);
  }

  /**
   * Pedido já existente não é alterado.
   */
  public function testExistingOrderIsNotChanged(): void {
    $order = $this->order(FALSE, NULL);
    $order->expects($this->never())->method('setData');

    $this->hooks('main')->presave($order);
  }

  /**
   * Sem purpose conhecido, nada é gravado (o pedido conta como sem_dominio).
   */
  public function testUnknownPurposeIsNotStored(): void {
    $order = $this->order(TRUE, NULL);
    $order->expects($this->never())->method('setData');

    $this->hooks(NULL)->presave($order);
  }

  /**
   * Purpose com caracteres inválidos não é gravado.
   */
  public function testInvalidPurposeIsNotStored(): void {
    $order = $this->order(TRUE, NULL);
    $order->expects($this->never())->method('setData');

    $this->hooks('main; drop')->presave($order);
  }

  /**
   * Cria o hook com um gerenciador de purpose que devolve o valor informado.
   */
  private function hooks(?string $purpose): CommerceOrderDomainHooks {
    $manager = $this->createMock(PurposeResolverInterface::class);
    $manager->method('currentPurpose')->willReturn($purpose);
    return new CommerceOrderDomainHooks($manager);
  }

  /**
   * Pedido de teste com novidade e dado informados.
   */
  private function order(bool $isNew, mixed $data): OrderInterface&\PHPUnit\Framework\MockObject\MockObject {
    $order = $this->createMock(OrderInterface::class);
    $order->method('isNew')->willReturn($isNew);
    $order->method('getData')->willReturn($data);
    return $order;
  }

}
