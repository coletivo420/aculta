<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use Drupal\aculta_calendar\Recurrence\RecurrenceException;
use Drupal\aculta_calendar\Recurrence\RecurrenceExpander;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Expansão de série na janela (RECORRENCIA.md, seções 3, 6 e 8).
 *
 * Datas de referência: 28/10/2026 é quarta-feira. Fuso: America/Sao_Paulo.
 */
#[Group('aculta_calendar')]
class RecurrenceExpanderTest extends UnitTestCase {

  /**
   * Expansor sob teste.
   */
  private RecurrenceExpander $expansor;

  /**
   * Fuso do site.
   */
  private \DateTimeZone $fuso;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->expansor = new RecurrenceExpander();
    $this->fuso = new \DateTimeZone(RecurrenceExpander::FUSO);
  }

  /**
   * Data em São Paulo.
   */
  private function data(string $texto): \DateTimeImmutable {
    return new \DateTimeImmutable($texto, $this->fuso);
  }

  /**
   * Lista só as datas de início, no formato dia/mês/ano hora.
   *
   * @param array<int, array{inicio: \DateTimeImmutable}> $ocorrencias
   *   Expanded occurrences whose start dates are formatted.
   *
   * @return string[]
   *   Start dates formatted as day/month/year and time.
   */
  private function inicios(array $ocorrencias): array {
    return array_map(static fn (array $o): string => $o['inicio']->format('d/m/Y H:i'), $ocorrencias);
  }

  /**
   * Janela larga o bastante para as séries de teste.
   */
  private function janela(): array {
    return [$this->data('2026-10-01 00:00'), $this->data('2027-06-30 23:59')];
  }

  /**
   * Limite de série aberta bem depois da janela, para não cortar os testes.
   */
  private function semLimite(): \DateTimeImmutable {
    return $this->data('2030-01-01 00:00');
  }

  /**
   * Diária com intervalo de 3 dias e COUNT=5.
   */
  public function testDiariaComIntervaloEcontagem(): void {
    [$de, $ate] = $this->janela();
    $ocorrencias = $this->expansor->expandir(
      'FREQ=DAILY;INTERVAL=3;COUNT=5',
      $this->data('2026-10-28 19:00'),
      7200,
      $de,
      $ate,
      $this->semLimite(),
    );
    $this->assertSame(
      ['28/10/2026 19:00', '31/10/2026 19:00', '03/11/2026 19:00', '06/11/2026 19:00', '09/11/2026 19:00'],
      $this->inicios($ocorrencias),
    );
  }

  /**
   * Semanal em MO e WE, começando na própria quarta-feira.
   */
  public function testSemanalEmDiasDaSemana(): void {
    [$de, $ate] = $this->janela();
    $ocorrencias = $this->expansor->expandir(
      'FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3',
      $this->data('2026-10-28 19:00'),
      7200,
      $de,
      $ate,
      $this->semLimite(),
    );
    $this->assertSame(
      ['28/10/2026 19:00', '02/11/2026 19:00', '04/11/2026 19:00'],
      $this->inicios($ocorrencias),
    );
  }

  /**
   * Mensal no dia 31: meses com 30 dias são pulados, sem ajuste.
   */
  public function testMensalNoDia31PulaMesesCurtos(): void {
    [$de, $ate] = $this->janela();
    $ocorrencias = $this->expansor->expandir(
      'FREQ=MONTHLY;BYMONTHDAY=31;COUNT=3',
      $this->data('2026-10-31 19:00'),
      7200,
      $de,
      $ate,
      $this->semLimite(),
    );
    $this->assertSame(
      ['31/10/2026 19:00', '31/12/2026 19:00', '31/01/2027 19:00'],
      $this->inicios($ocorrencias),
    );
  }

  /**
   * COUNT inclui a ocorrência cancelada, que continua marcada na saída.
   */
  public function testCancelamentoNaoLiberaContagem(): void {
    [$de, $ate] = $this->janela();
    $cancelada = $this->data('2026-11-02 19:00');
    $ocorrencias = $this->expansor->expandir(
      'FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3',
      $this->data('2026-10-28 19:00'),
      7200,
      $de,
      $ate,
      $this->semLimite(),
      [$cancelada->getTimestamp() => ['inicio' => NULL, 'fim' => NULL]],
    );
    $this->assertCount(3, $ocorrencias);
    $this->assertSame([FALSE, TRUE, FALSE], array_column($ocorrencias, 'cancelada'));
    $this->assertSame('04/11/2026 19:00', $ocorrencias[2]['inicio']->format('d/m/Y H:i'));
  }

  /**
   * Reagendamento usa o início original como chave e altera só essa ocorrência.
   */
  public function testReagendamentoMudaSoAquelaOcorrencia(): void {
    [$de, $ate] = $this->janela();
    $original = $this->data('2026-11-04 19:00');
    $novo = $this->data('2026-11-05 20:00');
    $ocorrencias = $this->expansor->expandir(
      'FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3',
      $this->data('2026-10-28 19:00'),
      7200,
      $de,
      $ate,
      $this->semLimite(),
      [$original->getTimestamp() => ['inicio' => $novo, 'fim' => $novo->modify('+2 hours')]],
    );
    $this->assertSame(
      ['28/10/2026 19:00', '02/11/2026 19:00', '05/11/2026 20:00'],
      $this->inicios($ocorrencias),
    );
    $this->assertSame('04/11/2026 19:00', $ocorrencias[2]['original']->format('d/m/Y H:i'));
    $this->assertFalse($ocorrencias[2]['cancelada']);
  }

  /**
   * Série aberta é cortada pelo limite de horizonte, não pela janela pedida.
   */
  public function testSerieAbertaRespeitaOlimiteDeHorizonte(): void {
    $ocorrencias = $this->expansor->expandir(
      'FREQ=DAILY',
      $this->data('2026-10-28 19:00'),
      3600,
      $this->data('2026-10-01 00:00'),
      $this->data('2027-06-30 23:59'),
      $this->data('2026-10-30 23:59'),
    );
    $this->assertSame(
      ['28/10/2026 19:00', '29/10/2026 19:00', '30/10/2026 19:00'],
      $this->inicios($ocorrencias),
    );
  }

  /**
   * UNTIL é o fim do dia local, então a última ocorrência do dia entra.
   */
  public function testUntilIncluiOultimoDia(): void {
    [$de, $ate] = $this->janela();
    $ocorrencias = $this->expansor->expandir(
      'FREQ=DAILY;UNTIL=20261030',
      $this->data('2026-10-28 19:00'),
      3600,
      $de,
      $ate,
      $this->semLimite(),
    );
    $this->assertSame(
      ['28/10/2026 19:00', '29/10/2026 19:00', '30/10/2026 19:00'],
      $this->inicios($ocorrencias),
    );
  }

  /**
   * Janela com mais de 366 ocorrências é recusada, não truncada (M3).
   */
  public function testTetoDe366OcorrenciasNaJanela(): void {
    $this->expectException(RecurrenceException::class);
    $this->expectExceptionMessage('mais de 366 ocorrências');
    $this->expansor->expandir(
      'FREQ=DAILY',
      $this->data('2026-10-28 19:00'),
      3600,
      $this->data('2026-10-01 00:00'),
      $this->data('2027-11-30 23:59'),
      $this->data('2027-11-30 23:59'),
    );
  }

  /**
   * Janela que cabe exatamente no teto é aceita.
   */
  public function testJanelaNoLimiteEaceita(): void {
    $ocorrencias = $this->expansor->expandir(
      'FREQ=DAILY',
      $this->data('2026-10-28 19:00'),
      3600,
      $this->data('2026-10-28 00:00'),
      $this->data('2027-10-28 23:59'),
      $this->data('2027-10-28 23:59'),
    );
    $this->assertCount(366, $ocorrencias);
  }

  /**
   * Regra inválida chega ao expansor como RecurrenceException.
   */
  public function testRegraInvalidaNaoExpande(): void {
    $this->expectException(RecurrenceException::class);
    $this->expansor->expandir(
      'FREQ=DAILY;BYHOUR=10',
      $this->data('2026-10-28 19:00'),
      3600,
      $this->data('2026-10-01 00:00'),
      $this->data('2026-11-01 00:00'),
      $this->semLimite(),
    );
  }

}
