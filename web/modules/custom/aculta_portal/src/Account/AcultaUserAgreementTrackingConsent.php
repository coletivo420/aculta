<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\aculta_user\Consent\AcultaUserAgreementLedger;
use Drupal\aculta_mailer\Tracking\TrackingConsentChecker;

/**
 * Rastreamento de e-mails: desligado (falha fechada) até a decisão sobre a função.
 *
 * Não existe mais aceite próprio de rastreamento: o único aceite é o de Termos e Política
 * (`aculta_user_agreement_terms`). Enquanto a função não estiver descrita na política e o responsável não
 * decidir a base legal, nenhum e-mail é rastreado. Ver docs/portal/ACEITE-UNICO.md.
 */
final class AcultaUserAgreementTrackingConsent implements TrackingConsentChecker {

  public function __construct(private readonly AcultaUserAgreementLedger $ledger, private readonly \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypes) {}

  /**
   * {@inheritdoc}
   */
  public function hasConsent(string $recipient): bool {
    return FALSE;
  }

}
