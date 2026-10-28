<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventDateOverride;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Provedor de itens de evento: série, exceções, filtros e rascunho fora.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventCalendarItemProviderKernelTest extends KernelTestBase {

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
   * Converte horário de Brasília para o valor guardado (UTC, sem sufixo).
   */
  private function utc(string $brasilia): string {
    return (new \DateTimeImmutable($brasilia, new \DateTimeZone('America/Sao_Paulo')))
      ->setTimezone(new \DateTimeZone('UTC'))
      ->format('Y-m-d\TH:i:s');
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
   * Item com local, regra, cancelamento e reagendamento.
   */
  public function testItemComRegraExcecoesELocal(): void {
    $tipo = $this->termo('aculta_event_type', 'Oficina');
    $tipoLocal = $this->termo('aculta_location_type', 'Teatro');

    $evento = Event::create(['title' => 'Oficina', 'field_status' => 'publicado', 'field_tipo' => $tipo]);
    $evento->save();
    $local = EventLocation::create(['title' => 'Sede', 'field_tipo' => $tipoLocal]);
    $local->save();
    $data = EventDate::create([
      'title' => 'Oficina',
      'field_evento' => $evento->id(),
      'field_local' => $local->id(),
      'field_quando' => ['value' => $this->utc('2030-05-10 19:00:00'), 'end_value' => $this->utc('2030-05-10 21:00:00')],
      'field_regra' => 'FREQ=WEEKLY;BYDAY=FR;COUNT=3',
    ]);
    $data->save();
    EventDateOverride::create([
      'field_data_pai' => $data->id(),
      'field_inicio_original' => $this->utc('2030-05-17 19:00:00'),
    ])->save();
    EventDateOverride::create([
      'field_data_pai' => $data->id(),
      'field_inicio_original' => $this->utc('2030-05-24 19:00:00'),
      'field_inicio' => $this->utc('2030-05-25 20:00:00'),
      'field_fim' => $this->utc('2030-05-25 22:00:00'),
    ])->save();

    $itens = iterator_to_array($this->container->get('aculta_events.item_provider')->items($this->query()));
    $this->assertCount(1, $itens);
    $item = $itens[0];
    $this->assertInstanceOf(CalendarItem::class, $item);
    $this->assertSame('eventdate:' . $data->id(), $item->id);
    $this->assertSame('Oficina', $item->titulo);
    $this->assertSame('Sede', $item->local);
    $this->assertSame('FREQ=WEEKLY;BYDAY=FR;COUNT=3', $item->rrule);
    $this->assertCount(1, $item->excecoes);
    $this->assertCount(1, $item->reagendadas);
    $this->assertFalse($item->cancelado);
    $this->assertSame('America/Sao_Paulo', $item->inicio->getTimezone()->getName());
    $this->assertSame('19:00', $item->inicio->format('H:i'));
    $this->assertSame('/eventos/' . $evento->get('field_slug')->value, $item->link);
  }

  /**
   * Filtros por tipo de evento e de local, como na agenda.
   */
  public function testFiltrosDoProvedor(): void {
    $oficina = $this->termo('aculta_event_type', 'Oficina');
    $show = $this->termo('aculta_event_type', 'Show');
    $teatro = $this->termo('aculta_location_type', 'Teatro');
    $praça = $this->termo('aculta_location_type', 'Praça');

    foreach ([['Oficina', $oficina, $teatro], ['Show', $show, $praça]] as [$titulo, $tipo, $tipoLocal]) {
      $evento = Event::create(['title' => $titulo, 'field_status' => 'publicado', 'field_tipo' => $tipo]);
      $evento->save();
      $local = EventLocation::create(['title' => 'Local ' . $titulo, 'field_tipo' => $tipoLocal]);
      $local->save();
      EventDate::create([
        'title' => $titulo,
        'field_evento' => $evento->id(),
        'field_local' => $local->id(),
        'field_quando' => ['value' => $this->utc('2030-06-10 19:00:00'), 'end_value' => $this->utc('2030-06-10 20:00:00')],
      ])->save();
    }

    $provedor = $this->container->get('aculta_events.item_provider');
    $porTipo = iterator_to_array($provedor->items($this->query(['tipo' => $oficina])));
    $this->assertSame(['Oficina'], array_map(static fn (CalendarItem $i): string => $i->titulo, $porTipo));

    $porTipoLocal = iterator_to_array($provedor->items($this->query(['tipo_local' => $teatro])));
    $this->assertSame(['Oficina'], array_map(static fn (CalendarItem $i): string => $i->titulo, $porTipoLocal));

    $this->assertSame([], iterator_to_array($provedor->items($this->query(['tipo' => $show, 'tipo_local' => $teatro]))));

    $filtros = $provedor->filtros();
    $this->assertArrayHasKey('tipo', $filtros);
    $this->assertArrayHasKey('tipo_local', $filtros);
    $this->assertContains('Oficina', $filtros['tipo']['opcoes']);
    $this->assertContains('Teatro', $filtros['tipo_local']['opcoes']);
    $this->assertSame(['aculta_event_date_list', 'aculta_event_list'], $provedor->cacheTags());
  }

  /**
   * Rascunho não entra, mesmo com data no período.
   */
  public function testRascunhoFora(): void {
    $rascunho = Event::create(['title' => 'Rascunho', 'field_status' => 'rascunho']);
    $rascunho->save();
    EventDate::create([
      'title' => 'Rascunho',
      'field_evento' => $rascunho->id(),
      'field_quando' => ['value' => $this->utc('2030-06-10 19:00:00'), 'end_value' => $this->utc('2030-06-10 20:00:00')],
    ])->save();

    $itens = iterator_to_array($this->container->get('aculta_events.item_provider')->items($this->query()));
    $this->assertSame([], $itens);
  }

  /**
   * Consulta de junho de 2030, com os filtros pedidos.
   */
  private function query(array $filtros = []): CalendarQuery {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    return new CalendarQuery(
      new \DateTimeImmutable('2030-05-01 00:00:00', $fuso),
      new \DateTimeImmutable('2030-06-30 23:59:59', $fuso),
      new \DateTimeImmutable('2030-05-01 00:00:00', $fuso),
      $filtros,
    );
  }

}
