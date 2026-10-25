<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Tracking;

/**
 * Consentimento de rastreamento de um destinatário (serviço com tag `aculta_mailer.tracking_consent`).
 *
 * O dono do consentimento é o módulo da conta (hoje aculta_portal). Sem implementação, ninguém é rastreado.
 */
interface TrackingConsentChecker {

  /**
   * Indica se o destinatário consentiu com o rastreamento de aberturas e cliques.
   *
   * @param string $recipient
   *   Endereço do destinatário.
   */
  public function hasConsent(string $recipient): bool;

}
