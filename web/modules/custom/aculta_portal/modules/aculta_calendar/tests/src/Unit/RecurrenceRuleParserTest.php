<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use Drupal\aculta_calendar\Recurrence\RecurrenceException;
use Drupal\aculta_calendar\Recurrence\RecurrenceRuleParser;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Validação e normalização da regra da v1 (RECORRENCIA.md, seções 5 e 7).
 */
#[Group('aculta_calendar')]
class RecurrenceRuleParserTest extends UnitTestCase {

  /**
   * Regras válidas, com a forma canônica esperada.
   *
   * @return array<string, array{string, string}>
   */
  public static function validas(): array {
    return [
      'diária com intervalo' => ['FREQ=DAILY;INTERVAL=3;COUNT=5', 'FREQ=DAILY;INTERVAL=3;COUNT=5'],
      'semanal com dias fora de ordem' => ['FREQ=WEEKLY;BYDAY=WE,MO;COUNT=3', 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE;COUNT=3'],
      'mensal por dia do mês' => ['FREQ=MONTHLY;BYMONTHDAY=31;COUNT=3', 'FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=31;COUNT=3'],
      'minúsculas são aceitas' => ['freq=weekly;byday=mo;count=2', 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO;COUNT=2'],
      'série aberta, sem fim' => ['FREQ=DAILY', 'FREQ=DAILY;INTERVAL=1'],
      'com UNTIL' => ['FREQ=DAILY;UNTIL=20261030', 'FREQ=DAILY;INTERVAL=1;UNTIL=20261030'],
      'intervalo máximo' => ['FREQ=DAILY;INTERVAL=99;COUNT=1', 'FREQ=DAILY;INTERVAL=99;COUNT=1'],
    ];
  }

  /**
   * Regras que devem ser recusadas, com o motivo esperado na mensagem.
   *
   * @return array<string, array{string, string}>
   */
  public static function invalidas(): array {
    return [
      'vazia' => ['', 'regra vazia'],
      'sem FREQ' => ['COUNT=3', 'FREQ deve ser'],
      'FREQ anual fora da v1' => ['FREQ=YEARLY;COUNT=3', 'FREQ deve ser'],
      'parte desconhecida' => ['FREQ=DAILY;BYHOUR=10;COUNT=3', 'parte desconhecida: BYHOUR'],
      'parte repetida' => ['FREQ=DAILY;FREQ=WEEKLY;COUNT=3', 'parte repetida: FREQ'],
      'parte sem valor' => ['FREQ=DAILY;COUNT', "parte sem valor: 'COUNT'"],
      'BYDAY com DAILY' => ['FREQ=DAILY;BYDAY=MO;COUNT=3', 'BYDAY só é aceito com FREQ=WEEKLY'],
      'BYDAY com prefixo numérico' => ['FREQ=MONTHLY;BYDAY=1MO;COUNT=3', 'BYDAY só é aceito com FREQ=WEEKLY'],
      'BYDAY inválido' => ['FREQ=WEEKLY;BYDAY=XX;COUNT=3', "BYDAY inválido: 'XX'"],
      'BYMONTHDAY com WEEKLY' => ['FREQ=WEEKLY;BYMONTHDAY=3;COUNT=3', 'BYMONTHDAY só é aceito com FREQ=MONTHLY'],
      'BYMONTHDAY 32' => ['FREQ=MONTHLY;BYMONTHDAY=32;COUNT=3', 'BYMONTHDAY fora do intervalo 1 a 31: 32'],
      'INTERVAL zero' => ['FREQ=DAILY;INTERVAL=0;COUNT=3', 'INTERVAL fora do intervalo 1 a 99: 0'],
      'INTERVAL não numérico' => ['FREQ=DAILY;INTERVAL=x;COUNT=3', 'INTERVAL deve ser inteiro'],
      'COUNT zero' => ['FREQ=DAILY;COUNT=0', 'COUNT fora do intervalo 1 a 366: 0'],
      'COUNT acima do teto' => ['FREQ=DAILY;COUNT=367', 'COUNT fora do intervalo 1 a 366: 367'],
      'COUNT e UNTIL juntos' => ['FREQ=DAILY;COUNT=3;UNTIL=20261030', 'COUNT e UNTIL não podem vir juntos'],
      'UNTIL data inexistente' => ['FREQ=DAILY;UNTIL=20261340', 'UNTIL deve ser uma data AAAAMMDD válida'],
      'UNTIL em formato errado' => ['FREQ=DAILY;UNTIL=2026-10-30', 'UNTIL deve ser uma data AAAAMMDD válida'],
    ];
  }

  /**
   * Regra válida é aceita e normalizada.
   */
  #[DataProvider('validas')]
  public function testAceitaERetornaFormaCanonica(string $regra, string $esperada): void {
    $this->assertSame($esperada, (new RecurrenceRuleParser())->normalize($regra));
  }

  /**
   * Regra inválida é recusada com a mensagem do motivo.
   */
  #[DataProvider('invalidas')]
  public function testRecusaComMotivo(string $regra, string $motivo): void {
    $this->expectException(RecurrenceException::class);
    $this->expectExceptionMessage($motivo);
    (new RecurrenceRuleParser())->parse($regra);
  }

  /**
   * O teto de 366 é a constante usada no expansor (M3).
   */
  public function testTetoDeOcorrencias(): void {
    $this->assertSame(366, RecurrenceRuleParser::MAX_OCCURRENCES);
  }

}
