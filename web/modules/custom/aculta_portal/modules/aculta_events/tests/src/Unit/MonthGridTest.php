<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_events\Agenda\MonthGrid;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Grade do mês: semanas ISO, dias fora do mês vazios, link e contagem por dia.
 */
#[Group('aculta_events')]
final class MonthGridTest extends TestCase {

  /**
   * Fuso de referência dos testes.
   */
  private const FUSO = 'America/Sao_Paulo';

  /**
   * Outubro de 2026 começa numa quinta: a primeira semana começa em 28/09.
   */
  public function testOutubroDe2026TemCincoSemanas(): void {
    $grade = (new MonthGrid())->build(2026, 10, [], $this->hoje('2026-10-15'));

    $this->assertSame('Outubro de 2026', $grade['rotulo']);
    $this->assertCount(5, $grade['semanas']);
    $this->assertCount(7, $grade['semanas'][0]);
    $this->assertNull($grade['semanas'][0][0]['dia'], '28/09 é de outro mês e entra vazio');
    $this->assertSame('2026-10-01', $grade['semanas'][0][3]['dia']->format('Y-m-d'));
    $this->assertSame('2026-10-31', $grade['semanas'][4][5]['dia']->format('Y-m-d'));
    $this->assertNull($grade['semanas'][4][6]['dia'], '01/11 é de outro mês e entra vazio');
  }

  /**
   * Fevereiro de 2027 começa numa segunda e cabe em quatro semanas exatas.
   */
  public function testFevereiroDe2027TemQuatroSemanas(): void {
    $grade = (new MonthGrid())->build(2027, 2, [], $this->hoje('2027-02-01'));

    $this->assertCount(4, $grade['semanas']);
    $this->assertSame('2027-02-01', $grade['semanas'][0][0]['dia']->format('Y-m-d'));
    $this->assertSame('2027-02-28', $grade['semanas'][3][6]['dia']->format('Y-m-d'));
  }

  /**
   * Os meses vizinhos atravessam a virada do ano e o dia de hoje é marcado.
   */
  public function testMesesVizinhosEHoje(): void {
    $grade = (new MonthGrid())->build(2027, 1, [], $this->hoje('2027-01-10'));

    $this->assertSame(['ano' => 2026, 'mes' => 12], $grade['anterior']);
    $this->assertSame(['ano' => 2027, 'mes' => 2], $grade['seguinte']);
    $hoje = array_values(array_filter(
      array_merge(...$grade['semanas']),
      static fn (array $celula): bool => $celula['hoje'],
    ));
    $this->assertCount(1, $hoje);
    $this->assertSame('2027-01-10', $hoje[0]['dia']->format('Y-m-d'));
  }

  /**
   * Itens de dias fora do mês não entram na grade nem na contagem.
   */
  public function testItensSoDoMes(): void {
    $itens = [
      '2026-10-02' => [['titulo' => 'Oficina']],
      '2026-10-02 12:00:00' => [['titulo' => 'Ignorado por chave inválida']],
      '2026-09-30' => [['titulo' => 'Fora do mês']],
    ];
    $grade = (new MonthGrid())->build(2026, 10, $itens, $this->hoje('2026-10-01'));

    $this->assertSame(1, $grade['quantidade']);
    $this->assertSame('Oficina', $grade['semanas'][0][4]['itens'][0]['titulo']);
    $this->assertSame('2026-10-02', $grade['semanas'][0][4]['dia']->format('Y-m-d'));
  }

  /**
   * Data de referência no fuso de Brasília.
   */
  private function hoje(string $dia): \DateTimeImmutable {
    return new \DateTimeImmutable($dia . ' 12:00:00', new \DateTimeZone(self::FUSO));
  }

}
