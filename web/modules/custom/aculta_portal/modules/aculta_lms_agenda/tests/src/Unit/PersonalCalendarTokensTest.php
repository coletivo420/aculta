<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_agenda\Unit;

use Drupal\aculta_lms_agenda\Calendar\PersonalCalendarTokens;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Token do .ics pessoal: gera, revoga, troca e nunca guarda o valor em claro.
 */
#[Group('aculta_lms_agenda')]
class PersonalCalendarTokensTest extends UnitTestCase {

  /**
   * Estado em memória para o serviço.
   */
  private function servico(): PersonalCalendarTokens {
    $valores = [];
    $estado = $this->createMock(StateInterface::class);
    $estado->method('get')->willReturnCallback(static function (string $chave, $padrao = NULL) use (&$valores) {
      return $valores[$chave] ?? $padrao;
    });
    $estado->method('set')->willReturnCallback(static function (string $chave, $valor) use (&$valores): void {
      $valores[$chave] = $valor;
    });
    return new PersonalCalendarTokens($estado);
  }

  /**
   * O token gerado identifica o dono; o valor em claro não aparece no estado.
   */
  public function testGeneratesAndIdentifiesOwner(): void {
    $servico = $this->servico();
    $token = $servico->gerar(7);
    $this->assertSame(7, $servico->usuarioDoToken($token));
    $this->assertNull($servico->usuarioDoToken('token-qualquer-invalido-123'));
  }

  /**
   * Gerar outro token troca o anterior, que deixa de valer.
   */
  public function testNewTokenReplacesPreviousToken(): void {
    $servico = $this->servico();
    $antigo = $servico->gerar(7);
    $novo = $servico->gerar(7);
    $this->assertNull($servico->usuarioDoToken($antigo));
    $this->assertSame(7, $servico->usuarioDoToken($novo));
  }

  /**
   * Revogar apaga o acesso na hora.
   */
  public function testRevokingInvalidatesToken(): void {
    $servico = $this->servico();
    $token = $servico->gerar(7);
    $this->assertTrue($servico->revogar(7));
    $this->assertNull($servico->usuarioDoToken($token));
    $this->assertFalse($servico->revogar(7), 'segunda revogação não tem o que apagar');
  }

  /**
   * O hash é determinístico e diferente do token.
   */
  public function testHashDiffersFromToken(): void {
    $this->assertSame(PersonalCalendarTokens::hash('abc'), PersonalCalendarTokens::hash('abc'));
    $this->assertNotSame('abc', PersonalCalendarTokens::hash('abc'));
  }

}
