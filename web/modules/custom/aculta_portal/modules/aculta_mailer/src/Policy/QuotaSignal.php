<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Policy;

use Drupal\aculta_mailer\Alert\MailAlerts;
use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Liga o texto de erro ou de bounce ao transporte e ao alerta.
 *
 * Quando o texto indica cota (modelo de failover e alerta "failed_primary_email" do WP Mail SMTP).
 */
class QuotaSignal {

  public function __construct(
    private readonly TransportQuota $quota,
    private readonly MailAlerts $alerts,
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  /**
   * Falha de envio por um transporte: texto de cota marca o transporte e gera alerta.
   */
  public function failure(string $transport, string $detail): void {
    if (!QuotaSignatures::matches($detail)) {
      return;
    }
    $this->quota->signal($transport);
    $this->alerts->quotaReached($transport, $detail);
  }

  /**
   * Bounce recebido pelo monitoramento: o transporte é o do envio correspondente ao correlation ID.
   */
  public function bounce(string $correlationId, string $diagnostic): void {
    if ($correlationId === '' || !QuotaSignatures::matches($diagnostic)) {
      return;
    }
    $found = $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE)->loadByProperties(['correlation_id' => $correlationId]);
    if ($found === []) {
      return;
    }
    $this->failure((string) reset($found)->get('transport')->value, $diagnostic);
  }

}
