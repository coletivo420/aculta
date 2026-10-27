<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit;

use Drupal\aculta_commerce_melhorenvio\Http\OAuthState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Parâmetro `state` do OAuth2: emissão aleatória e conferência estrita no retorno.
 */
#[CoversClass(OAuthState::class)]
#[Group('aculta_commerce_melhorenvio')]
class OAuthStateTest extends UnitTestCase {

  /**
   * O valor emitido tem 64 caracteres hexadecimais e muda a cada emissão.
   */
  public function testIssuedStateIsRandomHex(): void {
    $state = new OAuthState();
    $a = $state->issue();
    $b = $state->issue();
    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a);
    $this->assertNotSame($a, $b);
  }

  /**
   * Só o valor exatamente igual ao emitido é aceito.
   */
  public function testOnlyTheIssuedValueMatches(): void {
    $state = new OAuthState();
    $this->assertTrue($state->matches('abc123', 'abc123'));
    $this->assertFalse($state->matches('abc123', 'abc124'));
    $this->assertFalse($state->matches('abc123', 'abc1234'));
  }

  /**
   * Ausência de valor esperado ou recebido nunca é aceita.
   *
   * @return array<string, array{?string, ?string}>
   *   Combinações sem valor válido.
   */
  public static function missingValueProvider(): array {
    return [
      'sem valor esperado' => [NULL, 'abc'],
      'valor esperado vazio' => ['', ''],
      'sem valor recebido' => ['abc', NULL],
      'valor recebido vazio' => ['abc', ''],
    ];
  }

  /**
   * Retorno sem o valor esperado, ou sem o valor recebido, é recusado.
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('missingValueProvider')]
  public function testMissingValueIsRejected(?string $expected, ?string $received): void {
    $this->assertFalse((new OAuthState())->matches($expected, $received));
  }

}
