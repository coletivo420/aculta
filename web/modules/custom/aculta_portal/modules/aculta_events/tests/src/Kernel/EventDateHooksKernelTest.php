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
 * Datas passadas fora das listagens e cache que expira no fim da data (auditoria, T5).
 *
 * Cobre o equivalente ao nodeViewAlter/viewsPostExecute do OpenCulturas, reescrito:
 * a página e o catálogo escondem ocorrência terminada, uma ocorrência em andamento
 * continua visível, e cada saída declara max-age até o instante em que muda sozinha.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventDateHooksKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'taxonomy', 'text', 'filter', 'datetime', 'datetime_range',
    'options', 'field', 'key', 'address', 'geofield', 'views', 'aculta_calendar', 'aculta_events',
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

    $this->assertSame([], $pagina['#datas']);
  }

  /**
   * Ocorrência em andamento continua na página; antes, ela sumia no meio do evento.
   */
  public function testOcorrenciaEmAndamentoPermaneceNaPagina(): void {
    [$evento] = $this->eventoComData(-60, 60);

    $pagina = EventPageController::create($this->container)->page((string) $evento->get('field_slug')->value);

    $this->assertCount(1, $pagina['#datas']);
    $this->assertStringContainsString(' às ', (string) $pagina['#datas'][0]['#markup']);
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

    // A pilha de requisição volta ao estado inicial: o KernelTestBase fecha a sessão
    // da requisição corrente no tearDown, e a requisição do teste não tem sessão.
    $pilha = $this->container->get('request_stack');
    $pilha->push(Request::create('/eventos'));
    try {
      $pagina = EventCatalogueController::create($this->container)->page();
    }
    finally {
      $pilha->pop();
    }

    $agora = $this->container->get('datetime.time')->getCurrentTime();
    $this->assertCount(1, $pagina['#items']);
    $this->assertEqualsWithDelta($fim->getTimestamp() - $agora, $pagina['#cache']['max-age'], 5);
    $this->assertSame([EventCacheTags::LISTA], $pagina['#cache']['tags']);
  }

  /**
   * A view de datas perde a linha vencida, corrige total_rows e declara a validade.
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
   * Criar ou editar evento e data invalida a listagem (tag declarada no catálogo).
   */
  public function testSalvarEventoOuDataTemHookDeInvalidacao(): void {
    $hooks = [
      'aculta_event_insert',
      'aculta_event_update',
      'aculta_event_delete',
      'aculta_event_date_insert',
      'aculta_event_date_update',
      'aculta_event_date_delete',
    ];
    foreach ($hooks as $hook) {
      $this->assertTrue(
        $this->container->get('module_handler')->hasImplementations($hook),
        sprintf('Falta implementação de %s.', $hook),
      );
    }
  }

}
