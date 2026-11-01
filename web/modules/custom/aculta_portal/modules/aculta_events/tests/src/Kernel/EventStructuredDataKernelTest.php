<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\EventOrganizationPageController;
use Drupal\aculta_events\Controller\EventPageController;
use Drupal\aculta_events\Controller\LocationPageController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\KernelTests\KernelTestBase;
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
   * Valida a idempotência de aculta_events_update_10005.
   */
  public function testUpdate10005Idempotence(): void {
    \Drupal::moduleHandler()->loadInclude('aculta_events', 'install');
    $resultado = aculta_events_update_10005();
    $this->assertIsString($resultado);
    // Em execução subsequente, informa que já existiam sem lançar exceção.
    $resultado2 = aculta_events_update_10005();
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
