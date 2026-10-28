<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Controller;

use Drupal\aculta_calendar\Agenda\CalendarAgenda;
use Drupal\aculta_calendar\Agenda\MonthGrid;
use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Agenda pública do calendário: lista por dia, dia e grade do mês (CA4).
 *
 * O módulo que publica a página define as rotas (ADR-031) e informa os nomes na
 * opção `_aculta_calendar_rotas`; o conteúdo vem dos provedores de itens.
 * Período padrão: hoje a 30 dias; aceita de e ate em dd/mm/aaaa, até 366 dias.
 */
final class AgendaController extends ControllerBase {

  /**
   * Dias da semana em português, indexados pelo número ISO (1 = segunda).
   */
  private const DIAS_SEMANA = [1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado', 7 => 'domingo'];

  /**
   * Cabeçalho curto da grade do mês (também é o texto do `scope="col"`).
   */
  private const COLUNAS = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];

  /**
   * Construtor.
   */
  public function __construct(
    private readonly CalendarItemSource $fonte,
    private readonly CalendarAgenda $agenda,
    private readonly MonthGrid $grade,
    private readonly TimeInterface $tempo,
    private readonly RequestStack $pilha,
    private readonly RouteMatchInterface $rotaAtual,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_calendar.item_source'),
      $container->get('aculta_calendar.agenda'),
      $container->get('aculta_calendar.month_grid'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
      $container->get('current_route_match'),
    );
  }

  /**
   * Página da agenda.
   */
  public function page(): array {
    return $this->conteudo(FALSE);
  }

  /**
   * Conteúdo da agenda, com ou sem a navegação do site (incorporação).
   */
  public function conteudo(bool $incorporado): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = $this->agora($fuso);
    $consulta = $this->pilha->getCurrentRequest()?->query;
    $de = $this->dataBr((string) $consulta?->get('de'), $fuso) ?? $agora->setTime(0, 0);
    $ate = $this->dataBr((string) $consulta?->get('ate'), $fuso) ?? $de->modify('+30 days');
    $ate = $ate->setTime(23, 59, 59);
    $aviso = '';
    if ($ate < $de) {
      $aviso = '<p role="alert">Período inválido: a data final é anterior à inicial.</p>';
      $ate = $de->setTime(23, 59, 59);
    }
    elseif ($de->diff($ate)->days > 366) {
      $aviso = '<p role="alert">Período acima de 366 dias. Reduza o intervalo.</p>';
      $ate = $de->modify('+366 days')->setTime(23, 59, 59);
    }

