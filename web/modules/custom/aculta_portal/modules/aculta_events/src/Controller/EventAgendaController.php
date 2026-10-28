<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Agenda\EventAgenda;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Agenda pública (/calendario): texto, sem JavaScript, por dia, em dd/mm/aaaa e horário de Brasília.
 *
 * Período padrão: hoje a 30 dias; aceita de e ate em dd/mm/aaaa, até 366 dias.
 */
final class EventAgendaController extends ControllerBase {

  /**
   * Dias da semana em português, indexados pelo número ISO (1 = segunda).
   */
  private const DIAS_SEMANA = [1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado', 7 => 'domingo'];

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EventAgenda $agenda,
    private readonly TimeInterface $tempo,
    private readonly RequestStack $pilha,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_events.agenda'), $container->get('datetime.time'), $container->get('request_stack'));
  }

  /**
   * Página da agenda.
   */
  public function page(): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
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

    $grupos = $this->agenda->dias($de, $ate, $agora);
    $saida = [];
    foreach ($grupos as $dia => $itens) {
      $data = new \DateTimeImmutable($dia, $fuso);
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
      $saida[] = ['#markup' => '<h2>' . Html::escape($cabecalho) . '</h2><ul>' . $linhas . '</ul>'];
    }

    return [
      '#prefix' => $aviso,
      'dias' => $saida ?: ['#markup' => '<p>' . $this->t('Nenhum evento neste período.') . '</p>'],
      '#cache' => ['contexts' => ['url.query_args'], 'tags' => ['aculta_event_date_list'], 'max-age' => 900],
    ];
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
