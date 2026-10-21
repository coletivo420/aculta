<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\aculta_user\Consent\ConsentLedger;
use Drupal\aculta_mailer\Tracking\TrackingConsentChecker;

/**
 * Consentimento de rastreamento de e-mails da conta, dado no acordo aculta_rastreamento (opcional e separado dos termos).
 *
 * Quem não tem conta, ou não aceitou, não é rastreado.
 */
final class MailTrackingConsent implements TrackingConsentChecker {

  private const AGREEMENT = 'aculta_rastreamento';

  public function __construct(private readonly ConsentLedger $ledger, private readonly \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypes) {}

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
    return $account !== NULL && $this->ledger->hasAccepted((int) $account->id(), self::AGREEMENT);
  }

}
