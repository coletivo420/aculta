<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Delivery\TransportChoice;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Escolha do transporte: esgotado ou desligado é ignorado, inclusive a contingência.
 */
#[CoversClass(TransportChoice::class)]
#[Group('aculta_mailer')]
final class TransportChoiceTest extends UnitTestCase {

  /**
   * Transporte disponível fica como pedido (NULL segue o padrão).
   */
  public function testAvailableRequestIsKept(): void {
    $this->assertNull(TransportChoice::choose(NULL, 'smtp', 'smtp2go', static fn(string $name): bool => TRUE));
    $this->assertSame('brevo', TransportChoice::choose('brevo', 'smtp', 'smtp2go', static fn(string $name): bool => TRUE));
  }

  /**
   * Padrão esgotado: vai para a contingência disponível.
   */
  public function testExhaustedPrimaryUsesAvailableFallback(): void {
    $available = static fn(string $name): bool => $name !== 'smtp';
    $this->assertSame('smtp2go', TransportChoice::choose(NULL, 'smtp', 'smtp2go', $available));
  }

  /**
   * Contingência também esgotada: não troca sem motivo; o pedido original segue e a falha mostra o erro real.
   */
  public function testExhaustedFallbackIsIgnored(): void {
    $available = static fn(string $name): bool => FALSE;
    $this->assertNull(TransportChoice::choose(NULL, 'smtp', 'smtp2go', $available));
    $this->assertSame('smtp2go', TransportChoice::choose('smtp2go', 'smtp', 'smtp2go', $available));
  }

  /**
   * Brevo desligado (indisponível) segue para a contingência.
   */
  public function testUnavailableRequestedTransportUsesFallback(): void {
    $available = static fn(string $name): bool => $name !== 'brevo';
    $this->assertSame('smtp2go', TransportChoice::choose('brevo', 'smtp', 'smtp2go', $available));
  }

}
