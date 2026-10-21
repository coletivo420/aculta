<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\aculta_user\Consent\AcultaUserAgreementLedger;
use Drupal\aculta_mailer\Tracking\TrackingConsentChecker;

/**
 * Consentimento opcional de rastreamento de e-mails, mantido no ledger aculta_user_agreement.
 *
 * Quem não tem conta, ou não aceitou, não é rastreado.
 */
final class AcultaUserAgreementTrackingConsent implements TrackingConsentChecker {

  private const AGREEMENT_TYPE = 'aculta_user_agreement_email_tracking';

  public function __construct(private readonly AcultaUserAgreementLedger $ledger, private readonly \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypes) {}

  /**
   * {@inheritdoc}
   */
  public function hasConsent(string $recipient): bool {
    $storage = $this->entityTypes->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('mail', $recipient)
      ->range(0, 1)
      ->execute();
    $account = $ids === [] ? NULL : $storage->load(reset($ids));
    return $account !== NULL && $this->ledger->hasAcceptedAcultaUserAgreement((int) $account->id(), self::AGREEMENT_TYPE);
  }

}
