<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\EventOrganizationPageController;
use Drupal\aculta_events\Controller\EventPageController;
use Drupal\aculta_events\Controller\LocationPageController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventDateOverride;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Valida campos aditivos estruturados de eventos e datas (ARQ-04).
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventStructuredDataKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'options', 'link', 'text',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'ui_patterns',
    'ui_patterns_views', 'aculta_calendar',
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
    $this->installEntitySchema('aculta_event_organization');
    $this->installEntitySchema('aculta_event_override');
  }

  /**
   * Testa persistência e leitura dos campos estruturados de evento e data.
   */
  public function testStructuredFieldsPersistence(): void {
    $evento = Event::create([
      'title' => 'Debate Cultural',
      'field_status' => 'publicado',
      'field_classificacao_etaria' => '14',
      'field_acessibilidade' => 'Tradução em Libras disponível e rampa de acesso.',
      'field_seguranca' => 'Entrada mediante documento com foto; proibido objetos cortantes.',
    ]);
    $evento->save();

    $this->assertSame('14', $evento->get('field_classificacao_etaria')->value);
    $this->assertSame('Tradução em Libras disponível e rampa de acesso.', $evento->get('field_acessibilidade')->value);
    $this->assertSame('Entrada mediante documento com foto; proibido objetos cortantes.', $evento->get('field_seguranca')->value);

    $data = EventDate::create([
      'title' => 'Sessão 1',
      'field_evento' => $evento->id(),
      'field_quando' => [
        'value' => '2026-11-10T19:00:00',
        'end_value' => '2026-11-10T21:00:00',
      ],
      'field_modo' => 'hibrido',
      'field_link_virtual' => 'https://transmissao.example.invalid/debate',
      'field_abertura_portas' => '18:30 (30 minutos antes)',
    ]);
    $data->save();

    $this->assertSame('hibrido', $data->get('field_modo')->value);
    $this->assertSame('https://transmissao.example.invalid/debate', $data->get('field_link_virtual')->uri);
    $this->assertSame('18:30 (30 minutos antes)', $data->get('field_abertura_portas')->value);
  }

  /**
   * Public event pages hide private local data and vary cache by account.
   */
  public function testPrivateLocationIsHiddenFromPublicEventPresentation(): void {
    User::create(['name' => 'root-fixture', 'status' => 1])->save();
    $owner = User::create(['name' => 'local-owner', 'status' => 1]);
    $owner->save();
    $event = Event::create([
      'title' => 'Evento híbrido público',
      'field_slug' => 'evento-hibrido-publico',
      'field_status' => 'publicado',
    ]);
    $event->save();
    $location = EventLocation::create([
      'title' => 'Local de endereço reservado',
      'field_status' => 'privado',
      'field_precisao' => 'exato',
      'field_organizador' => $owner->id(),
      'field_endereco' => [
        'country_code' => 'BR',
        'address_line1' => 'Rua Reservada, 987',
        'locality' => 'Cidade Segura',
        'administrative_area' => 'PR',
        'postal_code' => '80000-987',
      ],
    ]);
    $location->save();
    $now = $this->container->get('datetime.time')->getCurrentTime();
    EventDate::create([
      'title' => 'Sessão híbrida',
      'field_evento' => $event->id(),
      'field_local' => $location->id(),
      'field_quando' => [
        'value' => gmdate('Y-m-d\\TH:i:s', $now + 86400),
        'end_value' => gmdate('Y-m-d\\TH:i:s', $now + 90000),
      ],
      'field_modo' => 'hibrido',
      'field_link_virtual' => 'https://stream.example.test/evento',
    ])->save();

    $controller = EventPageController::create($this->container);
    $publicPage = $controller->page('evento-hibrido-publico');
    $publicMarkup = (string) $publicPage['#slots']['essentials']['dates']['items']['date_0']['#markup'];
    $publicJson = $this->extractJsonLd($publicPage['#attached']['html_head'], 'aculta_events_event_jsonld');
    $publicLocation = $publicJson['@graph'][0]['location'] ?? NULL;

    $this->assertStringNotContainsString('Local de endereço reservado', $publicMarkup);
    $this->assertStringNotContainsString('Rua Reservada', json_encode($publicJson));
    $this->assertStringNotContainsString('80000-987', json_encode($publicJson));
    $this->assertSame('VirtualLocation', $publicLocation['@type'] ?? NULL);
    $this->assertContains('user', $publicPage['#cache']['contexts']);

    $this->container->get('current_user')->setAccount($owner);
    $ownerPage = $controller->page('evento-hibrido-publico');
    $ownerJson = $this->extractJsonLd($ownerPage['#attached']['html_head'], 'aculta_events_event_jsonld');
    $ownerLocations = $ownerJson['@graph'][0]['location'] ?? [];

    $ownerMarkup = (string) $ownerPage['#slots']['essentials']['dates']['items']['date_0']['#markup'];
    $this->assertStringContainsString('Local de endereço reservado', $ownerMarkup);
    $this->assertCount(2, $ownerLocations);
    $this->assertSame('Rua Reservada, 987', $ownerLocations[1]['address']['streetAddress'] ?? NULL);
    $this->assertSame('80000-987', $ownerLocations[1]['address']['postalCode'] ?? NULL);
    $this->assertContains('user', $ownerPage['#cache']['contexts']);
  }

  /**
   * Local agenda expands dates and exceptions through the shared Calendar API.
   */
  public function testLocationAgendaUsesCalendarRecurrenceAndLocalFilter(): void {
    $timezone = new \DateTimeZone('America/Sao_Paulo');
    $utc = new \DateTimeZone('UTC');
    $now = (new \DateTimeImmutable('@' . $this->container->get('datetime.time')->getCurrentTime()))->setTimezone($timezone);
    $first = $now->setTime(18, 0)->modify('+2 days');
    $second = $first->modify('+1 day');
    $third = $first->modify('+2 days');
    $rescheduled = $first->modify('+4 days');
    $asUtc = static fn (\DateTimeImmutable $date): string => $date->setTimezone($utc)->format('Y-m-d\\TH:i:s');

    $owner = User::create(['name' => 'location-owner', 'status' => 1]);
    $owner->save();
    $location = EventLocation::create([
      'title' => 'Local da agenda',
      'field_status' => 'privado',
      'field_organizador' => $owner->id(),
    ]);
    $location->save();
    $otherLocation = EventLocation::create(['title' => 'Outro local', 'field_status' => 'publicado']);
    $otherLocation->save();
    $event = Event::create([
      'title' => 'Evento da agenda',
      'field_slug' => 'evento-da-agenda',
      'field_status' => 'publicado',
    ]);
    $event->save();
    $date = EventDate::create([
      'title' => 'Série recorrente',
      'field_evento' => $event->id(),
      'field_local' => $location->id(),
      'field_quando' => [
        'value' => $asUtc($first),
        'end_value' => $asUtc($first->modify('+2 hours')),
      ],
      'field_regra' => 'FREQ=DAILY;COUNT=3',
    ]);
    $date->save();
    EventDateOverride::create([
      'field_data_pai' => $date->id(),
      'field_inicio_original' => $asUtc($second),
    ])->save();
    EventDateOverride::create([
      'field_data_pai' => $date->id(),
      'field_inicio_original' => $asUtc($third),
      'field_inicio' => $asUtc($rescheduled),
      'field_fim' => $asUtc($rescheduled->modify('+2 hours')),
    ])->save();

    $otherEvent = Event::create([
      'title' => 'Evento de outro local',
      'field_slug' => 'evento-de-outro-local',
      'field_status' => 'publicado',
    ]);
    $otherEvent->save();
    EventDate::create([
      'title' => 'Outra data',
      'field_evento' => $otherEvent->id(),
      'field_local' => $otherLocation->id(),
      'field_quando' => [
        'value' => $asUtc($first),
        'end_value' => $asUtc($first->modify('+2 hours')),
      ],
    ])->save();

    $this->container->get('current_user')->setAccount($owner);
    $build = LocationPageController::create($this->container)->page($location);
    $days = $build['agenda']['days'];
    $this->assertSame('aculta_events.location_ics', $build['agenda']['ics']['#url']->getRouteName());
    $this->assertSame((string) $location->id(), (string) $build['agenda']['ics']['#url']->getRouteParameters()['aculta_event_location']);
    $firstDay = $first->format('Y-m-d');
    $cancelledDay = $second->format('Y-m-d');
    $originalThirdDay = $third->format('Y-m-d');
    $rescheduledDay = $rescheduled->format('Y-m-d');
    $markup = '';
    foreach ($days as $day) {
      foreach ($day['items']['#items'] as $item) {
        $markup .= (string) $item['#markup'];
      }
    }

    $this->assertSame([$firstDay, $cancelledDay, $rescheduledDay], array_keys($days));
    $this->assertArrayNotHasKey($originalThirdDay, $days);
    $this->assertStringContainsString('/eventos/evento-da-agenda', $markup);
    $this->assertStringContainsString('cancelado', $markup);
    $this->assertStringNotContainsString('Evento de outro local', $markup);
    $this->assertContains('aculta_event_date_list', $build['agenda']['#cache']['tags']);
    $this->assertContains('user', $build['agenda']['#cache']['contexts']);

    $outsider = User::create(['name' => 'unrelated-viewer', 'status' => 1]);
    $outsider->save();
    $this->container->get('current_user')->setAccount($outsider);
    $denied = FALSE;
    try {
      LocationPageController::create($this->container)->page($location);
    }
    catch (NotFoundHttpException) {
      $denied = TRUE;
    }
    $this->assertTrue($denied);
  }

  /**
   * Valida a idempotência de aculta_events_update_10005.
   */
  public function testUpdate10005Idempotence(): void {
    \Drupal::moduleHandler()->loadInclude('aculta_events', 'install');
    $resultado = aculta_events_update_10005();
    // Em execução subsequente, informa que já existiam sem lançar exceção.
    $resultado2 = aculta_events_update_10005();
    $this->assertSame($resultado, $resultado2);
    $this->assertSame('Campos estruturados de ARQ-04 já existiam.', $resultado2);
  }

  /**
   * Valida saída JSON-LD de Event, Place e Organization (I-12 / LOC-05).
   */
  public function testStructuredDataJsonLdOutput(): void {
    // 1. Organização cultural com endereço e links oficiais.
    $org = EventOrganization::create([
      'title' => 'Instituto Cultural ACULTA',
      'field_slug' => 'instituto-cultural-aculta',
      'body' => 'Entidade dedicada ao desenvolvimento e promoção da cultura.',
      'localidade' => 'Curitiba',
      'uf' => 'PR',
      'links' => "https://aculta.example.invalid\nhttps://instagram.com/aculta",
      'reivindicada' => TRUE,
      'reivindicacao_status' => 'confirmada',
    ]);
    $org->save();

    // 2. Local de evento com endereço e coordenadas.
    $local = EventLocation::create([
      'title' => 'Espaço Cultural das Flores',
      'field_descricao' => 'Centro de eventos multidisciplinar no centro histórico.',
      'field_telefone' => '(41) 3333-4444',
      'field_site' => 'https://espacodasflores.art.br',
      'field_horario' => 'Segunda a sexta das 9h às 18h',
      'field_endereco' => [
        'country_code' => 'BR',
        'locality' => 'Curitiba',
        'administrative_area' => 'PR',
        'postal_code' => '80010-000',
        'address_line1' => 'Rua das Flores, 100',
      ],
      'field_precisao' => 'exato',
    ]);
    $local->save();

    // 3. Evento com modalidade híbrida, oferta gratuita e organização.
    $evento = Event::create([
      'title' => 'Mostra de Cinema Independente',
      'field_slug' => 'mostra-cinema-independente',
      'body' => 'Exibição de curtas e longas metragens nacionais.',
      'field_status' => 'publicado',
      'field_organizacao' => (int) $org->id(),
    ]);
    $evento->save();

    $data = EventDate::create([
      'title' => 'Sessão de Abertura',
      'field_evento' => (int) $evento->id(),
      'field_local' => (int) $local->id(),
      'field_quando' => [
        'value' => '2026-11-15T19:00:00',
        'end_value' => '2026-11-15T21:30:00',
      ],
      'field_modo' => 'hibrido',
      'field_link_virtual' => 'https://aovivo.example.invalid/cinema',
      'field_gratuito' => TRUE,
    ]);
    $data->save();

    // Validação da página do Evento:
    $eventController = EventPageController::create($this->container);
    $eventBuild = $eventController->page('mostra-cinema-independente');
    $this->assertArrayHasKey('#attached', $eventBuild);
    $this->assertNotEmpty($eventBuild['#attached']['html_head']);

    $eventJson = $this->extractJsonLd($eventBuild['#attached']['html_head'], 'aculta_events_event_jsonld');
    $this->assertNotNull($eventJson);
    $this->assertSame('https://schema.org', $eventJson['@context'] ?? NULL);
    $this->assertNotEmpty($eventJson['@graph']);
    $eventGraphItem = $eventJson['@graph'][0];
    $this->assertSame('Event', $eventGraphItem['@type']);
    $this->assertSame('Mostra de Cinema Independente', $eventGraphItem['name']);
    $this->assertSame('https://schema.org/MixedEventAttendanceMode', $eventGraphItem['eventAttendanceMode']);
    $this->assertSame('https://schema.org/EventScheduled', $eventGraphItem['eventStatus']);

    // Valida location composto (VirtualLocation + Place):
    $this->assertIsArray($eventGraphItem['location']);
    $this->assertCount(2, $eventGraphItem['location']);
    $this->assertSame('VirtualLocation', $eventGraphItem['location'][0]['@type']);
    $this->assertSame('https://aovivo.example.invalid/cinema', $eventGraphItem['location'][0]['url']);
    $this->assertSame('Place', $eventGraphItem['location'][1]['@type']);
    $this->assertSame('Espaço Cultural das Flores', $eventGraphItem['location'][1]['name']);
    $this->assertSame('Curitiba', $eventGraphItem['location'][1]['address']['addressLocality']);

    // Valida organizer:
    $this->assertSame('Organization', $eventGraphItem['organizer']['@type']);
    $this->assertSame('Instituto Cultural ACULTA', $eventGraphItem['organizer']['name']);

    // Valida offers:
    $this->assertSame('Offer', $eventGraphItem['offers']['@type']);
    $this->assertSame('0', $eventGraphItem['offers']['price']);
    $this->assertSame('BRL', $eventGraphItem['offers']['priceCurrency']);

    // Validação da página do Local:
    $locationController = LocationPageController::create($this->container);
    $locationBuild = $locationController->page($local);
    $locationJson = $this->extractJsonLd($locationBuild['#attached']['html_head'], 'aculta_events_location_jsonld');
    $this->assertNotNull($locationJson);
    $this->assertSame('https://schema.org', $locationJson['@context']);
    $this->assertSame('Place', $locationJson['@type']);
    $this->assertSame('Espaço Cultural das Flores', $locationJson['name']);
    $this->assertSame('(41) 3333-4444', $locationJson['telephone']);
    $this->assertSame('https://espacodasflores.art.br', $locationJson['sameAs']);
    $this->assertSame('BR', $locationJson['address']['addressCountry']);
    $this->assertSame('Curitiba', $locationJson['address']['addressLocality']);
    $this->assertSame('PR', $locationJson['address']['addressRegion']);
    $this->assertSame('Rua das Flores, 100', $locationJson['address']['streetAddress']);

    // Validação da página da Organização:
    $orgController = EventOrganizationPageController::create($this->container);
    $orgBuild = $orgController->page('instituto-cultural-aculta');
    $orgJson = $this->extractJsonLd($orgBuild['#attached']['html_head'], 'aculta_events_organization_jsonld');
    $this->assertNotNull($orgJson);
    $this->assertSame('https://schema.org', $orgJson['@context']);
    $this->assertSame('Organization', $orgJson['@type']);
    $this->assertSame('Instituto Cultural ACULTA', $orgJson['name']);
    $this->assertSame('BR', $orgJson['address']['addressCountry']);
    $this->assertSame('Curitiba', $orgJson['address']['addressLocality']);
    $this->assertSame('PR', $orgJson['address']['addressRegion']);
    $this->assertContains('https://aculta.example.invalid', $orgJson['sameAs']);
    $this->assertContains('https://instagram.com/aculta', $orgJson['sameAs']);
  }

  /**
   * Does not expose an organizer account without a public identity.
   */
  public function testPrivateOrganizerIdentityIsOmittedFromEventPresentation(): void {
    $owner = User::create([
      'name' => 'nome-cadastral-privado',
      'status' => 1,
    ]);
    $owner->save();
    $event = Event::create([
      'title' => 'Evento sem organização pública',
      'field_slug' => 'evento-sem-organizacao-publica',
      'field_status' => 'publicado',
      'field_organizador' => $owner->id(),
    ]);
    $event->save();
    EventDate::create([
      'title' => 'Sessão pública',
      'field_evento' => $event->id(),
      'field_quando' => [
        'value' => '2026-11-15T19:00:00',
        'end_value' => '2026-11-15T21:00:00',
      ],
    ])->save();

    $page = EventPageController::create($this->container)->page('evento-sem-organizacao-publica');
    $json = $this->extractJsonLd($page['#attached']['html_head'], 'aculta_events_event_jsonld');
    $encoded = json_encode($json, JSON_UNESCAPED_UNICODE);

    $this->assertArrayNotHasKey('sidebar', $page['#slots']);
    $this->assertStringNotContainsString('nome-cadastral-privado', (string) $encoded);
    $this->assertArrayNotHasKey('organizer', $json['@graph'][0]);
    $this->assertContains('user:1', $page['#cache']['tags']);
  }

  /**
   * Extrai e decodifica o JSON-LD dos anexos html_head.
   *
   * @param array<int, array<int, mixed>> $htmlHead
   *   Anexos html_head do render array.
   * @param string $key
   *   Chave identificadora do script.
   *
   * @return array<string, mixed>|null
   *   Estrutura decodificada ou NULL.
   */
  private function extractJsonLd(array $htmlHead, string $key): ?array {
    foreach ($htmlHead as $item) {
      if (isset($item[1]) && $item[1] === $key) {
        $raw = (string) ($item[0]['#value'] ?? '');
        if ($raw !== '') {
          return json_decode($raw, TRUE);
        }
      }
    }
    return NULL;
  }

}
