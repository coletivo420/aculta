<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Cache\EventCacheTags;
use Drupal\aculta_events\Controller\EventCatalogueController;
use Drupal\aculta_events\Controller\EventPageController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Hook\EventDateHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\ResultRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Oculta datas passadas e expira o cache ao final da ocorrência (T5).
 *
 * Cobre a lógica reescrita de página e catálogo: ocorrências terminadas somem,
 * as que estão em andamento permanecem e o cache expira quando a saída muda.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventDateHooksKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'taxonomy', 'link', 'text', 'filter', 'datetime', 'datetime_range',
    'options', 'field', 'key', 'address', 'geofield', 'views', 'ui_patterns',
    'ui_patterns_views', 'aculta_calendar', 'aculta_events',
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
   * Evento publicado com uma data em minutos a partir de agora.
   */
  private function eventoComData(int $minutosInicio, int $minutosFim, string $titulo = 'Feira do livro'): array {
    $evento = Event::create(['title' => $titulo, 'field_status' => 'publicado']);
    $evento->save();

    $inicio = (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))
      ->modify(sprintf('%+d minutes', $minutosInicio));
    $fim = (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))
      ->modify(sprintf('%+d minutes', $minutosFim));

    $data = EventDate::create([
      'title' => $titulo,
      'field_evento' => $evento->id(),
      'field_quando' => [
        'value' => $inicio->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
        'end_value' => $fim->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
      ],
    ]);
    $data->save();

    return [$evento, $data, $fim];
  }

  /**
   * Data que já terminou não aparece na página do evento.
   */
  public function testDataPassadaNaoApareceNaPagina(): void {
    [$evento] = $this->eventoComData(-180, -60);

    $pagina = EventPageController::create($this->container)->page((string) $evento->get('field_slug')->value);

    $this->assertArrayNotHasKey('dates', $pagina['#slots']['essentials'] ?? []);
  }

  /**
   * Ocorrência em andamento permanece visível até seu fim.
   */
  public function testOcorrenciaEmAndamentoPermaneceNaPagina(): void {
    [$evento] = $this->eventoComData(-60, 60);

    $pagina = EventPageController::create($this->container)->page((string) $evento->get('field_slug')->value);

    $dateItems = array_filter(
      array_keys($pagina['#slots']['essentials']['dates']['items']),
      static fn (string|int $key): bool => is_string($key) && !str_starts_with($key, '#'),
    );
    $this->assertCount(1, $dateItems);
    $this->assertStringContainsString(' às ', (string) $pagina['#slots']['essentials']['dates']['items']['date_0']['#markup']);
  }

  /**
   * O cache da página expira no fim da data, não num cron diário.
   */
  public function testCacheDaPaginaExpiraNoFimDaData(): void {
    [$evento, , $fim] = $this->eventoComData(60, 120);

    $pagina = EventPageController::create($this->container)->page((string) $evento->get('field_slug')->value);

    $agora = $this->container->get('datetime.time')->getCurrentTime();
    $this->assertEqualsWithDelta($fim->getTimestamp() - $agora, $pagina['#cache']['max-age'], 5);
    $this->assertContains('aculta_event:' . $evento->id(), $pagina['#cache']['tags']);
    $this->assertContains(EventCacheTags::LISTA, $pagina['#cache']['tags']);
  }

  /**
   * O catálogo também declara validade até o fim da próxima data listada.
   */
  public function testCacheDoCatalogoExpiraNoFimDaData(): void {
    [, , $fim] = $this->eventoComData(60, 120);

    // O tearDown restaura a pilha; esta requisição de teste não tem sessão.
    $pilha = $this->container->get('request_stack');
    $requisicao = Request::create('/eventos');
    $pilha->push($requisicao);
    try {
      $pagina = EventCatalogueController::create($this->container)->page();
    }
    finally {
      $pilha->pop();
    }

    $agora = $this->container->get('datetime.time')->getCurrentTime();
    $this->assertSame('view', $pagina['catalogue']['#type']);
    $this->assertSame('eventos_publicos', $pagina['catalogue']['#name']);
    $this->assertCount(1, $requisicao->attributes->get('_aculta_events_catalogue_cards', []));
    $this->assertEqualsWithDelta($fim->getTimestamp() - $agora, $pagina['#cache']['max-age'], 5);
    $this->assertSame([EventCacheTags::LISTA], $pagina['#cache']['tags']);
  }

  /**
   * A view remove datas vencidas e atualiza total_rows e cache.
   */
  public function testViewDeDatasRemoveLinhaVencida(): void {
    [, $vencida] = $this->eventoComData(-180, -60, 'Antiga');
    [, $futura, $fim] = $this->eventoComData(60, 120, 'Próxima');

    $view = View::create([
      'id' => 'teste_datas',
      'label' => 'Teste datas',
      'base_table' => 'aculta_event_date',
      'base_field' => 'id',
      'display' => ['default' => ['display_plugin' => 'default', 'id' => 'default', 'display_title' => 'Padrão']],
    ]);
    $view->save();
    $executavel = $view->getExecutable();
    $executavel->current_display = 'default';
    $executavel->result = [
      new ResultRow(['index' => 0, '_entity' => $vencida]),
      new ResultRow(['index' => 1, '_entity' => $futura]),
    ];
    $executavel->total_rows = 2;

    $this->container->get(EventDateHooks::class)->viewsPostExecute($executavel);

    $this->assertCount(1, $executavel->result);
    $this->assertSame(1, $executavel->total_rows);
    $agora = $this->container->get('datetime.time')->getCurrentTime();
    $this->assertEqualsWithDelta($fim->getTimestamp() - $agora, $executavel->element['#cache']['max-age'], 5);
  }

  /**
   * View que não é de datas de evento passa intacta.
   */
  public function testViewDeOutraEntidadeNaoMuda(): void {
    [, $data] = $this->eventoComData(-180, -60);

    $view = View::create([
      'id' => 'teste_locais',
      'label' => 'Teste locais',
      'base_table' => 'aculta_event_location',
      'base_field' => 'id',
      'display' => ['default' => ['display_plugin' => 'default', 'id' => 'default', 'display_title' => 'Padrão']],
    ]);
    $view->save();
    $executavel = $view->getExecutable();
    $executavel->current_display = 'default';
    $executavel->result = [new ResultRow(['index' => 0, '_entity' => $data])];
    $executavel->total_rows = 1;

    $this->container->get(EventDateHooks::class)->viewsPostExecute($executavel);

    $this->assertCount(1, $executavel->result);
    $this->assertSame([], $executavel->element['#cache']);
  }

  /**
   * Criar ou editar evento e data invalida a listagem do catálogo.
   */
  public function testSalvarEventoOuDataTemHookDeInvalidacao(): void {
    $hooks = [
      'aculta_event_insert',
      'aculta_event_update',
      'aculta_event_delete',
      'aculta_event_date_insert',
      'aculta_event_date_update',
      'aculta_event_date_delete',
      'aculta_event_override_insert',
      'aculta_event_override_update',
      'aculta_event_override_delete',
    ];
    foreach ($hooks as $hook) {
      $this->assertTrue(
        $this->container->get('module_handler')->hasImplementations($hook),
        sprintf('Falta implementação de %s.', $hook),
      );
    }
  }

}
