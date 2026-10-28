<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Agenda\CalendarOccurrences;
use Drupal\aculta_calendar\Ics\CalendarIcsExporter;
use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemProviderInterface;
use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exportação .ics a partir da API de itens: série, exceções, cancelado e UID.
 */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class CalendarIcsExportKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Fuso de referência.
   */
  private const FUSO = 'America/Sao_Paulo';

  /**
   * Série com RRULE, EXDATE e RECURRENCE-ID.
   */
  public function testSerieExportadaComExcecoes(): void {
    $fuso = new \DateTimeZone(self::FUSO);
    $item = new CalendarItem(
      id: 'eventdate:7',
      titulo: 'Oficina',
      inicio: new \DateTimeImmutable('2026-10-28 19:00:00', $fuso),
      fim: new \DateTimeImmutable('2026-10-28 21:00:00', $fuso),
      local: 'Sede',
      origem: 'teste',
      rrule: 'FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3',
      excecoes: [new \DateTimeImmutable('2026-11-02 19:00:00', $fuso)],
      reagendadas: [[
        'original' => new \DateTimeImmutable('2026-11-04 19:00:00', $fuso),
        'inicio' => new \DateTimeImmutable('2026-11-05 20:00:00', $fuso),
        'fim' => new \DateTimeImmutable('2026-11-05 22:00:00', $fuso),
      ]],
    );

    $ics = $this->exportador([$item])->exportar($this->consulta('2026-10-01', '2026-11-30'), 'eventos.exemplo.invalid');

    $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3', $ics);
    $this->assertStringContainsString('EXDATE;TZID=America/Sao_Paulo:20261102T190000', $ics);
    $this->assertStringContainsString('RECURRENCE-ID;TZID=America/Sao_Paulo:20261104T190000', $ics);
    $this->assertStringContainsString('SUMMARY:Oficina', $ics);
    $this->assertStringContainsString('LOCATION:Sede', $ics);
    $this->assertStringContainsString('@eventos.exemplo.invalid', $ics);
  }

  /**
   * Item cancelado sai com o prefixo.
   */
  public function testItemCanceladoEMarcado(): void {
    $fuso = new \DateTimeZone(self::FUSO);
    $item = new CalendarItem(
      id: 'eventdate:8',
      titulo: 'Roda',
      inicio: new \DateTimeImmutable('2026-10-28 19:00:00', $fuso),
      fim: new \DateTimeImmutable('2026-10-28 20:00:00', $fuso),
      origem: 'teste',
      cancelado: TRUE,
    );

    $ics = $this->exportador([$item])->exportar($this->consulta('2026-10-01', '2026-10-31'), 'eventos.exemplo.invalid');
    $this->assertStringContainsString('SUMMARY:[Cancelado] Roda', $ics);
  }

  /**
   * Exportação de um item não depende de janela.
   */
  public function testExportaUmItem(): void {
    $fuso = new \DateTimeZone(self::FUSO);
    $item = new CalendarItem(
      id: 'eventdate:9',
      titulo: 'Oficina',
      inicio: new \DateTimeImmutable('2026-10-28 19:00:00', $fuso),
      fim: new \DateTimeImmutable('2026-10-28 21:00:00', $fuso),
      origem: 'teste',
      rrule: 'FREQ=WEEKLY;BYDAY=WE;COUNT=3',
    );

    $ics = $this->exportador([])->exportarItem($item, new \DateTimeImmutable('2026-10-01 00:00:00', $fuso), 'eventos.exemplo.invalid');

    $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=WE;COUNT=3', $ics);
    $this->assertStringContainsString('DTSTART;TZID=America/Sao_Paulo:20261028T190000', $ics);
    $this->assertStringContainsString('@eventos.exemplo.invalid', $ics);
  }

  /**
   * Exportador montado com um provedor de itens fixo.
   */
  private function exportador(array $itens): CalendarIcsExporter {
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

    };
    return new CalendarIcsExporter(new CalendarItemSource([$provedor]), new CalendarOccurrences());
  }

  /**
   * Consulta do período.
   */
  private function consulta(string $de, string $ate): CalendarQuery {
    $fuso = new \DateTimeZone(self::FUSO);
    $inicio = new \DateTimeImmutable($de . ' 00:00:00', $fuso);
    return new CalendarQuery($inicio, new \DateTimeImmutable($ate . ' 23:59:59', $fuso), $inicio);
  }

}
