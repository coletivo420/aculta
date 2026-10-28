<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
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
    Vocabulary::create(['vid' => 'aculta_event_type', 'name' => 'Tipo de evento'])->save();
    Vocabulary::create(['vid' => 'aculta_location_type', 'name' => 'Tipo de local'])->save();
  }

  /**
   * Evento com tipo e, opcionalmente, local de um tipo.
   */
  private function eventoComTipo(string $titulo, string $dia, int $tipo, ?int $tipoLocal): void {
    $evento = Event::create(['title' => $titulo, 'field_status' => 'publicado', 'field_tipo' => $tipo]);
    $evento->save();
    $campos = [
      'title' => $titulo,
      'field_evento' => $evento->id(),
      'field_quando' => ['value' => $this->utc("$dia 10:00:00"), 'end_value' => $this->utc("$dia 12:00:00")],
    ];
    if ($tipoLocal !== NULL) {
      $local = EventLocation::create(['title' => 'Local ' . $titulo, 'field_tipo' => $tipoLocal]);
      $local->save();
      $campos['field_local'] = $local->id();
    }
    EventDate::create($campos)->save();
  }

  /**
   * Cria um termo e devolve o id.
   */
  private function termo(string $vid, string $nome): int {
    $termo = Term::create(['vid' => $vid, 'name' => $nome]);
    $termo->save();
    return (int) $termo->id();
  }

  /**
   * Filtro por tipo de evento e por tipo de local, como no catálogo.
   */
  public function testFiltraPorTipoDeEventoETipoDeLocal(): void {
    $oficina = $this->termo('aculta_event_type', 'Oficina');
    $show = $this->termo('aculta_event_type', 'Show');
    $teatro = $this->termo('aculta_location_type', 'Teatro');
    $praça = $this->termo('aculta_location_type', 'Praça');

    $this->eventoComTipo('Oficina no teatro', '2030-06-10', $oficina, $teatro);
    $this->eventoComTipo('Oficina na praça', '2030-06-10', $oficina, $praça);
    $this->eventoComTipo('Show no teatro', '2030-06-10', $show, $teatro);

    $de = new \DateTimeImmutable('2030-06-01 00:00:00', new \DateTimeZone('America/Sao_Paulo'));
    $ate = new \DateTimeImmutable('2030-06-30 23:59:59', new \DateTimeZone('America/Sao_Paulo'));
    $agenda = $this->container->get('aculta_events.agenda');

    $porTipo = $agenda->dias($de, $ate, $de, $oficina);
    $this->assertSame(['Oficina no teatro', 'Oficina na praça'], array_map(static fn (array $i): string => $i['titulo'], $porTipo['2030-06-10']));

    $porTipoLocal = $agenda->dias($de, $ate, $de, NULL, $teatro);
    $this->assertSame(['Oficina no teatro', 'Show no teatro'], array_map(static fn (array $i): string => $i['titulo'], $porTipoLocal['2030-06-10']));

    $combinado = $agenda->dias($de, $ate, $de, $show, $praça);
    $this->assertSame([], $combinado, 'sem evento que atenda aos dois filtros');
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
