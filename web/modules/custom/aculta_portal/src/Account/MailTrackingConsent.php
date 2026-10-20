<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\agreement\AgreementHandlerInterface;
use Drupal\aculta_mailer\Tracking\TrackingConsentChecker;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Consentimento de rastreamento de e-mails da conta, dado no acordo aculta_rastreamento (opcional e separado dos termos).
 *
 * Quem não tem conta, ou não aceitou, não é rastreado.
 */
final class MailTrackingConsent implements TrackingConsentChecker {

  private const AGREEMENT = 'aculta_rastreamento';

  public function __construct(
    private readonly AgreementHandlerInterface $agreementHandler,
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function hasConsent(string $recipient): bool {
    $agreement = $this->entityTypes->getStorage('agreement')->load(self::AGREEMENT);
    if ($agreement === NULL) {
      return FALSE;
    }
    $storage = $this->entityTypes->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('mail', $recipient)
      ->range(0, 1)
      ->execute();
    $account = $ids === [] ? NULL : $storage->load(reset($ids));
    return $account !== NULL && $this->agreementHandler->hasAgreed($agreement, $account);
  }

}
