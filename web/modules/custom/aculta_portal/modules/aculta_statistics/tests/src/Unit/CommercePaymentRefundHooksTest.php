<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Hook\CommercePaymentRefundHooks;
use Drupal\commerce_price\Price;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Incremento de reembolso entre versões do pagamento.
 */
#[CoversClass(CommercePaymentRefundHooks::class)]
#[Group('aculta_statistics')]
final class CommercePaymentRefundHooksTest extends TestCase {

  /**
   * Primeiro reembolso conta o valor inteiro em centavos.
   */
  public function testFirstRefundCountsFullAmount(): void {
    $increment = CommercePaymentRefundHooks::refundIncrement(new Price('25.00', 'BRL'), NULL);

    $this->assertSame(['currency' => 'BRL', 'cents' => 2500], $increment);
  }

  /**
   * Reembolso parcial adicional conta só a diferença.
   */
  public function testSubsequentRefundCountsOnlyIncrement(): void {
    $increment = CommercePaymentRefundHooks::refundIncrement(new Price('25.00', 'BRL'), new Price('10.00', 'BRL'));

    $this->assertSame(['currency' => 'BRL', 'cents' => 1500], $increment);
  }

  /**
   * Salvar o mesmo reembolso de novo não conta nada.
   */
  public function testUnchangedRefundIsNotCountedAgain(): void {
    $this->assertNull(CommercePaymentRefundHooks::refundIncrement(new Price('25.00', 'BRL'), new Price('25.00', 'BRL')));
  }

  /**
   * Sem reembolso no pagamento atual, nada é contado.
   */
  public function testNoRefundIsIgnored(): void {
    $this->assertNull(CommercePaymentRefundHooks::refundIncrement(NULL, NULL));
  }

  /**
   * Troca de moeda entre versões não gera incremento (não se somam moedas).
   */
  public function testCurrencyChangeIsIgnored(): void {
    $this->assertNull(CommercePaymentRefundHooks::refundIncrement(new Price('5.00', 'USD'), new Price('2.00', 'BRL')));
  }

}
