<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Policy\QuotaSignatures;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Assinaturas de cota: texto do transporte sim, cota de caixa de destinatário não.
 */
#[CoversClass(QuotaSignatures::class)]
#[Group('aculta_mailer')]
final class QuotaSignaturesTest extends UnitTestCase {

  /**
   * Texto de limite do transporte é detectado.
   */
  #[DataProvider('transportLimitProvider')]
  public function testTransportLimitTextMatches(string $text): void {
    $this->assertTrue(QuotaSignatures::matches($text));
  }

  /**
   * Textos de limite do transporte.
   */
  public static function transportLimitProvider(): array {
    return [
      'cota diária em inglês' => ['550 5.4.5 Daily sending quota exceeded'],
      'limite de taxa' => ['421 4.7.0 rate limit reached, try later'],
      'HTTP 429' => ['429 Too Many Requests'],
      'SMTP2GO: cota mensal' => ['552 Your monthly email allowance is exhausted'],
      'muitos envios' => ['554 too many messages sent in the last hour'],
      'cota em português' => ['Cota diária de envio excedida no provedor'],
      'limite diário em português' => ['Limite diário de envio atingido'],
    ];
  }

  /**
   * Texto que não é do transporte não é detectado.
   */
  #[DataProvider('notTransportProvider')]
  public function testOtherTextDoesNotMatch(string $text): void {
    $this->assertFalse(QuotaSignatures::matches($text));
  }

  /**
   * Erros que não são do transporte: caixa do destinatário, usuário desconhecido e vazio.
   */
  public static function notTransportProvider(): array {
    return [
      'caixa do destinatário cheia' => ['552 5.2.2 Mailbox quota exceeded for this recipient'],
      'cota de usuário' => ['552 user over quota'],
      'usuário desconhecido' => ['550 5.1.1 user unknown'],
      'vazio' => [''],
    ];
  }

}
