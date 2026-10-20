<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Report;

use Drupal\aculta_mailer\Delivery\AdministratorRecipients;
use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Queue\MailQueue;
use Drupal\aculta_mailer\Type\WeeklySummaryMailType;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Resumo semanal por e-mail, com os 7 dias anteriores como comparação (modelo "Email Reports" do WP Mail SMTP).
 *
 * Conta o registro de envios (não o provedor). Um envio por semana, pelo cron.
 */
final class MailSummary {

  /**
   * Intervalo entre resumos, em segundos.
   */
  public const INTERVAL = 604800;

  /**
   * Teto de registros lidos por contagem, para não sobrecarregar o cron em volume alto.
   */
  public const LIMIT = 5000;

  private const STATE_LAST = 'aculta_mailer.summary.last';

  private const STATUSES = ['sent', 'delivered', 'failed', 'bounced', 'deferred', 'cancelled'];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly MailQueue $queue,
    private readonly UuidInterface $uuid,
    private readonly AdministratorRecipients $administrators,
  ) {}

  /**
   * Envia o resumo quando venceu o intervalo e o recurso está ligado.
   */
  public function cron(): void {
    $settings = $this->configFactory->get(DeliverySettings::CONFIG);
    if (!$settings->get('summary_enabled')) {
      return;
    }
    $recipients = DeliverySettings::recipients((string) $settings->get('summary_recipients'));
    if ($settings->get('summary_admins')) {
      $recipients = array_values(array_unique(array_merge($recipients, $this->administrators->emails())));
    }
    $now = $this->time->getRequestTime();
    if ($recipients === [] || $now - (int) $this->state->get(self::STATE_LAST, 0) < self::INTERVAL) {
      return;
    }
    // Marca antes de enfileirar: uma falha de fila não gera resumos repetidos a cada cron.
    $this->state->set(self::STATE_LAST, $now);
    $summary = $this->build($now);
    foreach ($recipients as $recipient) {
      $this->queue->enqueue(new MailMessage(WeeklySummaryMailType::ID, $recipient, 'pt-br', $summary, $this->uuid->generate()));
    }
  }

  /**
   * Variáveis do resumo da semana até `$now`, com a semana anterior para comparação.
   *
   * @return array<string, string>
   *   Variáveis do tipo de resumo.
   */
  public function build(int $now): array {
    $week = self::INTERVAL;
    $current = $this->statusCounts($now - $week, $now);
    $previous = $this->statusCounts($now - 2 * $week, $now - $week);
    return [
      'periodo' => date('d/m/Y', $now - $week) . ' a ' . date('d/m/Y', $now),
      'total' => (string) $current['total'],
      'enviados' => (string) ($current['status']['sent'] + $current['status']['delivered']),
      'falhas' => (string) $current['status']['failed'],
      'rebotes' => (string) $current['status']['bounced'],
      'anterior' => (string) $previous['total'],
      'motivos' => $this->reasons($now - $week, $now),
    ];
  }

  /**
   * Total e contagem por estado no intervalo [de, até).
   *
   * @return array{total: int, status: array<string, int>}
   *   Total do intervalo e contagem por estado.
   */
  private function statusCounts(int $from, int $to): array {
    $storage = $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE);
    $counts = ['total' => $this->query($storage, $from, $to)->count()->execute(), 'status' => []];
    foreach (self::STATUSES as $status) {
      $counts['status'][$status] = (int) $this->query($storage, $from, $to)->condition('status', $status)->count()->execute();
    }
    return $counts;
  }

  /**
   * Lista de motivos de falha da semana, do mais frequente. Sem texto do provedor.
   */
  private function reasons(int $from, int $to): string {
    $storage = $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE);
    $ids = $this->query($storage, $from, $to)
      ->condition('status', 'failed')
      ->range(0, self::LIMIT)
      ->execute();
    $codes = [];
    foreach ($storage->loadMultiple($ids) as $log) {
      $code = (string) $log->get('error_code')->value;
      $codes[$code === '' ? '(sem código)' : $code] = ($codes[$code === '' ? '(sem código)' : $code] ?? 0) + 1;
    }
    arsort($codes);
    $lines = [];
    foreach (array_slice($codes, 0, 10, TRUE) as $code => $count) {
      $lines[] = $code . ': ' . $count;
    }
    return $lines === [] ? '-' : implode("\n", $lines);
  }

  /**
   * Consulta de registros no intervalo [de, até). O limite fica só na leitura de motivos, não na contagem.
   */
  private function query(object $storage, int $from, int $to): object {
    return $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('created', $from, '>=')
      ->condition('created', $to, '<');
  }

}
