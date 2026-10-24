<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MessageIdFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contrato de MailMessage: entrada validada e contexto somente escalar.
 */
#[CoversClass(MailMessage::class)]
#[Group('aculta_mailer')]
final class MailMessageTest extends UnitTestCase {

  private const CORRELATION = '0190a3f2-7c1d-4e8a-9b2f-3d4e5f6a7b8c';

  /**
   * Aceita mensagem válida e preserva o contexto escalar.
   */
  public function testAcceptsValidMessage(): void {
    $context = ['display_name' => 'Maria', 'count' => 2, 'flag' => NULL, 'ratio' => 0.5];
    $message = new MailMessage('account_created', 'pessoa@example.com', 'pt-br', $context, self::CORRELATION);

    $this->assertSame('account_created', $message->type);
    $this->assertSame('pt-br', $message->langcode);
    $this->assertSame($context, $message->context);
    $this->assertFalse($message->hasIdempotentCorrelation());
  }

  /**
   * Accepts UUID v5 correlation IDs used for stable idempotent deliveries.
   */
  public function testAcceptsUuidV5Correlation(): void {
    $id = MessageIdFactory::correlationIdFor('logical-delivery');
    $message = new MailMessage('account_created', 'pessoa@example.com', 'pt-br', [], $id);

    $this->assertSame($id, $message->correlationId);
    $this->assertTrue($message->hasIdempotentCorrelation());
  }

  /**
   * Tipos fora de snake_case são recusados.
   *
   * @return array
   *   Tipos inválidos nomeados.
   */
  public static function invalidTypeProvider(): array {
    return [
      'maiusculas' => ['AccountCreated'],
      'hifen' => ['account-created'],
      'muito curto' => ['ab'],
      'espaço' => ['account created'],
    ];
  }

  /**
   * Rejeita tipo de e-mail inválido.
   */
  #[DataProvider('invalidTypeProvider')]
  public function testRejectsInvalidType(string $type): void {
    $this->expectException(\InvalidArgumentException::class);
    new MailMessage($type, 'pessoa@example.com', 'pt-br', [], self::CORRELATION);
  }

  /**
   * Rejeita destinatário que não é e-mail.
   */
  public function testRejectsInvalidRecipient(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MailMessage('account_created', 'nao-e-email', 'pt-br', [], self::CORRELATION);
  }

  /**
   * Rejeita idioma fora do formato BCP 47 simplificado.
   */
  public function testRejectsInvalidLangcode(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MailMessage('account_created', 'pessoa@example.com', 'portugues', [], self::CORRELATION);
  }

  /**
   * Rejeita correlation ID que não é UUID.
   */
  public function testRejectsNonUuidCorrelation(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MailMessage('account_created', 'pessoa@example.com', 'pt-br', [], 'abc');
  }

  /**
   * Rejeita correlation ID em maiúsculas.
   */
  public function testRejectsUppercaseCorrelation(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MailMessage('account_created', 'pessoa@example.com', 'pt-br', [], strtoupper(self::CORRELATION));
  }

  /**
   * Rejeita objeto no contexto: entidades nunca chegam ao template.
   */
  public function testRejectsObjectInContext(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MailMessage('account_created', 'pessoa@example.com', 'pt-br', ['account' => new \stdClass()], self::CORRELATION);
  }

  /**
   * Aceita linhas escalares aninhadas, como no resumo de avisos.
   */
  public function testAcceptsRowsOfScalars(): void {
    $rows = [['tipo' => 'resposta', 'titulo' => 'Tópico', 'quantidade' => 2]];
    $message = new MailMessage('avisos_resumo', 'pessoa@example.com', 'en', ['linhas' => $rows], self::CORRELATION);
    $this->assertSame($rows, $message->context['linhas']);
  }

  /**
   * Rejeita aninhamento além do segundo nível.
   */
  public function testRejectsDeepNestingInContext(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MailMessage('account_created', 'pessoa@example.com', 'pt-br', ['items' => [['a' => ['b']]]], self::CORRELATION);
  }

  /**
   * Rejeita chave de contexto que não é snake_case.
   */
  public function testRejectsNonSnakeCaseContextKey(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MailMessage('account_created', 'pessoa@example.com', 'pt-br', ['Display-Name' => 'Maria'], self::CORRELATION);
  }

  /**
   * Ida e volta pelo array da fila preserva a mensagem.
   */
  public function testRoundTripThroughArray(): void {
    $message = new MailMessage('account_created', 'pessoa@example.com', 'pt-br', ['name' => 'Maria', 'count' => NULL], self::CORRELATION);
    $copy = MailMessage::fromArray($message->toArray());

    $this->assertEquals($message, $copy);
  }

  /**
   * Dados corrompidos da fila são recusados pela mesma validação.
   */
  public function testFromArrayRejectsCorruptData(): void {
    $this->expectException(\InvalidArgumentException::class);
    MailMessage::fromArray([
      'type' => 'account_created',
      'to' => 'nao-e-email',
      'langcode' => 'pt-br',
      'correlation_id' => self::CORRELATION,
    ]);
  }

}
