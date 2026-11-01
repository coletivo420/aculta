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
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\user\Entity\User;
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
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'options', 'link', 'text',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar',
    'aculta_events',
  ];

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
  public function testItemComRegraExcecoesLocal(): void {
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
   * Public calendar items and ICS omit private local names.
   */
  public function testPrivateLocationIsOmittedFromCalendarAndIcsItems(): void {
    $evento = Event::create([
      'title' => 'Evento em local restrito',
      'field_status' => 'publicado',
    ]);
    $evento->save();
    $local = EventLocation::create([
      'title' => 'Endereço reservado',
      'field_status' => 'privado',
    ]);
    $local->save();
    $data = EventDate::create([
      'title' => 'Data reservada',
      'field_evento' => $evento->id(),
      'field_local' => $local->id(),
      'field_quando' => [
        'value' => $this->utc('2030-06-10 19:00:00'),
        'end_value' => $this->utc('2030-06-10 21:00:00'),
      ],
    ]);
    $data->save();

    $provider = $this->container->get('aculta_events.item_provider');
    $items = iterator_to_array($provider->items($this->query()));
    $icsItem = $provider->itemDaData($data, $evento, $local);

    $this->assertCount(1, $items);
    $this->assertSame('', $items[0]->local);
    $this->assertNotNull($icsItem);
    $this->assertSame('', $icsItem->local);
    $this->assertContains('aculta_event_location_list', $provider->cacheTags());
  }

  /**
   * Contextual calendar query returns only dates at a public Local.
   */
  public function testContextualQueryRequiresPublicLocation(): void {
    $locations = [];
    $definitions = [
      ['Sede pública', 'publicado'],
      ['Outra sede', 'publicado'],
      ['Sede privada', 'privado'],
    ];
    foreach ($definitions as [$name, $status]) {
      $location = EventLocation::create(['title' => $name, 'field_status' => $status]);
      $location->save();
      $locations[] = $location;
    }
    $eventLocations = [
      ['Evento A', $locations[0]],
      ['Evento B', $locations[1]],
      ['Evento C', $locations[2]],
    ];
    foreach ($eventLocations as [$title, $location]) {
      $event = Event::create([
        'title' => $title,
        'field_slug' => strtolower(str_replace(' ', '-', $title)),
        'field_status' => 'publicado',
      ]);
      $event->save();
      EventDate::create([
        'title' => $title,
        'field_evento' => $event->id(),
        'field_local' => $location->id(),
        'field_quando' => [
          'value' => $this->utc('2030-06-10 19:00:00'),
          'end_value' => $this->utc('2030-06-10 20:00:00'),
        ],
      ])->save();
    }

    $provider = $this->container->get('aculta_events.item_provider');
    $publicItems = iterator_to_array($provider->items($this->query(['local' => (int) $locations[0]->id()])));
    $privateItems = iterator_to_array($provider->items($this->query(['local' => (int) $locations[2]->id()])));

    $this->assertSame(['Evento A'], array_map(static fn (CalendarItem $item): string => $item->titulo, $publicItems));
    $this->assertSame([], $privateItems);
  }

  /**
   * Contextual local agendas are isolated between owners and anonymous users.
   */
  public function testContextualAgendaIsolatedBetweenLocationOwners(): void {
    // UID 1 bypasses access checks in Drupal and cannot be used as an owner
    // fixture for private content.
    $ownerA = User::create(['uid' => 101, 'name' => 'owner-a', 'status' => 1]);
    $ownerA->save();
    $ownerB = User::create(['uid' => 102, 'name' => 'owner-b', 'status' => 1]);
    $ownerB->save();

    $localA = EventLocation::create([
      'title' => 'Local privado A',
      'field_status' => 'privado',
      'field_organizador' => $ownerA->id(),
    ]);
    $localA->save();
    $localB = EventLocation::create([
      'title' => 'Local privado B',
      'field_status' => 'privado',
      'field_organizador' => $ownerB->id(),
    ]);
    $localB->save();

    foreach ([[$localA, 'Evento A'], [$localB, 'Evento B']] as [$local, $title]) {
      $event = Event::create(['title' => $title, 'field_status' => 'publicado']);
      $event->save();
      EventDate::create([
        'title' => $title,
        'field_evento' => $event->id(),
        'field_local' => $local->id(),
        'field_quando' => [
          'value' => $this->utc('2030-06-10 19:00:00'),
          'end_value' => $this->utc('2030-06-10 20:00:00'),
        ],
      ])->save();
    }

    $provider = $this->container->get('aculta_events.item_provider');
    $this->container->get('current_user')->setAccount($ownerA);
    $ownerAItems = iterator_to_array($provider->items($this->query(['local' => (int) $localA->id()])));
    self::assertFalse($localB->access('view', $ownerA));
    $ownerBItems = iterator_to_array($provider->items($this->query(['local' => (int) $localB->id()])));

    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $anonymousItems = iterator_to_array($provider->items($this->query(['local' => (int) $localA->id()])));

    self::assertSame(['Evento A'], array_map(static fn (CalendarItem $item): string => $item->titulo, $ownerAItems));
    self::assertSame([], $ownerBItems);
    self::assertSame([], $anonymousItems);
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
        'field_quando' => [
          'value' => $this->utc('2030-06-10 19:00:00'),
          'end_value' => $this->utc('2030-06-10 20:00:00'),
        ],
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
    $this->assertSame(
      ['aculta_event_date_list', 'aculta_event_list', 'aculta_event_location_list'],
      $provedor->cacheTags(),
    );
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
