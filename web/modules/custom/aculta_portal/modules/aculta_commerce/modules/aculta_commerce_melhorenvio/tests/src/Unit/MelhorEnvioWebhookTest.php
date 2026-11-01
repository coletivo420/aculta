<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit;

use Drupal\aculta_commerce_melhorenvio\Webhook\MelhorEnvioWebhookEvent;
use Drupal\aculta_commerce_melhorenvio\Webhook\MelhorEnvioWebhookSignature;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Webhook do Melhor Envio: assinatura HMAC e forma do evento, sem rede.
 */
#[CoversClass(MelhorEnvioWebhookSignature::class)]
#[CoversClass(MelhorEnvioWebhookEvent::class)]
#[Group('aculta_commerce_melhorenvio')]
class MelhorEnvioWebhookTest extends UnitTestCase {

  /**
   * Segredo fictício de teste.
   */
  private const SECRET = 'segredo-fixture-nao-real';

  /**
   * Corpo de um evento válido, conforme a documentação.
   */
  private const BODY = '{"event":"order.posted","data":{"id":"0f1e2d3c-4b5a-4968-8777-665544332211","protocol":"ORD-1","status":"posted"}}';

  /**
   * Assinatura válida em base64 e em hexadecimal.
   */
  public function testValidSignatureInBase64AndHex(): void {
    $signer = new MelhorEnvioWebhookSignature();
    $raw = hash_hmac('sha256', self::BODY, self::SECRET, TRUE);
    $this->assertTrue($signer->isValid(self::BODY, base64_encode($raw), self::SECRET));
    $this->assertTrue($signer->isValid(self::BODY, bin2hex($raw), self::SECRET));
  }

  /**
   * Assinatura adulterada, corpo alterado, segredo errado, ausente ou vazia é recusada.
   *
   * @return array<string, array{string, string, string}>
   *   Casos (corpo, assinatura, segredo).
   */
  public static function invalidSignatureProvider(): array {
    $good = base64_encode(hash_hmac('sha256', self::BODY, self::SECRET, TRUE));
    return [
      'corpo alterado' => [self::BODY . ' ', $good, self::SECRET],
      'segredo errado' => [self::BODY, $good, 'outro-segredo'],
      'assinatura adulterada' => [self::BODY, substr($good, 0, -2) . 'xx', self::SECRET],
      'assinatura vazia' => [self::BODY, '', self::SECRET],
      'segredo vazio' => [self::BODY, $good, ''],
    ];
  }

  /**
   * Requisição sem assinatura confiável não passa.
   */
  #[DataProvider('invalidSignatureProvider')]
  public function testInvalidSignatureIsRejected(string $body, string $signature, string $secret): void {
    $this->assertFalse((new MelhorEnvioWebhookSignature())->isValid($body, $signature, $secret));
  }

  /**
   * Evento válido é interpretado nos campos documentados.
   */
  public function testValidEventIsParsed(): void {
    $event = MelhorEnvioWebhookEvent::fromJson(self::BODY);
    $this->assertSame('order.posted', $event->event);
    $this->assertSame('0f1e2d3c-4b5a-4968-8777-665544332211', $event->labelId);
    $this->assertSame('posted', $event->status);
  }

  /**
   * Corpos fora do formato documentado são recusados.
   *
   * @return array<string, array{string}>
   *   Corpos inválidos.
   */
  public static function invalidBodyProvider(): array {
    return [
      'não é JSON' => ['não é json'],
      'evento desconhecido' => ['{"event":"order.inventado","data":{"id":"0f1e2d3c-4b5a-4968-8777-665544332211","status":"posted"}}'],
      'id não é UUID' => ['{"event":"order.posted","data":{"id":"abc","status":"posted"}}'],
      'sem status' => ['{"event":"order.posted","data":{"id":"0f1e2d3c-4b5a-4968-8777-665544332211"}}'],
      'sem bloco data' => ['{"event":"order.posted"}'],
    ];
  }

  /**
   * Corpo inválido gera exceção de validação, sem processar nada.
   */
  #[DataProvider('invalidBodyProvider')]
  public function testInvalidBodyIsRejected(string $body): void {
    $this->expectException(\InvalidArgumentException::class);
    MelhorEnvioWebhookEvent::fromJson($body);
  }

}
