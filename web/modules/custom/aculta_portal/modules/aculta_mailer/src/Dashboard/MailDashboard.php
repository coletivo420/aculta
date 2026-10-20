<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Dashboard;

use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Contagens de envios por período, estado, tipo e transporte, a partir de aculta_mail_log.
 *
 * Só leitura e contagem: não lista destinatários nem conteúdo.
 */
final class MailDashboard {

  /**
   * Períodos oferecidos no painel, em dias.
   */
  public const PERIODS = [1, 7, 30];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
        'created' => (int) $log->get('created')->value,
        'type' => (string) $log->get('type')->value,
        'correlation_id' => (string) $log->get('correlation_id')->value,
        'transport' => (string) $log->get('transport')->value,
        'attempts' => (int) $log->get('attempts')->value,
        'error_code' => (string) $log->get('error_code')->value,
        'error_message' => (string) $log->get('error_message')->value,
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
    $ids = $this->query($since)->condition('status', MailStatus::Failed->value)->execute();
    $counts = [];
    foreach ($this->entityTypeManager->getStorage(MailLogRecorder::ENTITY_TYPE)->loadMultiple($ids) as $log) {
      $code = (string) $log->get('error_code')->value;
      $counts[$code] = ($counts[$code] ?? 0) + 1;
    }
    ksort($counts);
    return $counts;
  }

  /**
   * Total de registros criados a partir do instante informado.
   */
  private function count(int $since): int {
    return (int) $this->query($since)->count()->execute();
  }

  /**
   * Contagem por campo. Valores conhecidos aparecem com zero quando não há registros.
   *
   * @param int $since
   *   Instante inicial, em timestamp Unix.
   * @param string $field
   *   Campo a agrupar.
   * @param string[] $known
   *   Valores que devem aparecer mesmo sem registros.
   *
   * @return array
   *   Contagem por valor do campo.
   */
  private function groupBy(int $since, string $field, array $known = []): array {
    $counts = array_fill_keys($known, 0);
    $ids = $this->query($since)->execute();
    if ($ids === []) {
      return $counts;
    }
    foreach ($this->entityTypeManager->getStorage(MailLogRecorder::ENTITY_TYPE)->loadMultiple($ids) as $log) {
      $value = (string) $log->get($field)->value;
      $counts[$value] = ($counts[$value] ?? 0) + 1;
    }
    ksort($counts);
    return $counts;
  }

  /**
   * Consulta dos registros criados a partir do instante, sem checagem de acesso (leitura interna).
   */
  private function query(int $since): QueryInterface {
    return $this->entityTypeManager->getStorage(MailLogRecorder::ENTITY_TYPE)->getQuery()
      ->accessCheck(FALSE)
      ->condition('created', $since, '>=');
  }

}
