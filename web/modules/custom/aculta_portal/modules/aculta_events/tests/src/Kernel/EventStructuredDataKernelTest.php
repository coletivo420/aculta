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
use Drupal\Core\Url;
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
    $publicPage = $this->expectArray($controller->page('evento-hibrido-publico'));
    $publicSlots = $this->expectArray($publicPage['#slots'] ?? NULL);
    $publicEssentials = $this->expectArray($publicSlots['essentials'] ?? NULL);
    $publicDates = $this->expectArray($publicEssentials['dates'] ?? NULL);
    $publicItems = $this->expectArray($publicDates['items'] ?? NULL);
    $publicDate = $this->expectArray($publicItems['date_0'] ?? NULL);
    $publicMarkup = $this->expectString($publicDate['#markup'] ?? NULL);
    $publicAttached = $this->expectArray($publicPage['#attached'] ?? NULL);
    $publicHtmlHead = $this->expectArray($publicAttached['html_head'] ?? NULL);
    $publicJson = $this->extractJsonLd($publicHtmlHead, 'aculta_events_event_jsonld');
    $this->assertNotNull($publicJson);
    $publicGraph = $this->expectArray($publicJson['@graph'] ?? NULL);
    $publicFirstGraphItem = $this->expectArray($publicGraph[0] ?? NULL);
    $publicLocation = $this->expectArray($publicFirstGraphItem['location'] ?? NULL);

    $this->assertStringNotContainsString('Local de endereço reservado', $publicMarkup);
    $encodedPublicJson = $this->expectString(json_encode($publicJson));
    $this->assertStringNotContainsString('Rua Reservada', $encodedPublicJson);
    $this->assertStringNotContainsString('80000-987', $encodedPublicJson);
    $this->assertSame('VirtualLocation', $publicLocation['@type'] ?? NULL);
    $publicCache = $this->expectArray($publicPage['#cache'] ?? NULL);
    $publicContexts = $this->expectArray($publicCache['contexts'] ?? NULL);
    $this->assertContains('user', $publicContexts);

    $this->container->get('current_user')->setAccount($owner);
    $ownerPage = $this->expectArray($controller->page('evento-hibrido-publico'));
    $ownerAttached = $this->expectArray($ownerPage['#attached'] ?? NULL);
    $ownerHtmlHead = $this->expectArray($ownerAttached['html_head'] ?? NULL);
    $ownerJson = $this->extractJsonLd($ownerHtmlHead, 'aculta_events_event_jsonld');
    $this->assertNotNull($ownerJson);
    $ownerGraph = $this->expectArray($ownerJson['@graph'] ?? NULL);
    $ownerFirstGraphItem = $this->expectArray($ownerGraph[0] ?? NULL);
    $ownerLocations = $this->expectArray($ownerFirstGraphItem['location'] ?? []);

    $ownerSlots = $this->expectArray($ownerPage['#slots'] ?? NULL);
    $ownerEssentials = $this->expectArray($ownerSlots['essentials'] ?? NULL);
    $ownerDates = $this->expectArray($ownerEssentials['dates'] ?? NULL);
    $ownerItems = $this->expectArray($ownerDates['items'] ?? NULL);
    $ownerDate = $this->expectArray($ownerItems['date_0'] ?? NULL);
    $ownerMarkup = $this->expectString($ownerDate['#markup'] ?? NULL);
    $this->assertStringContainsString('Local de endereço reservado', $ownerMarkup);
    $this->assertCount(2, $ownerLocations);
    $ownerLocation = $this->expectArray($ownerLocations[1] ?? NULL);
    $ownerAddress = $this->expectArray($ownerLocation['address'] ?? NULL);
    $this->assertSame('Rua Reservada, 987', $ownerAddress['streetAddress'] ?? NULL);
    $this->assertSame('80000-987', $ownerAddress['postalCode'] ?? NULL);
    $ownerCache = $this->expectArray($ownerPage['#cache'] ?? NULL);
    $ownerContexts = $this->expectArray($ownerCache['contexts'] ?? NULL);
    $this->assertContains('user', $ownerContexts);
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
    $build = $this->expectArray(LocationPageController::create($this->container)->page($location));
    $agenda = $this->expectArray($build['agenda'] ?? NULL);
    $days = $this->expectArray($agenda['days'] ?? NULL);
    $ics = $this->expectArray($agenda['ics'] ?? NULL);
    $url = $this->expectUrl($ics['#url'] ?? NULL);
    $this->assertSame('aculta_events.location_ics', $url->getRouteName());
    $routeParameters = $url->getRouteParameters();
    $this->assertSame((string) $location->id(), $this->expectString($routeParameters['aculta_event_location'] ?? NULL));
    $firstDay = $first->format('Y-m-d');
    $cancelledDay = $second->format('Y-m-d');
    $originalThirdDay = $third->format('Y-m-d');
    $rescheduledDay = $rescheduled->format('Y-m-d');
    $markup = '';
    foreach ($days as $day) {
      $dayBuild = $this->expectArray($day);
      $dayItems = $this->expectArray($dayBuild['items'] ?? NULL);
      $items = $this->expectArray($dayItems['#items'] ?? NULL);
      foreach ($items as $item) {
        $itemBuild = $this->expectArray($item);
        $markup .= $this->expectString($itemBuild['#markup'] ?? NULL);
      }
    }

    $this->assertSame([$firstDay, $cancelledDay, $rescheduledDay], array_keys($days));
    $this->assertArrayNotHasKey($originalThirdDay, $days);
    $this->assertStringContainsString('/eventos/evento-da-agenda', $markup);
    $this->assertStringContainsString('cancelado', $markup);
    $this->assertStringNotContainsString('Evento de outro local', $markup);
    $agendaCache = $this->expectArray($agenda['#cache'] ?? NULL);
    $this->assertContains('aculta_event_date_list', $this->expectArray($agendaCache['tags'] ?? NULL));
    $this->assertContains('user', $this->expectArray($agendaCache['contexts'] ?? NULL));

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

    $now = $this->container->get('datetime.time')->getCurrentTime();
    $data = EventDate::create([
      'title' => 'Sessão de Abertura',
      'field_evento' => (int) $evento->id(),
      'field_local' => (int) $local->id(),
      'field_quando' => [
        'value' => gmdate('Y-m-d\\TH:i:s', $now + 86400),
        'end_value' => gmdate('Y-m-d\\TH:i:s', $now + 95400),
      ],
      'field_modo' => 'hibrido',
      'field_link_virtual' => 'https://aovivo.example.invalid/cinema',
      'field_gratuito' => TRUE,
    ]);
    $data->save();

    // Validação da página do Evento:
    $eventController = EventPageController::create($this->container);
    $eventBuild = $this->expectArray($eventController->page('mostra-cinema-independente'));
    $this->assertArrayHasKey('#attached', $eventBuild);
    $eventAttached = $this->expectArray($eventBuild['#attached']);
    $eventHtmlHead = $this->expectArray($eventAttached['html_head'] ?? NULL);
    $this->assertNotEmpty($eventHtmlHead);

    $eventJson = $this->extractJsonLd($eventHtmlHead, 'aculta_events_event_jsonld');
    $this->assertNotNull($eventJson);
    $eventData = $this->expectArray($eventJson);
    $this->assertSame('https://schema.org', $eventData['@context'] ?? NULL);
    $eventGraph = $this->expectArray($eventData['@graph'] ?? NULL);
    $this->assertNotEmpty($eventGraph);
    $eventGraphItem = $this->expectArray($eventGraph[0] ?? NULL);
    $this->assertSame('Event', $eventGraphItem['@type'] ?? NULL);
    $this->assertSame('Mostra de Cinema Independente', $eventGraphItem['name'] ?? NULL);
    $this->assertSame('https://schema.org/MixedEventAttendanceMode', $eventGraphItem['eventAttendanceMode'] ?? NULL);
    $this->assertSame('https://schema.org/EventScheduled', $eventGraphItem['eventStatus'] ?? NULL);

    // Valida location composto (VirtualLocation + Place):
    $eventLocations = $this->expectArray($eventGraphItem['location'] ?? NULL);
    $this->assertCount(2, $eventLocations);
    $virtualLocation = $this->expectArray($eventLocations[0] ?? NULL);
    $placeLocation = $this->expectArray($eventLocations[1] ?? NULL);
    $this->assertSame('VirtualLocation', $virtualLocation['@type'] ?? NULL);
    $this->assertSame('https://aovivo.example.invalid/cinema', $virtualLocation['url'] ?? NULL);
    $this->assertSame('Place', $placeLocation['@type'] ?? NULL);
    $this->assertSame('Espaço Cultural das Flores', $placeLocation['name'] ?? NULL);
    $placeAddress = $this->expectArray($placeLocation['address'] ?? NULL);
    $this->assertSame('Curitiba', $placeAddress['addressLocality'] ?? NULL);

    // Valida organizer:
    $eventOrganizer = $this->expectArray($eventGraphItem['organizer'] ?? NULL);
    $this->assertSame('Organization', $eventOrganizer['@type'] ?? NULL);
    $this->assertSame('Instituto Cultural ACULTA', $eventOrganizer['name'] ?? NULL);

    // Valida offers:
    $eventOffer = $this->expectArray($eventGraphItem['offers'] ?? NULL);
    $this->assertSame('Offer', $eventOffer['@type'] ?? NULL);
    $this->assertSame('0', $eventOffer['price'] ?? NULL);
    $this->assertSame('BRL', $eventOffer['priceCurrency'] ?? NULL);

    // Validação da página do Local:
    $locationController = LocationPageController::create($this->container);
    $locationBuild = $this->expectArray($locationController->page($local));
    $locationAttached = $this->expectArray($locationBuild['#attached'] ?? NULL);
    $locationHtmlHead = $this->expectArray($locationAttached['html_head'] ?? NULL);
    $locationJson = $this->extractJsonLd($locationHtmlHead, 'aculta_events_location_jsonld');
    $this->assertNotNull($locationJson);
    $locationData = $this->expectArray($locationJson);
    $this->assertSame('https://schema.org', $locationData['@context'] ?? NULL);
    $this->assertSame('Place', $locationData['@type'] ?? NULL);
    $this->assertSame('Espaço Cultural das Flores', $locationData['name'] ?? NULL);
    $this->assertSame('(41) 3333-4444', $locationData['telephone'] ?? NULL);
    $this->assertSame('https://espacodasflores.art.br', $locationData['sameAs'] ?? NULL);
    $locationAddress = $this->expectArray($locationData['address'] ?? NULL);
    $this->assertSame('BR', $locationAddress['addressCountry'] ?? NULL);
    $this->assertSame('Curitiba', $locationAddress['addressLocality'] ?? NULL);
    $this->assertSame('PR', $locationAddress['addressRegion'] ?? NULL);
    $this->assertSame('Rua das Flores, 100', $locationAddress['streetAddress'] ?? NULL);

    // Validação da página da Organização:
    $orgController = EventOrganizationPageController::create($this->container);
    $orgBuild = $this->expectArray($orgController->page('instituto-cultural-aculta'));
    $orgAttached = $this->expectArray($orgBuild['#attached'] ?? NULL);
    $orgHtmlHead = $this->expectArray($orgAttached['html_head'] ?? NULL);
    $orgJson = $this->extractJsonLd($orgHtmlHead, 'aculta_events_organization_jsonld');
    $this->assertNotNull($orgJson);
    $orgData = $this->expectArray($orgJson);
    $this->assertSame('https://schema.org', $orgData['@context'] ?? NULL);
    $this->assertSame('Organization', $orgData['@type'] ?? NULL);
    $this->assertSame('Instituto Cultural ACULTA', $orgData['name'] ?? NULL);
    $orgAddress = $this->expectArray($orgData['address'] ?? NULL);
    $this->assertSame('BR', $orgAddress['addressCountry'] ?? NULL);
    $this->assertSame('Curitiba', $orgAddress['addressLocality'] ?? NULL);
    $this->assertSame('PR', $orgAddress['addressRegion'] ?? NULL);
    $orgSameAs = $this->expectArray($orgData['sameAs'] ?? NULL);
    $this->assertContains('https://aculta.example.invalid', $orgSameAs);
    $this->assertContains('https://instagram.com/aculta', $orgSameAs);
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
    $now = $this->container->get('datetime.time')->getCurrentTime();
    EventDate::create([
      'title' => 'Sessão pública',
      'field_evento' => $event->id(),
      'field_quando' => [
        'value' => gmdate('Y-m-d\\TH:i:s', $now + 86400),
        'end_value' => gmdate('Y-m-d\\TH:i:s', $now + 93600),
      ],
    ])->save();

    $page = $this->expectArray(EventPageController::create($this->container)->page('evento-sem-organizacao-publica'));
    $attached = $this->expectArray($page['#attached'] ?? NULL);
    $htmlHead = $this->expectArray($attached['html_head'] ?? NULL);
    $json = $this->extractJsonLd($htmlHead, 'aculta_events_event_jsonld');
    $this->assertNotNull($json);
    $jsonData = $this->expectArray($json);
    $encoded = $this->expectString(json_encode($jsonData, JSON_UNESCAPED_UNICODE));

    $slots = $this->expectArray($page['#slots'] ?? NULL);
    $this->assertArrayNotHasKey('sidebar', $slots);
    $this->assertStringNotContainsString('nome-cadastral-privado', $encoded);
    $graph = $this->expectArray($jsonData['@graph'] ?? NULL);
    $graphItem = $this->expectArray($graph[0] ?? NULL);
    $this->assertArrayNotHasKey('organizer', $graphItem);
    $cache = $this->expectArray($page['#cache'] ?? NULL);
    $this->assertContains('user:1', $this->expectArray($cache['tags'] ?? NULL));
  }

  /**
   * Extrai e decodifica o JSON-LD dos anexos html_head.
   *
   * @param array<array-key, mixed> $htmlHead
   *   Anexos html_head do render array.
   * @param string $key
   *   Chave identificadora do script.
   *
   * @return array<string, mixed>|null
   *   Estrutura decodificada ou NULL.
   */
  private function extractJsonLd(array $htmlHead, string $key): ?array {
    foreach ($htmlHead as $item) {
      if (!is_array($item)) {
        continue;
      }
      if (isset($item[1]) && $item[1] === $key) {
        $script = $item[0] ?? NULL;
        if (!is_array($script)) {
          return NULL;
        }
        $raw = $script['#value'] ?? NULL;
        if (is_string($raw) && $raw !== '') {
          $decoded = json_decode($raw, TRUE);
          if (!is_array($decoded)) {
            return NULL;
          }
          $result = [];
          foreach ($decoded as $field => $value) {
            if (!is_string($field)) {
              return NULL;
            }
            $result[$field] = $value;
          }
          return $result;
        }
      }
    }
    return NULL;
  }

  /**
   * Asserts an array shape at a render or decoded JSON boundary.
   *
   * @param mixed $value
   *   The value to validate.
   *
   * @return array<array-key, mixed>
   *   The validated array.
   */
  private function expectArray(mixed $value): array {
    if (!is_array($value)) {
      throw new \UnexpectedValueException('Expected an array.');
    }
    return $value;
  }

  /**
   * Asserts a string at a render or encoding boundary.
   *
   * @param mixed $value
   *   The value to validate.
   */
  private function expectString(mixed $value): string {
    if (!is_string($value)) {
      throw new \UnexpectedValueException('Expected a string.');
    }
    return $value;
  }

  /**
   * Asserts that a render element contains a route URL.
   *
   * @param mixed $value
   *   The value to validate.
   */
  private function expectUrl(mixed $value): Url {
    if (!$value instanceof Url) {
      throw new \UnexpectedValueException('Expected a route URL.');
    }
    return $value;
  }

}
