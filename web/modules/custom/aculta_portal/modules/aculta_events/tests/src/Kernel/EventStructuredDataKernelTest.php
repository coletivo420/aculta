<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
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
      'field_link_virtual' => 'https://transmissao.aculta.org/debate',
      'field_abertura_portas' => '18:30 (30 minutos antes)',
    ]);
    $data->save();

    $this->assertSame('hibrido', $data->get('field_modo')->value);
    $this->assertSame('https://transmissao.aculta.org/debate', $data->get('field_link_virtual')->uri);
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

}