    return $this->renderAgenda(new CalendarQuery($de, $ate, $agora, $this->filtrosDaUrl()), $incorporado, $aviso);
  }

  /**
   * Agenda de um dia (/calendario/dia/{ano}/{mes}/{dia}), fonte do link da grade.
   */
  public function dia(int $ano, int $mes, int $dia): array {
    if (!checkdate($mes, $dia, $ano) || $ano < 1900 || $ano > 2200) {
      throw new NotFoundHttpException();
    }
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $de = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano, $mes, $dia), $fuso);
    return $this->renderAgenda(new CalendarQuery($de, $de->setTime(23, 59, 59), $this->agora($fuso), $this->filtrosDaUrl()), FALSE, '');
  }

  /**
   * Grade de um mês (/calendario/mes/{ano}/{mes}).
   */
  public function mes(int $ano, int $mes): array {
    if ($mes < 1 || $mes > 12 || $ano < 1900 || $ano > 2200) {
      throw new NotFoundHttpException();
    }
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $primeiro = new \DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes), $fuso);
    $ultimo = $primeiro->modify('last day of this month')->setTime(23, 59, 59);
    $agora = $this->agora($fuso);
    $query = new CalendarQuery($primeiro, $ultimo, $agora, $this->filtrosDaUrl());
    $grade = $this->grade->build($ano, $mes, $this->agenda->dias($query), $agora);

    return [
      'navegacao' => ['#markup' => Markup::create($this->navegacaoGrade($grade, $query))],
      'filtros' => ['#markup' => Markup::create($this->formularioFiltros($query, FALSE, $this->url('agenda')))],
      'grade' => ['#markup' => Markup::create($this->renderGrade($grade, $query))],
      '#attached' => ['library' => ['aculta_calendar/agenda']],
      '#cache' => $this->cacheVisao(),
    ];
  }

  /**
   * Título da grade do mês.
   */
  public function tituloMes(int $ano, int $mes): string {
    $nomes = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];
    return (string) $this->t('Calendário — @mes de @ano', ['@mes' => $nomes[$mes] ?? '', '@ano' => $ano]);
  }

  /**
   * Título da agenda de um dia.
   */
  public function tituloDia(int $ano, int $mes, int $dia): string {
    return (string) $this->t('Calendário — @data', ['@data' => sprintf('%02d/%02d/%04d', $dia, $mes, $ano)]);
  }

  /**
   * Agenda (lista por dia) do período da consulta.
   */
  private function renderAgenda(CalendarQuery $query, bool $incorporado, string $aviso): array {
    $saida = [];
    foreach ($this->agenda->dias($query) as $dia => $itens) {
      $data = new \DateTimeImmutable($dia, new \DateTimeZone('America/Sao_Paulo'));
      $cabecalho = $data->format('d/m/Y') . ' — ' . self::DIAS_SEMANA[(int) $data->format('N')];
      $linhas = '';
      foreach ($itens as $item) {
        $linha = Html::escape($item['inicio']->format('H:i') . ' às ' . $item['fim']->format('H:i') . ' — ' . $item['titulo']);
        if ($item['local'] !== '') {
          $linha .= Html::escape(' · ' . $item['local']);
        }
        if ($item['cancelada']) {
          $linha .= ' <strong>(cancelado)</strong>';
        }
        $linhas .= '<li>' . $linha . '</li>';
      }
      $icsDia = $this->url('ics', [], ['de' => $data->format('d/m/Y'), 'ate' => $data->format('d/m/Y')]);
      $exportar = $icsDia === NULL ? '' : '<p><a href="' . Html::escape($icsDia) . '">' . Html::escape((string) $this->t('Exportar o dia @data (.ics)', ['@data' => $data->format('d/m/Y')])) . '</a></p>';
      $saida[] = [
        '#markup' => Markup::create('<h2>' . Html::escape($cabecalho) . '</h2><ul>' . $linhas . '</ul>' . $exportar),
      ];
    }

    return [
      // A incorporação é uma agenda para terceiros: sem filtros nem navegação do site.
      'filtros' => ['#markup' => Markup::create($incorporado ? '' : $this->formularioFiltros($query, TRUE, $this->url('agenda')))],
      'navegacao' => ['#markup' => Markup::create($incorporado ? '' : $this->navegacaoAgenda($query))],
      '#prefix' => $aviso,
      'dias' => $saida ?: ['#markup' => '<p>' . $this->t('Nenhum evento neste período.') . '</p>'],
      '#attached' => ['library' => ['aculta_calendar/agenda']],
      '#cache' => $this->cacheVisao(),
    ];
  }

  /**
   * Links da agenda: grade do mês e exportação do período filtrado.
   */
  private function navegacaoAgenda(CalendarQuery $query): string {
    $consultaLinks = $query->filtros;
    $mes = $this->url('month', ['ano' => (int) $query->de->format('Y'), 'mes' => (int) $query->de->format('n')], $consultaLinks);
    $ics = $this->url('ics', [], $consultaLinks + ['de' => $query->de->format('d/m/Y'), 'ate' => $query->ate->format('d/m/Y')]);
    $links = [];
    if ($mes !== NULL) {
      $links[] = '<a href="' . Html::escape($mes) . '">' . Html::escape((string) $this->t('Ver a grade do mês')) . '</a>';
    }
    if ($ics !== NULL) {
      $links[] = '<a href="' . Html::escape($ics) . '">' . Html::escape((string) $this->t('Exportar o período (.ics)')) . '</a>';
    }
    $interativo = $this->url('interactive');
    if ($interativo !== NULL) {
      $links[] = '<a href="' . Html::escape($interativo) . '">' . Html::escape((string) $this->t('Visão interativa')) . '</a>';
    }
    return $links === [] ? '' : '<p class="aculta-agenda__acoes">' . implode(' · ', $links) . '</p>';
  }

  /**
   * Links da grade: mês anterior, agenda e próximo mês, preservando os filtros.
   */
  private function navegacaoGrade(array $grade, CalendarQuery $query): string {
    $consulta = $query->filtros;
    $anterior = $this->url('month', ['ano' => $grade['anterior']['ano'], 'mes' => $grade['anterior']['mes']], $consulta);
    $seguinte = $this->url('month', ['ano' => $grade['seguinte']['ano'], 'mes' => $grade['seguinte']['mes']], $consulta);
    $agenda = $this->url('agenda', [], $consulta);
    $links = [];
    if ($anterior !== NULL) {
      $links[] = '<a href="' . Html::escape($anterior) . '">« ' . Html::escape((string) $this->t('Mês anterior')) . '</a>';
    }
    if ($agenda !== NULL) {
      $links[] = '<a href="' . Html::escape($agenda) . '">' . Html::escape((string) $this->t('Ver a agenda')) . '</a>';
    }
    if ($seguinte !== NULL) {
      $links[] = '<a href="' . Html::escape($seguinte) . '">' . Html::escape((string) $this->t('Próximo mês')) . ' »</a>';
    }
    return $links === [] ? '' : '<p class="aculta-agenda__acoes">' . implode(' · ', $links) . '</p>';
  }

  /**
   * Grade do mês em tabela semântica: caption, th de coluna e link por dia.
   */
  private function renderGrade(array $grade, CalendarQuery $query): string {
    $html = '<table class="aculta-mes"><caption>' . Html::escape($grade['rotulo']) . '</caption><thead><tr>';
    foreach (self::COLUNAS as $coluna) {
      $html .= '<th scope="col">' . Html::escape($coluna) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($grade['semanas'] as $semana) {
      $html .= '<tr>';
      foreach ($semana as $celula) {
        if ($celula['dia'] === NULL) {
          $html .= '<td class="aculta-mes__fora"></td>';
          continue;
        }
        $html .= '<td class="aculta-mes__celula' . ($celula['hoje'] ? ' aculta-mes__hoje' : '') . '"' . ($celula['hoje'] ? ' aria-current="date"' : '') . '>';
        $html .= '<span class="aculta-mes__numero">' . $celula['dia']->format('j') . '</span>';
        $quantidade = count($celula['itens']);
        if ($quantidade > 0) {
          $url = $this->url('day', [
            'ano' => (int) $celula['dia']->format('Y'),
            'mes' => (int) $celula['dia']->format('n'),
            'dia' => (int) $celula['dia']->format('j'),
          ], $query->filtros);
          $primeiro = (string) $celula['itens'][0]['titulo'];
          $rotulo = $celula['dia']->format('d/m/Y') . ': ' . ($quantidade === 1 ? $primeiro : $quantidade . ' eventos, primeiro: ' . $primeiro);
          $conteudo = '<span class="aculta-mes__contagem">' . Html::escape((string) $this->t('@n evento(s)', ['@n' => $quantidade])) . '</span>'
            . '<span class="aculta-mes__eventos">' . Html::escape($primeiro) . ($quantidade > 1 ? ' <span>+' . ($quantidade - 1) . '</span>' : '') . '</span>';
          $html .= $url === NULL
            ? $conteudo
            : '<a class="aculta-mes__link" href="' . Html::escape($url) . '" aria-label="' . Html::escape((string) $this->t('Ver a agenda de @rotulo', ['@rotulo' => $rotulo])) . '">' . $conteudo . '</a>';
        }
        $html .= '</td>';
      }
      $html .= '</tr>';
    }
    return $html . '</tbody></table>';
  }

  /**
   * Formulário de filtros (GET), com as definições dos provedores e o período.
   */
  private function formularioFiltros(CalendarQuery $query, bool $comPeriodo, ?string $urlLimpar): string {
    $campos = '';
    foreach ($this->fonte->filtros() as $chave => $definicao) {
      $selecionado = $query->filtros[$chave] ?? NULL;
      $opcoes = '<option value="">Todos</option>';
      foreach ($definicao['opcoes'] as $id => $rotulo) {
        $opcoes .= '<option value="' . (int) $id . '"' . ((int) $id === $selecionado ? ' selected' : '') . '>' . Html::escape((string) $rotulo) . '</option>';
      }
      $campos .= '<label>' . Html::escape((string) $definicao['label']) . ' <select name="' . Html::escape($chave) . '">' . $opcoes . '</select></label>';
    }
    if ($comPeriodo) {
      $campos .= '<label>De <input type="text" name="de" value="' . Html::escape($query->de->format('d/m/Y')) . '" placeholder="dd/mm/aaaa" inputmode="numeric" size="10"></label>'
        . '<label>Até <input type="text" name="ate" value="' . Html::escape($query->ate->format('d/m/Y')) . '" placeholder="dd/mm/aaaa" inputmode="numeric" size="10"></label>';
    }
    else {
      $campos .= '<input type="hidden" name="de" value="' . Html::escape($query->de->format('d/m/Y')) . '">'
        . '<input type="hidden" name="ate" value="' . Html::escape($query->ate->format('d/m/Y')) . '">';
    }
    if ($campos === '') {
      return '';
    }
    $limpar = $urlLimpar === NULL ? '' : ' <a href="' . Html::escape($urlLimpar) . '">' . Html::escape((string) $this->t('Limpar filtros')) . '</a>';
    return '<form class="aculta-agenda__filtros" method="get">' . $campos
      . '<button type="submit">' . Html::escape((string) $this->t('Filtrar')) . '</button>' . $limpar . '</form>';
  }

  /**
   * Filtros da URL, para as chaves que os provedores definem.
   *
   * @return array<string, int>
   */
  private function filtrosDaUrl(): array {
    $request = $this->pilha->getCurrentRequest();
    if ($request === NULL) {
      return [];
    }
    $filtros = [];
    foreach (array_keys($this->fonte->filtros()) as $chave) {
      $valor = $request->query->getInt($chave);
      if ($valor > 0) {
        $filtros[$chave] = $valor;
      }
    }
    return $filtros;
  }

  /**
   * URL de uma rota do publisher, informada na opção `_aculta_calendar_rotas`.
   */
  private function url(string $chave, array $parametros = [], array $consulta = []): ?string {
    $rotas = $this->rotaAtual->getRouteObject()?->getOption('_aculta_calendar_rotas') ?? [];
    $nome = is_array($rotas) && is_string($rotas[$chave] ?? NULL) ? $rotas[$chave] : NULL;
    return $nome === NULL ? NULL : Url::fromRoute($nome, $parametros, ['query' => $consulta])->toString();
  }

  /**
   * Cache das visões: filtro na URL, lista genérica e tags dos provedores.
   */
  private function cacheVisao(): array {
    return [
      'contexts' => ['url.query_args'],
      'tags' => array_merge(['aculta_calendar_item_list'], $this->fonte->cacheTags()),
      'max-age' => 900,
    ];
  }

  /**
   * Agora, em horário de Brasília.
   */
  private function agora(\DateTimeZone $fuso): \DateTimeImmutable {
    return (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
  }

  /**
   * Data em dd/mm/aaaa, de Brasília; vazio ou inválido vira NULL (usa o padrão).
   */
  private function dataBr(string $valor, \DateTimeZone $fuso): ?\DateTimeImmutable {
    if ($valor === '') {
      return NULL;
    }
    $data = \DateTimeImmutable::createFromFormat('!d/m/Y', $valor, $fuso);
    return ($data === FALSE || $data->format('d/m/Y') !== $valor) ? NULL : $data;
  }

}
