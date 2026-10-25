<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SmtpReplyClassifier;
use Drupal\aculta_mailer\Delivery\TransportFailover;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Failover Hostinger → SMTP2GO e classificação de resposta SMTP.
 */
#[CoversClass(TransportFailover::class)]
#[CoversClass(SmtpReplyClassifier::class)]
#[Group('aculta_mailer')]
final class TransportFailoverTest extends UnitTestCase {

  /**
   * Erro temporário no padrão troca para o fallback.
   */
  public function testTemporaryFailureOnPrimarySwitchesToFallback(): void {
    $failover = new TransportFailover('hostinger', 'smtp2go');
    $this->assertSame('smtp2go', $failover->nextTransport('hostinger', DeliveryError::Temporary));
  }

  /**
   * Erro permanente nunca troca de transporte.
   */
  public function testPermanentFailureNeverSwitches(): void {
    $failover = new TransportFailover('hostinger', 'smtp2go');
    $this->assertNull($failover->nextTransport('hostinger', DeliveryError::Permanent));
  }

  /**
   * Sem fallback configurado, não há troca.
   */
  public function testNoFallbackMeansNoSwitch(): void {
    $failover = new TransportFailover('hostinger', NULL);
    $this->assertNull($failover->nextTransport('hostinger', DeliveryError::Temporary));
  }

  /**
   * Falha no fallback não volta ao padrão.
   */
  public function testFailureOnFallbackDoesNotLoop(): void {
    $failover = new TransportFailover('hostinger', 'smtp2go');
    $this->assertNull($failover->nextTransport('smtp2go', DeliveryError::Temporary));
  }

  /**
   * Padrão e contingência não podem ser o mesmo transporte.
   */
  public function testRejectsSameTransportForFallback(): void {
    $this->expectException(\InvalidArgumentException::class);
    new TransportFailover('hostinger', 'hostinger');
  }

  /**
   * Respostas 4xx e 5xx são classificadas; ausência de código é temporária.
   */
  #[DataProvider('replyProvider')]
  public function testClassifiesSmtpReplies(?int $code, DeliveryError $expected): void {
    $this->assertSame($expected, (new SmtpReplyClassifier())->classify($code));
  }

  /**
   * Casos de classificação de resposta SMTP.
   *
   * @return array
   *   Mapa de casos nomeados: código SMTP e classe esperada.
   */
  public static function replyProvider(): array {
    return [
      'temporário 421' => [421, DeliveryError::Temporary],
      'temporário 450' => [450, DeliveryError::Temporary],
      'permanente 550' => [550, DeliveryError::Permanent],
      'remetente recusado 553' => [553, DeliveryError::Permanent],
      'sem código' => [NULL, DeliveryError::Temporary],
    ];
  }

}
