<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Controller;

use Drupal\aculta_calendar\Agenda\CalendarOccurrences;
use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Visão interativa do calendário (DHTMLX Scheduler), carregada só nesta rota.
 *
 * O componente só apresenta. O motor de recorrência, o .ics e a regra de acesso
 * continuam no aculta_calendar; a agenda HTML é a alternativa sem JavaScript.
 */
final class InteractiveController extends ControllerBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly RouteMatchInterface $rotaAtual,
    private readonly CalendarItemSource $fonte,
    private readonly CalendarOccurrences $ocorrencias,
    private readonly TimeInterface $tempo,
    private readonly AgendaController $agendaPagina,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('aculta_calendar.item_source'),
      $container->get('aculta_calendar.occurrences'),
      $container->get('datetime.time'),
      AgendaController::create($container),
    );
  }

  /**
   * Página da visão interativa.
   */
  public function page(): array {
    $agenda = $this->url('agenda');
    return [
      'aviso' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['data-portal-ui' => 'calendar-interactive-notice'],
        '#value' => $this->t('Visão interativa do calendário. Sem JavaScript, use a agenda em lista.'),
      ],
      'controle' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'calendar-interactive-controls'],
        'contrast' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#attributes' => ['type' => 'button', 'id' => 'aculta-calendar-contrast', 'aria-pressed' => 'false'],
          '#value' => $this->t('Alto contraste'),
        ],
      ],
      'componente' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'aculta-calendar-interactive',
          'role' => 'region',
          'aria-label' => $this->t('Calendário interativo de eventos'),
          'data-portal-ui' => 'calendar-interactive',
        ],
      ],
      'alternativa' => $agenda === NULL ? ['#access' => FALSE] : [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['data-portal-ui' => 'calendar-interactive-alternative'],
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('Ver a agenda em lista'),
          '#url' => $agenda,
        ],
      ],
      // Em telas menores, a grade dá lugar à agenda sem JavaScript.
      'agenda' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'aculta-calendar-agenda',
          'data-portal-ui' => 'calendar-interactive-agenda',
        ],
        'conteudo' => $this->agendaPagina->conteudo(TRUE),
      ],
      '#attached' => [
        'library' => ['aculta_calendar/dhtmlx'],
        'drupalSettings' => ['acultaCalendar' => $this->dados()],
      ],
      '#cache' => [
        'tags' => array_merge(['aculta_calendar_item_list'], $this->fonte->cacheTags()),
        'contexts' => ['route'],
        'max-age' => 900,
      ],
    ];
  }

  /**
   * Dados do componente: itens e a data inicial da grade.
   *
   * Abre no mês da primeira ocorrência; sem itens, no mês atual.
   *
   * @return array{items: array<int, array<string, mixed>>, date: string}
   *   Itens e data inicial para a grade.
   */
  private function dados(): array {
    $itens = $this->itens();
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $hoje = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso)->format('Y-m-d H:i');
    return [
      'items' => $itens,
      'date' => $itens === [] ? $hoje : $itens[0]['start_date'],
    ];
  }

  /**
   * Ocorrências dos itens para o componente, no contrato JSON do scheduler.
   *
   * A expansão é do motor do calendário; o componente só apresenta.
   *
   * @return array<int, array<string, mixed>>
   *   Ocorrências expandidas no intervalo solicitado.
   */
  private function itens(): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
    // Um mês antes e um depois, para a navegação básica da grade.
    $de = $agora->modify('first day of previous month')->setTime(0, 0);
    $ate = $agora->modify('last day of next month')->setTime(23, 59, 59);
    $query = new CalendarQuery($de, $ate, $agora);

    $itens = [];
    foreach ($this->fonte->items($query) as $item) {
      try {
        $ocorrencias = $this->ocorrencias->expandir($item, $query);
      }
      catch (\Throwable) {
        continue;
      }
      foreach ($ocorrencias as $ocorrencia) {
        $cancelada = $ocorrencia['cancelada'] || $item->cancelado;
        $itens[] = [
          'id' => $item->id . ':' . $ocorrencia['original']->getTimestamp(),
          'text' => $cancelada ? '[Cancelado] ' . $item->titulo : $item->titulo,
          'start_date' => $ocorrencia['inicio']->format('Y-m-d H:i'),
          'end_date' => $ocorrencia['fim']->format('Y-m-d H:i'),
          'local' => $item->local,
          'url' => $item->link,
          'cancelado' => $cancelada,
        ];
      }
    }
    usort($itens, static fn (array $a, array $b): int => strcmp($a['start_date'], $b['start_date']));
    return $itens;
  }

  /**
   * URL de uma rota do publisher, informada em `_aculta_calendar_rotas`.
   */
  private function url(string $chave): ?Url {
    $rotas = $this->rotaAtual->getRouteObject()?->getOption('_aculta_calendar_rotas') ?? [];
    $nome = is_array($rotas) && is_string($rotas[$chave] ?? NULL) ? $rotas[$chave] : NULL;
    return $nome === NULL ? NULL : Url::fromRoute($nome);
  }

}
