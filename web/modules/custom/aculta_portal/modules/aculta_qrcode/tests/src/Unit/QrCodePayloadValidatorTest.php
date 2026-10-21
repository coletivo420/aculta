<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Unit;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodePayloadValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Testa a validação de URLs e textos antes da geração.
 */
#[Group('aculta_qrcode')]
final class QrCodePayloadValidatorTest extends UnitTestCase {

  /**
   * Aceita URLs absolutas http e https e devolve o valor sem espaços.
   */
  public function testAcceptsHttpAndHttpsUrls(): void {
    $validator = new QrCodePayloadValidator();

    self::assertSame('https://example.invalid/qr', $validator->validateUrl('  https://example.invalid/qr '));
    self::assertSame('http://exemplo.test/', $validator->validateUrl('http://exemplo.test/'));
  }

  /**
   * Rejeita esquemas executáveis, credenciais e URLs relativas.
   */
  #[DataProvider('rejectedUrlProvider')]
  public function testRejectsUnsafeOrInvalidUrls(string $url): void {
    $this->expectException(InvalidQrCodeException::class);
    (new QrCodePayloadValidator())->validateUrl($url);
  }

  /**
   * URLs que não podem ser codificadas.
   *
   * @return array<string, string[]>
   *   URLs rejeitadas, por caso.
   */
  public static function rejectedUrlProvider(): array {
    return [
      'javascript' => ['javascript:alert(1)'],
      'data' => ['data:text/html;base64,PGgxPg=='],
      'file' => ['file:///etc/passwd'],
      'credenciais' => ['https://usuario:senha@example.invalid/'],
      'relativa' => ['/pagina/projeto'],
      'vazia' => ['   '],
      'longa demais' => ['https://example.invalid/' . str_repeat('a', 2100)],
    ];
  }

  /**
   * Aceita texto simples dentro do limite.
   */
  public function testAcceptsPlainText(): void {
    self::assertSame('Oficina de abertura', (new QrCodePayloadValidator())->validateText('Oficina de abertura'));
  }

  /**
   * Rejeita texto vazio, longo ou com caractere de controle.
   */
  #[DataProvider('rejectedTextProvider')]
  public function testRejectsInvalidText(string $text): void {
    $this->expectException(InvalidQrCodeException::class);
    (new QrCodePayloadValidator())->validateText($text);
  }

  /**
   * Textos que não podem ser codificados.
   *
   * @return array<string, string[]>
   *   Textos rejeitados, por caso.
   */
  public static function rejectedTextProvider(): array {
    return [
      'vazio' => [''],
      'longo' => [str_repeat('x', 1001)],
      'controle' => ["linha\x07sonora"],
    ];
  }

}
