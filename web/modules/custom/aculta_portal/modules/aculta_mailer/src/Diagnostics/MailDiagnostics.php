<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Diagnostics;

use Drupal\aculta_mailer\Delivery\TransportFailover;
use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_mailer\Queue\MailQueue;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;

/**
 * Estado do envio para o administrador: transportes configurados, último envio e falha, fila e cron.
 *
 * Nunca expõe usuário ou senha do transporte: só informa se estão preenchidos.
 */
final class MailDiagnostics {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    private readonly QueueFactory $queues,
    private readonly TransportFailover $failover,
  ) {}

  /**
   * Relatório do estado atual.
   *
   * @return array
   *   Chaves `default`, `failover`, `transports`, `queue_items`, `cron_last`.
   */
  public function report(): array {
    $cron = (int) $this->state->get('system.cron_last', 0);
    return [
      'default' => (string) ($this->configFactory->get('mailer_transport.settings')->get('default_transport') ?? ''),
      'failover' => $this->failover->names(),
      'transports' => $this->transports(),
      'queue_items' => $this->queues->get(MailQueue::QUEUE_NAME)->numberOfItems(),
      'cron_last' => $cron,
    ];
  }

  /**
   * Último envio aceito e última falha por transporte, a partir do registro.
   *
   * @return array
   *   Por ID do transporte: `last_sent` e `last_failure` (timestamps ou NULL).
   */
  public function lastEventsByTransport(array $transportIds): array {
    $storage = $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE);
    $events = [];
    foreach ($transportIds as $id) {
      $events[$id] = [
        'last_sent' => $this->latest($storage, $id, MailStatus::Sent->value, 'sent'),
        'last_failure' => $this->latest($storage, $id, MailStatus::Failed->value, 'created'),
      ];
    }
    return $events;
  }

  /**
   * Transportes da camada de envio do Drupal, sem credenciais. Vazio quando o módulo não está disponível.
   *
   * @return array
   *   Linhas por ID do transporte.
   */
  private function transports(): array {
    if (!$this->entityTypes->hasDefinition('mailer_transport')) {
      return [];
    }
    $rows = [];
    foreach ($this->entityTypes->getStorage('mailer_transport')->loadMultiple() as $transport) {
      $configuration = (array) $transport->get('configuration');
      $rows[$transport->id()] = [
        'label' => (string) $transport->label(),
        'plugin' => (string) $transport->get('plugin'),
        'host' => (string) ($configuration['host'] ?? ''),
        'port' => (string) ($configuration['port'] ?? ''),
        'verify_peer' => !empty($configuration['query']['verify_peer']),
        'user_set' => (string) ($configuration['user'] ?? '') !== '',
        'pass_set' => (string) ($configuration['pass'] ?? '') !== '',
        'enabled' => (bool) $transport->status(),
      ];
    }
    $events = $this->lastEventsByTransport(array_keys($rows));
    foreach ($rows as $id => $row) {
      $rows[$id] += $events[$id];
    }
    return $rows;
  }

  /**
   * Timestamp do registro mais recente com o estado e o transporte, ou NULL.
   *
   * @param object $storage
   *   Armazenamento da entidade de registro.
   * @param string $transport
   *   ID do transporte.
   * @param string $status
   *   Estado a procurar.
   * @param string $sortField
   *   Campo de data usado para ordenar.
   */
  private function latest(object $storage, string $transport, string $status, string $sortField): ?int {
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('transport', $transport)
      ->condition('status', $status)
      ->sort($sortField, 'DESC')
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }
    $log = $storage->load(reset($ids));
    return (int) $log->get($sortField)->value ?: NULL;
  }

}
