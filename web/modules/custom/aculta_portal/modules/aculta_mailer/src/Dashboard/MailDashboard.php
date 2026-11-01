<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Dashboard;

use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Contagens de envios por período, estado, tipo e transporte, agregadas no banco (aculta_mail_log).
 *
 * Só leitura e contagem: não carrega registros para contar, e não lista destinatários nem conteúdo.
 */
final class MailDashboard {

  /**
   * Períodos oferecidos no painel, em dias.
   */
  public const PERIODS = [1, 7, 30];

  /**
   * Períodos selecionáveis no gráfico e nas tabelas, em dias.
   */
  public const CHART_PERIODS = [7, 14, 30];

  /**
   * Filtros de tipo do painel e os estados de cada um. `todos` não restringe.
   *
   * `confirmados`: entrega confirmada pelo provedor. `nao_confirmados`: ainda sem confirmação (na fila,
   * processando, aceito ou adiado). `falha`: falha de envio, devolução ou reclamação. `cancelled` só aparece
   * em `todos`.
   */
  public const FILTERS = [
    'todos' => NULL,
    'confirmados' => ['delivered'],
    'nao_confirmados' => ['queued', 'processing', 'sent', 'deferred'],
    'falha' => ['failed', 'bounced', 'complained'],
  ];

  /**
   * Tabela de registros (mesmo nome da entidade aculta_mail_log).
   */
  private const TABLE = MailLogRecorder::ENTITY_TYPE;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  /**
   * Resumo de um período: total, contagem por estado, por tipo e por transporte.
   *
   * @param int $since
   *   Instante inicial, em timestamp Unix.
   *
   * @return array
   *   Chaves `total`, `by_status`, `by_type`, `by_transport` e `by_error`.
   */
  public function summary(int $since): array {
    return [
      'total' => $this->count($since),
      'by_status' => $this->groupBy($since, 'status', array_map(static fn(MailStatus $s) => $s->value, MailStatus::cases())),
      'by_type' => $this->groupBy($since, 'type'),
      'by_transport' => $this->groupBy($since, 'transport'),
      'by_error' => $this->errorsByCode($since),
    ];
  }

  /**
   * Falhas recentes com o texto completo do erro, para o relatório do admin. Sem destinatário em campo próprio.
   *
   * @param int $since
   *   Instante inicial, em timestamp Unix.
   * @param int $limit
   *   Máximo de linhas.
   *
   * @return array
   *   Linhas com created, type, correlation_id, transport, attempts, error_code e error_message.
   */
  public function recentFailures(int $since, int $limit = 100): array {
    $ids = $this->query($since)
      ->condition('status', MailStatus::Failed->value)
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();
    $rows = [];
    foreach ($this->entityTypeManager->getStorage(MailLogRecorder::ENTITY_TYPE)->loadMultiple($ids) as $log) {
      $rows[] = [
        'id' => (string) $log->id(),
        'created' => (int) $log->get('created')->value,
        'type' => (string) $log->get('type')->value,
        'correlation_id' => (string) $log->get('correlation_id')->value,
        'transport' => (string) $log->get('transport')->value,
        'attempts' => (int) $log->get('attempts')->value,
        'error_code' => (string) $log->get('error_code')->value,
        'error_message' => (string) $log->get('error_message')->value,
        // O conteúdo cifrado não sai daqui: só se existe, para mostrar o link de reenvio.
        'resendable' => (string) $log->get('payload_cipher')->value !== '',
      ];
    }
    return $rows;
  }

  /**
   * Falhas por código de erro (ex.: `smtp_550`), para o administrador ver o motivo. Sem destinatário.
   *
   * @return array
   *   Contagem por código de erro.
   */
  private function errorsByCode(int $since): array {
    return $this->groupBy($since, 'error_code', [], MailStatus::Failed->value, '(sem código)');
  }

  /**
   * Linhas do registro para exportação (CSV), mais recentes primeiro. Sem destinatário nem texto de erro.
   *
   * @param int $since
   *   Instante inicial, em timestamp Unix.
   * @param int $limit
   *   Máximo de linhas.
   *
   * @return array
   *   Linhas com created, type, status, transport, attempts, error_code e correlation_id.
   */
  public function exportRows(int $since, int $limit = 10000): array {
    $rows = $this->table()
      ->fields('log', ['created', 'type', 'status', 'transport', 'attempts', 'error_code', 'correlation_id'])
      ->condition('log.created', $since, '>=')
      ->orderBy('log.created', 'DESC')
      ->range(0, $limit)
      ->execute();
    return array_map(static fn(object $row): array => (array) $row, $rows->fetchAll());
  }

  /**
   * Contagem de envios por dia UTC desde o instante, opcionalmente restrita a estados.
   *
   * @param int $since
   *   Instante inicial, em timestamp Unix.
   * @param string[]|null $statuses
   *   Estados a contar; NULL conta todos.
   *
   * @return array<int, int>
   *   Número do dia UTC (timestamp dividido por 86400) para a quantidade de envios.
   */
  public function dailyCounts(int $since, ?array $statuses = NULL): array {
    $query = $this->table()
      ->fields('log', ['created'])
      ->condition('log.created', $since, '>=');
    if ($statuses !== NULL) {
      $query->condition('log.status', $statuses, 'IN');
    }
    $days = [];
    foreach ($query->execute() as $row) {
      $day = intdiv((int) $row->created, 86400);
      $days[$day] = ($days[$day] ?? 0) + 1;
    }
    ksort($days);
    return $days;
  }

  /**
   * Total de registros criados a partir do instante informado.
   */
  private function count(int $since): int {
    return (int) $this->table()->condition('log.created', $since, '>=')->countQuery()->execute()->fetchField();
  }

  /**
   * Contagem por campo, no banco. Valores conhecidos aparecem com zero quando não há registros.
   *
   * @param int $since
   *   Instante inicial, em timestamp Unix.
   * @param string $field
   *   Coluna a agrupar (ex.: status, type, transport, error_code).
   * @param string[] $known
   *   Valores que devem aparecer mesmo sem registros.
   * @param string|null $onlyStatus
   *   Se informado, conta só os registros nesse estado.
   * @param string|null $emptyLabel
   *   Rótulo para valor vazio.
   *
   * @return array
   *   Contagem por valor do campo, em ordem de chave.
   */
  private function groupBy(int $since, string $field, array $known = [], ?string $onlyStatus = NULL, ?string $emptyLabel = NULL): array {
    $counts = array_fill_keys($known, 0);
    $query = $this->table()
      ->condition('log.created', $since, '>=')
      ->groupBy('log.' . $field);
    $query->addField('log', $field, 'value');
    $query->addExpression('COUNT(*)', 'total');
    if ($onlyStatus !== NULL) {
      $query->condition('log.status', $onlyStatus);
    }
    foreach ($query->execute() as $row) {
      $value = (string) $row->value;
      if ($value === '' && $emptyLabel !== NULL) {
        $value = $emptyLabel;
      }
      $counts[$value] = ($counts[$value] ?? 0) + (int) $row->total;
    }
    ksort($counts);
    return $counts;
  }

  /**
   * Consulta de entidades dos registros criados a partir do instante (relatório de falhas, limitado).
   */
  private function query(int $since): QueryInterface {
    return $this->entityTypeManager->getStorage(MailLogRecorder::ENTITY_TYPE)->getQuery()
      ->accessCheck(FALSE)
      ->condition('created', $since, '>=');
  }

  /**
   * Tabela de registros de envio, para consulta agregada.
   */
  private function table(): SelectInterface {
    return $this->database->select(self::TABLE, 'log');
  }

}
