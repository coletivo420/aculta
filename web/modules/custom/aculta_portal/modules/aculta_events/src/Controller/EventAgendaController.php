<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Agenda\EventAgenda;
use Drupal\aculta_events\Agenda\MonthGrid;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Agenda pública (/calendario): texto, sem JavaScript, por dia, em dd/mm/aaaa e horário de Brasília.
 *
 * Visões: agenda (lista por dia), dia e grade do mês. Período padrão da agenda: hoje
 * a 30 dias; aceita de e ate em dd/mm/aaaa, até 366 dias. Filtros iguais aos do catálogo
 * (tipo de evento, tipo de local e período).
 */
final class EventAgendaController extends ControllerBase {

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
    private readonly EventAgenda $agenda,
    private readonly MonthGrid $grade,
    private readonly TimeInterface $tempo,
    private readonly RequestStack $pilha,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_events.agenda'),
      $container->get('aculta_events.month_grid'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
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
    $consulta = $this->pilha->getCurrentRequest()->query;
    $de = $this->dataBr((string) $consulta->get('de'), $fuso) ?? $agora->setTime(0, 0);
    $ate = $this->dataBr((string) $consulta->get('ate'), $fuso) ?? $de->modify('+30 days');
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

    return $this->renderAgenda(
      $de,
      $ate,
      $this->tipo(),
      $this->tipoLocal(),
      $aviso,
      $incorporado,
      $agora,
    );
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
    return $this->renderAgenda($de, $de->setTime(23, 59, 59), $this->tipo(), $this->tipoLocal(), '', FALSE, $this->agora($fuso));
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
    $grade = $this->grade->build($ano, $mes, $this->agenda->dias($primeiro, $ultimo, $agora, $this->tipo(), $this->tipoLocal()), $agora);

    return [
      'navegacao' => ['#markup' => Markup::create($this->navegacaoGrade($grade, $this->tipo(), $this->tipoLocal()))],
      'filtros' => ['#markup' => Markup::create($this->formularioFiltros($this->tipo(), $this->tipoLocal(), $primeiro, $ultimo, FALSE, 'aculta_events.agenda'))],
      'grade' => ['#markup' => Markup::create($this->renderGrade($grade))],
      '#attached' => ['library' => ['aculta_events/agenda']],
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
   * Agenda (lista por dia) de [$de, $ate].
   */
  private function renderAgenda(\DateTimeImmutable $de, \DateTimeImmutable $ate, ?int $tipo, ?int $tipoLocal, string $aviso, bool $incorporado, \DateTimeImmutable $agora): array {
    $saida = [];
    foreach ($this->agenda->dias($de, $ate, $agora, $tipo, $tipoLocal) as $dia => $itens) {
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
      $exportarDia = Url::fromRoute('aculta_events.ics', [], ['query' => ['de' => $data->format('d/m/Y'), 'ate' => $data->format('d/m/Y')]]);
      $saida[] = [
        '#markup' => Markup::create('<h2>' . Html::escape($cabecalho) . '</h2><ul>' . $linhas . '</ul>'
          . '<p><a href="' . Html::escape($exportarDia->toString()) . '">' . Html::escape((string) $this->t('Exportar o dia @data (.ics)', ['@data' => $data->format('d/m/Y')])) . '</a></p>'),
      ];
    }

    return [
      // A incorporação é uma agenda para terceiros: sem filtros nem navegação do site.
      'filtros' => ['#markup' => Markup::create($incorporado ? '' : $this->formularioFiltros($tipo, $tipoLocal, $de, $ate, TRUE, 'aculta_events.agenda'))],
      'navegacao' => ['#markup' => Markup::create($incorporado ? '' : $this->navegacaoAgenda($de, $ate, $tipo, $tipoLocal))],
      '#prefix' => $aviso,
      'dias' => $saida ?: ['#markup' => '<p>' . $this->t('Nenhum evento neste período.') . '</p>'],
      '#attached' => ['library' => ['aculta_events/agenda']],
      '#cache' => $this->cacheVisao(),
    ];
  }

  /**
   * Links da agenda: grade do mês e exportação do período filtrado.
   */
  private function navegacaoAgenda(\DateTimeImmutable $de, \DateTimeImmutable $ate, ?int $tipo, ?int $tipoLocal): string {
    $consulta = $this->consulta($tipo, $tipoLocal);
    $urlMes = Url::fromRoute('aculta_events.month', ['ano' => (int) $de->format('Y'), 'mes' => (int) $de->format('n')], ['query' => $consulta]);
    $consultaExportacao = $consulta;
    $consultaExportacao['de'] = $de->format('d/m/Y');
    $consultaExportacao['ate'] = $ate->format('d/m/Y');
    $urlIcs = Url::fromRoute('aculta_events.ics', [], ['query' => $consultaExportacao]);
    return '<p class="aculta-agenda__acoes">'
      . '<a href="' . Html::escape($urlMes->toString()) . '">' . Html::escape((string) $this->t('Ver a grade do mês')) . '</a>'
      . ' · <a href="' . Html::escape($urlIcs->toString()) . '">' . Html::escape((string) $this->t('Exportar o período (.ics)')) . '</a>'
      . '</p>';
  }

  /**
   * Links da grade: mês anterior, agenda e próximo mês, preservando os filtros.
   */
  private function navegacaoGrade(array $grade, ?int $tipo, ?int $tipoLocal): string {
    $consulta = $this->consulta($tipo, $tipoLocal);
    $anterior = Url::fromRoute('aculta_events.month', ['ano' => $grade['anterior']['ano'], 'mes' => $grade['anterior']['mes']], ['query' => $consulta]);
    $seguinte = Url::fromRoute('aculta_events.month', ['ano' => $grade['seguinte']['ano'], 'mes' => $grade['seguinte']['mes']], ['query' => $consulta]);
    $agenda = Url::fromRoute('aculta_events.agenda', [], ['query' => $consulta]);
    return '<p class="aculta-agenda__acoes">'
      . '<a href="' . Html::escape($anterior->toString()) . '">« ' . Html::escape((string) $this->t('Mês anterior')) . '</a>'
      . ' · <a href="' . Html::escape($agenda->toString()) . '">' . Html::escape((string) $this->t('Ver a agenda')) . '</a>'
      . ' · <a href="' . Html::escape($seguinte->toString()) . '">' . Html::escape((string) $this->t('Próximo mês')) . ' »</a>'
      . '</p>';
  }

  /**
   * Grade do mês em tabela semântica: caption, th de coluna e link por dia.
   */
  private function renderGrade(array $grade): string {
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
          $url = Url::fromRoute('aculta_events.day', [
            'ano' => (int) $celula['dia']->format('Y'),
            'mes' => (int) $celula['dia']->format('n'),
            'dia' => (int) $celula['dia']->format('j'),
          ], ['query' => $this->consulta($this->tipo(), $this->tipoLocal())]);
          $primeiro = (string) $celula['itens'][0]['titulo'];
          $rotulo = $celula['dia']->format('d/m/Y') . ': ' . ($quantidade === 1 ? $primeiro : $quantidade . ' eventos, primeiro: ' . $primeiro);
          $html .= '<a class="aculta-mes__link" href="' . Html::escape($url->toString()) . '" aria-label="' . Html::escape((string) $this->t('Ver a agenda de @rotulo', ['@rotulo' => $rotulo])) . '">'
            . '<span class="aculta-mes__contagem">' . Html::escape((string) $this->t('@n evento(s)', ['@n' => $quantidade])) . '</span>'
            . '<span class="aculta-mes__eventos">' . Html::escape($primeiro) . ($quantidade > 1 ? ' <span>+' . ($quantidade - 1) . '</span>' : '') . '</span>'
            . '</a>';
        }
        $html .= '</td>';
      }
      $html .= '</tr>';
    }
    return $html . '</tbody></table>';
  }

  /**
   * Formulário de filtros (GET), com seleção de tipo de evento e de tipo de local.
   */
  private function formularioFiltros(?int $tipo, ?int $tipoLocal, \DateTimeImmutable $de, \DateTimeImmutable $ate, bool $comPeriodo, string $rotaLimpar): string {
    $periodo = '';
    if ($comPeriodo) {
      $periodo = '<label>De <input type="text" name="de" value="' . Html::escape($de->format('d/m/Y')) . '" placeholder="dd/mm/aaaa" inputmode="numeric" size="10"></label>'
        . '<label>Até <input type="text" name="ate" value="' . Html::escape($ate->format('d/m/Y')) . '" placeholder="dd/mm/aaaa" inputmode="numeric" size="10"></label>';
    }
    else {
      $periodo = '<input type="hidden" name="de" value="' . Html::escape($de->format('d/m/Y')) . '">'
        . '<input type="hidden" name="ate" value="' . Html::escape($ate->format('d/m/Y')) . '">';
    }
    $limpar = Url::fromRoute($rotaLimpar);
    return '<form class="aculta-agenda__filtros" method="get">'
      . '<label>Tipo de evento <select name="tipo"><option value="">Todos</option>' . $this->opcoes('aculta_event_type', $tipo) . '</select></label>'
      . '<label>Tipo de local <select name="tipo_local"><option value="">Todos</option>' . $this->opcoes('aculta_location_type', $tipoLocal) . '</select></label>'
      . $periodo
      . '<button type="submit">' . Html::escape((string) $this->t('Filtrar')) . '</button>'
      . ' <a href="' . Html::escape($limpar->toString()) . '">' . Html::escape((string) $this->t('Limpar filtros')) . '</a>'
      . '</form>';
  }

  /**
   * Opções de um vocabulário, com o termo pedido selecionado.
   */
  private function opcoes(string $vid, ?int $selecionado): string {
    $html = '';
    foreach ($this->entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid) as $termo) {
      $html .= '<option value="' . (int) $termo->tid . '"' . ((int) $termo->tid === $selecionado ? ' selected' : '') . '>' . Html::escape((string) $termo->name) . '</option>';
    }
    return $html;
  }

  /**
   * Consulta dos filtros preservada nos links.
   *
   * @return array<string, int>
   */
  private function consulta(?int $tipo, ?int $tipoLocal): array {
    return array_filter(['tipo' => $tipo, 'tipo_local' => $tipoLocal], static fn (?int $valor): bool => $valor !== NULL);
  }

  /**
   * Parâmetros de cache das visões: filtro na URL e listas de evento e de termo.
   */
  private function cacheVisao(): array {
    return ['contexts' => ['url.query_args'], 'tags' => ['aculta_event_date_list', 'taxonomy_term_list'], 'max-age' => 900];
  }

  /**
   * Filtro `tipo` da URL (termo de aculta_event_type), ou NULL.
   */
  private function tipo(): ?int {
    return $this->pilha->getCurrentRequest()->query->getInt('tipo') ?: NULL;
  }

  /**
   * Filtro `tipo_local` da URL (termo de aculta_location_type), ou NULL.
   */
  private function tipoLocal(): ?int {
    return $this->pilha->getCurrentRequest()->query->getInt('tipo_local') ?: NULL;
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
