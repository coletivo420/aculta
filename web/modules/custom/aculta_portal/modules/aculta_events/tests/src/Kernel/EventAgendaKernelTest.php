<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Agenda do período: agrupada por dia, cancelado marcado, rascunho fora.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventAgendaKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'key', 'address', 'geofield', 'options', 'text', 'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar', 'aculta_events'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'taxonomy']);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_override');
  }

  /**
   * Horário de Brasília convertido para o valor guardado (UTC).
   */
  private function utc(string $brasilia): string {
    return (new \DateTimeImmutable($brasilia, new \DateTimeZone('America/Sao_Paulo')))
      ->setTimezone(new \DateTimeZone('UTC'))
      ->format('Y-m-d\TH:i:s');
  }

  /**
   * Evento com uma data em horário fixo.
   */
  private function evento(string $titulo, string $situacao, string $dia, string $hora): void {
    $evento = Event::create(['title' => $titulo, 'field_status' => $situacao]);
    $evento->save();
    EventDate::create([
      'title' => $titulo,
      'field_evento' => $evento->id(),
      'field_quando' => [
        'value' => $this->utc("$dia $hora:00"),
        'end_value' => $this->utc("$dia " . sprintf('%02d', (int) substr($hora, 0, 2) + 2) . ':00:00'),
      ],
    ])->save();
  }

  /**
   * Dois eventos no mesmo dia ficam juntos e em ordem; cancelado sai marcado; rascunho não sai.
   */
  public function testAgrupaPorDiaESemRascunho(): void {
    $this->evento('Oficina', 'publicado', '2030-05-10', '19:00');
    $this->evento('Roda', 'publicado', '2030-05-10', '10:00');
    $this->evento('Feira cancelada', 'cancelado', '2030-05-12', '09:00');
    $this->evento('Rascunho oculto', 'rascunho', '2030-05-12', '14:00');

    $de = new \DateTimeImmutable('2030-05-01 00:00:00', new \DateTimeZone('America/Sao_Paulo'));
    $ate = new \DateTimeImmutable('2030-05-31 23:59:59', new \DateTimeZone('America/Sao_Paulo'));
    $dias = $this->container->get('aculta_events.agenda')->dias($de, $ate, $de);

    $this->assertSame(['2030-05-10', '2030-05-12'], array_keys($dias));
    $this->assertSame(['Roda', 'Oficina'], array_map(static fn (array $i): string => $i['titulo'], $dias['2030-05-10']));
    $this->assertTrue($dias['2030-05-12'][0]['cancelada']);
    $this->assertCount(1, $dias['2030-05-12'], 'rascunho não entra');
  }

}
