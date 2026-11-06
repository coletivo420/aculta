<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Calendar;

use Drupal\aculta_calendar\Agenda\CalendarAgenda;
use Drupal\aculta_calendar\Agenda\MonthGrid;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Compõe o card de calendário para o Studio Dashboard (Fases 2 e 3).
 */
final class StudioDashboardCalendarComposer {

  use StringTranslationTrait;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly ?CalendarAgenda $agenda,
    private readonly MonthGrid $monthGrid,
    private readonly AccountInterface $currentUser,
    private readonly ?UrlGeneratorInterface $urlGenerator = NULL,
  ) {}

  /**
   * Constrói o render array do SDC studio-calendar.
   *
   * @param int|null $ano
   *   Ano (quatro dígitos). NULL para ano atual de Brasília.
   * @param int|null $mes
   *   Mês (1-12). NULL para mês atual de Brasília.
   * @param string|null $filtro
   *   Filtro ativo: 'cursos', 'eventos' ou 'meus_eventos'. Default: 'cursos'.
   * @param string|null $diaSelecionado
   *   Chave Y-m-d do dia selecionado. NULL para hoje.
   *
   * @return array<string, mixed>
   *   Render array do componente aculta420_v2:studio-calendar.
   */
  public function compose(?int $ano = NULL, ?int $mes = NULL, ?string $filtro = NULL, ?string $diaSelecionado = NULL): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = new \DateTimeImmutable('now', $fuso);

    $ano = $ano ?? (int) $agora->format('Y');
    $mes = $mes ?? (int) $agora->format('n');
    if ($mes < 1) {
      $mes = 1;
    }
    elseif ($mes > 12) {
      $mes = 12;
    }

    $filtroValido = in_array($filtro, ['cursos', 'eventos', 'meus_eventos'], TRUE) ? $filtro : 'cursos';

    $primeiroDiaMes = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $ano, $mes), $fuso);
    $ultimoDiaMes = $primeiroDiaMes->modify('last day of this month')->setTime(23, 59, 59);

    // Consulta ocorrências do mês para o provedor selecionado
    $porDia = [];
    if ($this->agenda !== NULL) {
      $query = new CalendarQuery(
        de: $primeiroDiaMes,
        ate: $ultimoDiaMes,
        agora: $agora,
        filtros: [],
        provedores: [$filtroValido],
      );
      try {
        $porDia = $this->agenda->dias($query);
      }
      catch (\Throwable) {
        $porDia = [];
      }
    }

    $grid = $this->monthGrid->build($ano, $mes, $porDia, $agora);

    // Se dia selecionado não for especificado ou não pertencer a este mês, default para hoje (se neste mês) ou dia 1
    $hojeStr = $agora->format('Y-m-d');
    if ($diaSelecionado === NULL) {
      if ((int) $agora->format('Y') === $ano && (int) $agora->format('n') === $mes) {
        $diaSelecionado = $hojeStr;
      }
      else {
        $diaSelecionado = $primeiroDiaMes->format('Y-m-d');
      }
    }

    // URLs de navegação
    $prevAno = $grid['anterior']['ano'];
    $prevMes = $grid['anterior']['mes'];
    $nextAno = $grid['seguinte']['ano'];
    $nextMes = $grid['seguinte']['mes'];

    $prevUrl = $this->buildCalendarUrl($prevAno, $prevMes, $filtroValido, NULL);
    $nextUrl = $this->buildCalendarUrl($nextAno, $nextMes, $filtroValido, NULL);

    // Filtros
    $filtrosProps = [
      [
        'id' => 'cursos',
        'label' => (string) $this->t('Cursos'),
        'url' => $this->buildCalendarUrl($ano, $mes, 'cursos', $diaSelecionado),
        'active' => $filtroValido === 'cursos',
      ],
      [
        'id' => 'eventos',
        'label' => (string) $this->t('Eventos'),
        'url' => $this->buildCalendarUrl($ano, $mes, 'eventos', $diaSelecionado),
        'active' => $filtroValido === 'eventos',
      ],
      [
        'id' => 'meus_eventos',
        'label' => (string) $this->t('Meus eventos'),
        'url' => $this->buildCalendarUrl($ano, $mes, 'meus_eventos', $diaSelecionado),
        'active' => $filtroValido === 'meus_eventos',
      ],
    ];

    // Semanas da grade
    $weeksProps = [];
    foreach ($grid['semanas'] as $semana) {
      $semanaProps = [];
      foreach ($semana as $celula) {
        /** @var \DateTimeImmutable|null $diaDt */
        $diaDt = $celula['dia'];
        $diaKey = $diaDt?->format('Y-m-d');
        $isCurrentMonth = $diaDt !== NULL;
        $isSelected = $diaKey !== NULL && $diaKey === $diaSelecionado;
        $itensDia = $celula['itens'] ?? [];
        $count = count($itensDia);

        $urlDia = NULL;
        if ($isCurrentMonth && $diaKey !== NULL) {
          $urlDia = $this->buildCalendarUrl($ano, $mes, $filtroValido, $diaKey);
        }

        $semanaProps[] = [
          'day_number' => $diaDt ? (int) $diaDt->format('j') : NULL,
          'date_string' => $diaKey,
          'is_current_month' => $isCurrentMonth,
          'is_today' => (bool) ($celula['hoje'] ?? FALSE),
          'is_selected' => $isSelected,
          'count' => $count,
          'url' => $urlDia,
        ];
      }
      $weeksProps[] = $semanaProps;
    }

    // Itens do dia selecionado
    $eventosDoDia = $porDia[$diaSelecionado] ?? [];
    $selectedDayEventsProps = [];
    foreach ($eventosDoDia as $ev) {
      $inicio = $ev['inicio'] instanceof \DateTimeImmutable ? $ev['inicio'] : NULL;
      $fim = $ev['fim'] instanceof \DateTimeImmutable ? $ev['fim'] : NULL;
      $diaInteiro = (bool) ($ev['diaInteiro'] ?? FALSE);

      $timeStr = NULL;
      if ($diaInteiro) {
        $timeStr = (string) $this->t('Dia inteiro');
      }
      elseif ($inicio !== NULL) {
        $timeStr = $inicio->format('H:i');
        if ($fim !== NULL && $fim->format('H:i') !== '00:00') {
          $timeStr .= ' - ' . $fim->format('H:i');
        }
      }

      $selectedDayEventsProps[] = [
        'title' => (string) ($ev['titulo'] ?? ''),
        'time' => $timeStr,
        'location' => !empty($ev['local']) ? (string) $ev['local'] : NULL,
        'url' => !empty($ev['link']) ? (string) $ev['link'] : NULL,
        'is_cancelled' => (bool) ($ev['cancelada'] ?? FALSE),
      ];
    }

    $selectedDateLabel = (string) $this->t('Eventos do dia');
    if ($diaSelecionado !== NULL) {
      try {
        $selDt = new \DateTimeImmutable($diaSelecionado, $fuso);
        $selectedDateLabel = (string) $this->t('Eventos de @data', ['@data' => $selDt->format('d/m/Y')]);
      }
      catch (\Throwable) {
      }
    }

    $canAdd = $this->currentUser->isAuthenticated();
    $addUrl = NULL;
    if ($canAdd) {
      try {
        $addUrl = Url::fromRoute('aculta_events.organizer_event_contribution_add')->toString();
      }
      catch (\Throwable) {
        $addUrl = '/culturas/propostas/evento/nova';
      }
    }

    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:studio-calendar',
      '#props' => [
        'title' => (string) $this->t('Calendário'),
        'current_month_label' => $grid['rotulo'],
        'previous_url' => $prevUrl,
        'next_url' => $nextUrl,
        'active_filter' => $filtroValido,
        'filters' => $filtrosProps,
        'weeks' => $weeksProps,
        'selected_date_label' => $selectedDateLabel,
        'selected_day_events' => $selectedDayEventsProps,
        'can_add_event' => $canAdd,
        'add_event_url' => $addUrl,
      ],
      '#attached' => [
        'library' => [
          'core/drupal.htmx',
          'aculta_studio/calendar',
        ],
      ],
    ];
  }

  /**
   * Constrói URL de filtro/navegação do calendário.
   */
  private function buildCalendarUrl(int $ano, int $mes, string $filtro, ?string $dia): string {
    $params = [
      'ano' => $ano,
      'mes' => $mes,
      'filtro' => $filtro,
    ];
    if ($dia !== NULL) {
      $params['dia'] = $dia;
    }

    try {
      return Url::fromRoute('aculta_studio.calendar_fragment', [], ['query' => $params])->toString();
    }
    catch (\Throwable) {
      return '/estudio/calendario-fragmento?' . http_build_query($params);
    }
  }

}
