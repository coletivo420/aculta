<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Agenda\CalendarAgenda;
use Drupal\aculta_calendar\Agenda\CalendarOccurrences;
use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemProviderInterface;
use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Agenda a partir da API de itens: agrupamento por dia, fuso e cancelamento.
 */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class CalendarAgendaKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  /**
   * Modules enabled by this Kernel test.
   *
   * @var string[]
   */
  protected static $modules = ['system'];

  /**
   * Fuso de referência.
   */
  private const FUSO = 'America/Sao_Paulo';

  /**
   * Dois itens no mesmo dia ficam juntos e em ordem; cancelado sai marcado.
   */
  public function testAgrupaPorDiaComOrdenacao(): void {
    $itens = [
      $this->item('a', 'Roda', '2030-05-10 10:00', '2030-05-10 12:00'),
      $this->item('b', 'Oficina', '2030-05-10 19:00', '2030-05-10 21:00'),
      $this->item('c', 'Feira cancelada', '2030-05-12 09:00', '2030-05-12 10:00', cancelado: TRUE),
    ];
    $dias = $this->agenda($itens)->dias($this->consulta('2030-05-01', '2030-05-31'));

    $this->assertSame(['2030-05-10', '2030-05-12'], array_keys($dias));
    $this->assertSame(['Roda', 'Oficina'], array_map(static fn (array $i): string => $i['titulo'], $dias['2030-05-10']));
    $this->assertTrue($dias['2030-05-12'][0]['cancelada']);
  }

  /**
   * Série semanal respeita o fuso local, mesmo perto da meia-noite.
   */
  public function testSerieComecandoPertoDaMeiaNoite(): void {
    $serie = $this->item('s', 'Série', '2030-05-01 00:30', '2030-05-01 01:30', rrule: 'FREQ=WEEKLY;BYDAY=WE;COUNT=3');
    $dias = $this->agenda([$serie])->dias($this->consulta('2030-05-01', '2030-05-31'));

    $this->assertSame(['2030-05-01', '2030-05-08', '2030-05-15'], array_keys($dias));
    $this->assertSame('00:30', $dias['2030-05-01'][0]['inicio']->format('H:i'));
  }

  /**
   * Evento que cruza a meia-noite ocupa os dois dias locais.
   */
  public function testFimDaNoiteOcupaOsDoisDiasLocais(): void {
    $item = $this->item('t', 'Tarde', '2030-05-28 23:30', '2030-05-29 00:30');
    $dias = $this->agenda([$item])->dias($this->consulta('2030-05-27', '2030-05-30'));

    $this->assertSame(['2030-05-28', '2030-05-29'], array_keys($dias));
    $this->assertSame('23:30', $dias['2030-05-28'][0]['inicio']->format('H:i'));
  }

  /**
   * Evento em curso iniciado antes da janela aparece em cada dia coberto.
   *
   * Uses the same overlap rule as the providers (start <= ate, end >= de).
   */
  public function testEventoEmCursoIniciadoAntesDaJanelaPermanece(): void {
    $item = $this->item('r', 'Retiro', '2030-05-04 08:00', '2030-05-12 18:00');
    $dias = $this->agenda([$item])->dias($this->consulta('2030-05-10', '2030-05-12'));

    $this->assertSame(['2030-05-10', '2030-05-11', '2030-05-12'], array_keys($dias));
    foreach (['2030-05-10', '2030-05-11', '2030-05-12'] as $dia) {
      $this->assertSame('Retiro', $dias[$dia][0]['titulo']);
    }
  }

  /**
   * Evento de vários dias aparece em todos os dias que cobre.
   */
  public function testEventoDeVariosDiasApareceEmCadaDia(): void {
    $item = $this->item('m', 'Festival', '2030-05-04 08:00', '2030-05-08 18:00');
    $dias = $this->agenda([$item])->dias($this->consulta('2030-05-01', '2030-05-31'));

    $this->assertSame(
      ['2030-05-04', '2030-05-05', '2030-05-06', '2030-05-07', '2030-05-08'],
      array_keys($dias),
    );
  }

  /**
   * Ocorrência cancelada continua na agenda, marcada.
   */
  public function testOcorrenciaCanceladaApareceMarcada(): void {
    $serie = $this->item(
      's',
      'Série',
      '2030-05-01 00:30',
      '2030-05-01 01:30',
      rrule: 'FREQ=WEEKLY;BYDAY=WE;COUNT=3',
      excecoes: [new \DateTimeImmutable('2030-05-08 00:30:00', new \DateTimeZone(self::FUSO))],
    );
    $dias = $this->agenda([$serie])->dias($this->consulta('2030-05-01', '2030-05-31'));

    $this->assertTrue($dias['2030-05-08'][0]['cancelada']);
    $this->assertFalse($dias['2030-05-01'][0]['cancelada']);
  }

  /**
   * Agenda montada com um provedor de itens fixo.
   */
  private function agenda(array $itens): CalendarAgenda {
    $provedor = new class($itens) implements CalendarItemProviderInterface {

      /**
       * Itens devolvidos.
       */
      public function __construct(private readonly array $itens) {}

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return 'teste';
      }

      /**
       * {@inheritdoc}
       */
      public function items(CalendarQuery $query): iterable {
        return $this->itens;
      }

      /**
       * {@inheritdoc}
       */
      public function filtros(): array {
        return [];
      }

      /**
       * {@inheritdoc}
       */
      public function cacheTags(): array {
        return ['teste_list'];
      }

      /** {@inheritdoc} */
      public function cacheContexts(): array {
        return [];
      }

      /** {@inheritdoc} */
      public function cacheMaxAge(): int {
        return 900;
      }

    };
    return new CalendarAgenda(new CalendarItemSource([$provedor]), new CalendarOccurrences());
  }

  /**
   * Item de teste em horário de Brasília.
   */
  private function item(string $id, string $titulo, string $inicio, string $fim, ?string $rrule = NULL, array $excecoes = [], bool $cancelado = FALSE): CalendarItem {
    $fuso = new \DateTimeZone(self::FUSO);
    return new CalendarItem(
      id: $id,
      titulo: $titulo,
      inicio: new \DateTimeImmutable($inicio, $fuso),
      fim: new \DateTimeImmutable($fim, $fuso),
      local: 'Sede',
      origem: 'teste',
      rrule: $rrule,
      excecoes: $excecoes,
      cancelado: $cancelado,
    );
  }

  /**
   * Consulta do período, com a referência no início da janela.
   */
  private function consulta(string $de, string $ate): CalendarQuery {
    $fuso = new \DateTimeZone(self::FUSO);
    $inicio = new \DateTimeImmutable($de . ' 00:00:00', $fuso);
    return new CalendarQuery($inicio, new \DateTimeImmutable($ate . ' 23:59:59', $fuso), $inicio);
  }

}
