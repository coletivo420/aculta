<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Classifica a resposta SMTP: 4xx é temporário, 5xx é permanente.
 *
 * Código ausente ou fora de 4xx/5xx é tratado como temporário: o retry é limitado por RetrySchedule, então o custo
 * de uma classificação conservadora é baixo, enquanto tratar erro transitório como permanente perderia mensagens.
 */
final class SmtpReplyClassifier {

  /**
   * Classe do erro para o código de resposta SMTP informado.
   */
  public function classify(?int $replyCode): DeliveryError {
    return match (TRUE) {
      $replyCode !== NULL && $replyCode >= 500 && $replyCode <= 599 => DeliveryError::Permanent,
      default => DeliveryError::Temporary,
    };
  }

}
