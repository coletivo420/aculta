<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Calendar\EventIcsExporter;
use Drupal\aculta_events\Controller\EventIcsController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventDateOverride;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exportação .ics: série com RRULE, exceção cancelada e reagendada; cancelado marcado; rascunho fora.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventIcsExportKernelTest extends KernelTestBase {

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
   * Oficina semanal (seg e qua), com um cancelamento e um reagendamento.
   */
  public function testSerieExportadaComExcecoes(): void {
    $oficina = Event::create(['title' => 'Oficina', 'field_status' => 'publicado']);
    $oficina->save();
    $data = EventDate::create([
      'title' => 'Oficina',
      'field_evento' => $oficina->id(),
      'field_quando' => ['value' => $this->utc('2026-10-28 19:00:00'), 'end_value' => $this->utc('2026-10-28 21:00:00')],
      'field_regra' => 'FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3',
    ]);
    $data->save();
    EventDateOverride::create([
      'field_data_pai' => $data->id(),
      'field_inicio_original' => $this->utc('2026-11-02 19:00:00'),
    ])->save();
    EventDateOverride::create([
      'field_data_pai' => $data->id(),
      'field_inicio_original' => $this->utc('2026-11-04 19:00:00'),
      'field_inicio' => $this->utc('2026-11-05 20:00:00'),
      'field_fim' => $this->utc('2026-11-05 22:00:00'),
    ])->save();

    $rascunho = Event::create(['title' => 'Rascunho', 'field_status' => 'rascunho']);
    $rascunho->save();
    EventDate::create([
      'title' => 'Rascunho',
      'field_evento' => $rascunho->id(),
      'field_quando' => ['value' => $this->utc('2026-10-29 19:00:00'), 'end_value' => $this->utc('2026-10-29 20:00:00')],
    ])->save();

    $de = new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('America/Sao_Paulo'));
    $ate = new \DateTimeImmutable('2026-11-30 23:59:59', new \DateTimeZone('America/Sao_Paulo'));
    $ics = $this->container->get('aculta_events.ics_exporter')->exportar($de, $ate, $de, 'eventos.exemplo.invalid');

    $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3', $ics);
    $this->assertStringContainsString('EXDATE;TZID=America/Sao_Paulo:20261102T190000', $ics);
    $this->assertStringContainsString('RECURRENCE-ID;TZID=America/Sao_Paulo:20261104T190000', $ics);
    $this->assertStringContainsString('SUMMARY:Oficina', $ics);
    $this->assertStringNotContainsString('Rascunho', $ics, 'rascunho não é exportado');
    $this->assertStringContainsString('@eventos.exemplo.invalid', $ics, 'UID com o domínio do ambiente');
  }

  /**
   * Evento cancelado aparece com o prefixo de cancelamento.
   */
  public function testEventoCanceladoEMarcado(): void {
    $evento = Event::create(['title' => 'Roda', 'field_status' => 'cancelado']);
    $evento->save();
    EventDate::create([
      'title' => 'Roda',
      'field_evento' => $evento->id(),
      'field_quando' => ['value' => $this->utc('2026-10-28 19:00:00'), 'end_value' => $this->utc('2026-10-28 20:00:00')],
    ])->save();
    $de = new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('America/Sao_Paulo'));
    $ics = $this->container->get('aculta_events.ics_exporter')->exportar($de, $de->modify('+60 days'), $de, 'eventos.exemplo.invalid');
    $this->assertStringContainsString('SUMMARY:[Cancelado] Roda', $ics);
  }

  /**
   * Período inválido ou acima do teto responde 400, antes de exportar.
   */
  public function testValidaPeriodo(): void {
    $pilha = $this->container->get('request_stack');
    $controlador = EventIcsController::create($this->container);

    $pilha->push(Request::create('/calendario/exportar.ics?de=10/11/2026&ate=01/11/2026'));
    $this->assertSame(400, $controlador->periodo()->getStatusCode());
    $pilha->pop();

    $pilha->push(Request::create('/calendario/exportar.ics?de=01/01/2026&ate=31/12/2027'));
    $this->assertSame(400, $controlador->periodo()->getStatusCode());
    $pilha->pop();

    $pilha->push(Request::create('/calendario/exportar.ics?de=01/11/2026&ate=30/11/2026'));
    $this->assertSame(200, $controlador->periodo()->getStatusCode());
    $pilha->pop();
  }

  /**
   * Exportação de uma data leva a regra da série, sem depender de janela.
   */
  public function testExportaUmaDataComRegra(): void {
    $evento = Event::create(['title' => 'Oficina', 'field_status' => 'publicado']);
    $evento->save();
    $data = EventDate::create([
      'title' => 'Oficina',
      'field_evento' => $evento->id(),
      'field_quando' => ['value' => $this->utc('2026-10-28 19:00:00'), 'end_value' => $this->utc('2026-10-28 21:00:00')],
      'field_regra' => 'FREQ=WEEKLY;BYDAY=WE;COUNT=3',
    ]);
    $data->save();

    $agora = new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('America/Sao_Paulo'));
    $ics = $this->container->get('aculta_events.ics_exporter')->exportarData($data, $agora, 'eventos.exemplo.invalid');

    $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=WE;COUNT=3', $ics);
    $this->assertStringContainsString('SUMMARY:Oficina', $ics);
    $this->assertStringContainsString('DTSTART;TZID=America/Sao_Paulo:20261028T190000', $ics);
    $this->assertStringContainsString('@eventos.exemplo.invalid', $ics);
  }

  /**
   * O filtro de tipo de evento vale também para a exportação do período.
   */
  public function testFiltroNaExportacaoDePeriodo(): void {
    $oficina = Term::create(['vid' => 'aculta_event_type', 'name' => 'Oficina']);
    $oficina->save();
    $show = Term::create(['vid' => 'aculta_event_type', 'name' => 'Show']);
    $show->save();
    foreach ([['Oficina', (int) $oficina->id()], ['Show', (int) $show->id()]] as [$titulo, $tipo]) {
      $evento = Event::create(['title' => $titulo, 'field_status' => 'publicado', 'field_tipo' => $tipo]);
      $evento->save();
      EventDate::create([
        'title' => $titulo,
        'field_evento' => $evento->id(),
        'field_quando' => ['value' => $this->utc('2026-11-10 19:00:00'), 'end_value' => $this->utc('2026-11-10 20:00:00')],
      ])->save();
    }

    $de = new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('America/Sao_Paulo'));
    $agora = new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('America/Sao_Paulo'));
    $ics = $this->container->get('aculta_events.ics_exporter')->exportar($de, $de->modify('+30 days'), $agora, 'eventos.exemplo.invalid', (int) $oficina->id());

    $this->assertStringContainsString('SUMMARY:Oficina', $ics);
    $this->assertStringNotContainsString('SUMMARY:Show', $ics);
  }

  /**
   * A rota de uma data responde 200 com o slug certo e 404 com outro slug.
   */
  public function testControllerDataExigeSlugDoEvento(): void {
    $evento = Event::create(['title' => 'Oficina', 'field_status' => 'publicado']);
    $evento->save();
    $data = EventDate::create([
      'title' => 'Oficina',
      'field_evento' => $evento->id(),
      'field_quando' => ['value' => $this->utc('2026-10-28 19:00:00'), 'end_value' => $this->utc('2026-10-28 21:00:00')],
    ]);
    $data->save();
    $slug = (string) $evento->get('field_slug')->value;

    $pilha = $this->container->get('request_stack');
    $controlador = EventIcsController::create($this->container);
    $pilha->push(Request::create('/eventos/' . $slug . '/data/' . $data->id() . '.ics'));
    $this->assertSame(200, $controlador->data($slug, (int) $data->id())->getStatusCode());
    $pilha->pop();

    $this->expectException(NotFoundHttpException::class);
    $controlador->data('outro-evento', (int) $data->id());
  }

}
