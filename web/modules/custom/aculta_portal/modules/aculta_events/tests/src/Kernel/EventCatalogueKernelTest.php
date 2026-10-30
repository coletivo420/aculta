<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\EventPageController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Catálogo e página do evento, incluindo busca, slug e bloqueio de rascunhos.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventCatalogueKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'options', 'link', 'text',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar',
    'aculta_events',
  ];

  /**
   * Agora de Brasília, fixo no teste.
   */
  private \DateTimeImmutable $agora;

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
    $this->agora = new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
  }

  /**
   * Data de evento em dias a partir de agora, no horário informado (Brasília).
   */
  private function data(Event $evento, int $dias, string $horas = '19:00', string $fim = '21:00'): void {
    $inicio = $this->agora->modify("+$dias days")->setTime((int) substr($horas, 0, 2), (int) substr($horas, 3, 2));
    $termino = $this->agora->modify("+$dias days")->setTime((int) substr($fim, 0, 2), (int) substr($fim, 3, 2));
    $utc = new \DateTimeZone('UTC');
    EventDate::create([
      'title' => $evento->label(),
      'field_evento' => $evento->id(),
      'field_quando' => [
        'value' => $inicio->setTimezone($utc)->format('Y-m-d\TH:i:s'),
        'end_value' => $termino->setTimezone($utc)->format('Y-m-d\TH:i:s'),
      ],
    ])->save();
  }

  /**
   * Evento com título, situação e tipo opcionais.
   */
  private function evento(string $titulo, string $situacao, ?Term $tipo = NULL): Event {
    $valores = ['title' => $titulo, 'field_status' => $situacao];
    if ($tipo !== NULL) {
      $valores['field_tipo'] = $tipo->id();
    }
    $evento = Event::create($valores);
    $evento->save();
    return $evento;
  }

  /**
   * Cenário comum: oficina publicada, feira publicada, cancelado e rascunho.
   *
   * @return array{oficina: \Drupal\aculta_events\Entity\Event,
   *   feira: \Drupal\aculta_events\Entity\Event,
   *   cancelado: \Drupal\aculta_events\Entity\Event,
   *   rascunho: \Drupal\aculta_events\Entity\Event,
   *   tipo: \Drupal\taxonomy\Entity\Term}
   */
  private function cenario(): array {
    Vocabulary::create(['vid' => 'aculta_event_type', 'name' => 'Tipo de evento'])->save();
    $tipo = Term::create(['vid' => 'aculta_event_type', 'name' => 'Oficina']);
    $tipo->save();

    $oficina = $this->evento('Oficina de cerâmica', 'publicado', $tipo);
    $this->data($oficina, 3);
    $feira = $this->evento('Feira do livro', 'publicado');
    $this->data($feira, 10);
    $cancelado = $this->evento('Roda cancelada', 'cancelado');
    $this->data($cancelado, 2);
    $rascunho = $this->evento('Rascunho secreto', 'rascunho');
    $this->data($rascunho, 4);

    return [
      'oficina' => $oficina,
      'feira' => $feira,
      'cancelado' => $cancelado,
      'rascunho' => $rascunho,
      'tipo' => $tipo,
    ];
  }

  /**
   * Catálogo mantém cancelados e exclui rascunhos em ordem cronológica.
   */
  public function testCatalogoSemFiltroSemRascunhoEmOrdem(): void {
    $this->cenario();
    $resultados = $this->container->get('aculta_events.catalogue')->busca(NULL, NULL, NULL, $this->agora);
    $titulos = array_map(static fn (array $r): string => (string) $r['evento']->label(), $resultados);
    $this->assertSame(['Roda cancelada', 'Oficina de cerâmica', 'Feira do livro'], $titulos);
  }

  /**
   * Filtro por tipo devolve só os eventos desse tipo.
   */
  public function testFiltraPorTipo(): void {
    $cenario = $this->cenario();
    $resultados = $this->container->get('aculta_events.catalogue')->busca((int) $cenario['tipo']->id(), NULL, NULL, $this->agora);
    $this->assertSame(['Oficina de cerâmica'], array_map(static fn (array $r): string => (string) $r['evento']->label(), $resultados));
  }

  /**
   * Filtro por período: só o que cai dentro da janela.
   */
  public function testFiltraPorPeriodo(): void {
    $this->cenario();
    $de = $this->agora->modify('+5 days');
    $ate = $this->agora->modify('+15 days');
    $resultados = $this->container->get('aculta_events.catalogue')->busca(NULL, $de, $ate, $this->agora);
    $this->assertSame(['Feira do livro'], array_map(static fn (array $r): string => (string) $r['evento']->label(), $resultados));
  }

  /**
   * Página do evento pelo slug; rascunhos e slugs inexistentes respondem 404.
   */
  public function testPaginaPorSlugNaoPermiteRascunho(): void {
    $this->cenario();
    $controlador = EventPageController::create($this->container);
    $pagina = $controlador->page('oficina-de-ceramica');
    $this->assertSame('Oficina de cerâmica', $pagina['#titulo']);
    $this->assertSame('Oficina', $pagina['#tipo']);

    $this->expectException(NotFoundHttpException::class);
    $controlador->page('rascunho-secreto');
  }

  /**
   * Recurrence controls appear only for administrators and vary by permissions.
   */
  public function testRecurrenceLinksArePermissionScoped(): void {
    $evento = $this->evento('Oficina recorrente', 'publicado');
    $inicio = $this->agora->modify('+2 days')->setTime(10, 0);
    $fim = $inicio->modify('+1 hour');
    $utc = new \DateTimeZone('UTC');
    $data = EventDate::create([
      'title' => $evento->label(),
      'field_evento' => $evento->id(),
      'field_quando' => [
        'value' => $inicio->setTimezone($utc)->format('Y-m-d\\TH:i:s'),
        'end_value' => $fim->setTimezone($utc)->format('Y-m-d\\TH:i:s'),
      ],
      'field_regra' => 'FREQ=DAILY;COUNT=2',
    ]);
    $data->save();

    $this->container->get('current_user')->setAccount(new UserSession([
      'uid' => 1,
      'permissions' => ['administer aculta events'],
    ]));
    $page = EventPageController::create($this->container)->page('oficina-recorrente');
    $this->assertContains('user.permissions', $page['#cache']['contexts']);
    $adminMarkup = $page['#datas'][0]['#markup'];
    $id = (string) $data->id();
    $this->assertStringContainsString('/painel-administrativo/eventos/datas/' . $id . '/recorrencia', $adminMarkup);
    $this->assertStringContainsString('/painel-administrativo/eventos/datas/' . $id . '/ocorrencia/' . $inicio->getTimestamp(), $adminMarkup);
    $this->assertStringContainsString('Editar recorrência', $adminMarkup);
    $this->assertStringContainsString('Editar esta ocorrência', $adminMarkup);

    $this->container->get('current_user')->setAccount(new UserSession(['uid' => 0]));
    $publicPage = EventPageController::create($this->container)->page('oficina-recorrente');
    $this->assertStringNotContainsString('/painel-administrativo/eventos/datas/' . $id . '/recorrencia', $publicPage['#datas'][0]['#markup']);
    $this->assertContains('user.permissions', $publicPage['#cache']['contexts']);
  }

  /**
   * Paginação e limites da busca evitam sobrecarga e respeitam ordenação.
   */
  public function testPaginationAndLimit(): void {
    $this->cenario();
    $catalogo = $this->container->get('aculta_events.catalogue');

    // Ordem cronológica: Roda cancelada (+2d), Oficina (+3d), Feira (+10d).
    $todos = $catalogo->busca(NULL, NULL, NULL, $this->agora);
    $this->assertCount(3, $todos);

    // Página 1 com limite 2 traz os 2 primeiros na ordem cronológica.
    $pagina1 = $catalogo->busca(NULL, NULL, NULL, $this->agora, 2, 0);
    $this->assertCount(2, $pagina1);
    $this->assertSame('Roda cancelada', (string) $pagina1[0]['evento']->label());
    $this->assertSame('Oficina de cerâmica', (string) $pagina1[1]['evento']->label());

    // Página 2 com limite 2 e offset 2 traz o restante.
    $pagina2 = $catalogo->busca(NULL, NULL, NULL, $this->agora, 2, 2);
    $this->assertCount(1, $pagina2);
    $this->assertSame('Feira do livro', (string) $pagina2[0]['evento']->label());
  }

}
