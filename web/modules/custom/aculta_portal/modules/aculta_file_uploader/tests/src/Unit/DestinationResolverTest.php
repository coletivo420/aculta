<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_file_uploader\Unit;

use Drupal\aculta_file_uploader\DestinationResolver;
use Drupal\Core\Utility\Token;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifica tokens, sanitização e esquemas do destino de upload.
 */
#[Group('aculta_file_uploader')]
final class DestinationResolverTest extends UnitTestCase {

  /**
   * Cria o resolvedor com um Token que substitui só [date:custom:Y].
   */
  private function resolver(): DestinationResolver {
    $token = $this->createMock(Token::class);
    $token->method('replace')->willReturnCallback(
      static fn(string $text, array $data, array $options): string => str_replace('[date:custom:Y]', '2026', $text),
    );
    return new DestinationResolver($token);
  }

  /**
   * Tokens são substituídos e o esquema public:// é mantido.
   */
  public function testTokensAreReplacedInsidePublicScheme(): void {
    $this->assertSame('public://aculta/2026', $this->resolver()->resolve('public://aculta/[date:custom:Y]'));
  }

  /**
   * Private scheme is also accepted.
   */
  public function testPrivateSchemeIsAccepted(): void {
    $this->assertSame('private://lms/2026', $this->resolver()->resolve('private://lms/[date:custom:Y]'));
  }

  /**
   * Esquema fora da lista é recusado.
   */
  public function testOtherSchemeIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->resolver()->resolve('temporary://upload');
  }

  /**
   * Tentativa de sair do destino com ".." é recusada.
   */
  public function testParentTraversalIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->resolver()->resolve('public://../etc');
  }

  /**
   * Caracteres fora do conjunto seguro viram hífen, por segmento.
   */
  public function testSegmentsAreSanitized(): void {
    $this->assertSame('public://minha-pasta/foto-1', $this->resolver()->resolve('public://minha pasta/foto#1'));
  }

  /**
   * Destino que fica vazio após sanitização é recusado.
   */
  public function testEmptyDestinationIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->resolver()->resolve('public://///');
  }

}
