<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\aculta_mailer\Tracking\TrackingConsentChecker;
use Drupal\aculta_user\Consent\AcultaUserAgreementLedger;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Rastreamento de e-mails (aberturas e cliques), coberto pelo aceite único, e desligado até a publicação.
 *
 * Não há aceite próprio. A base é o aceite único de Termos e Política (`aculta_user_agreement_terms`), que só vale
 * depois que a função estiver descrita na política publicada. Por isso `FUNCTION_PUBLISHED` começa FALSO: enquanto
 * for falso, nenhum e-mail é rastreado. Ligar exige publicar o texto e alterar a constante por revisão de código.
 * Ver docs/portal/ACEITE-UNICO.md.
 */
final class AcultaUserAgreementTrackingConsent implements TrackingConsentChecker {

  /**
   * Verdadeiro só depois de a política publicada descrever o rastreamento de e-mails.
   */
  public const FUNCTION_PUBLISHED = FALSE;

  public function __construct(
    private readonly AcultaUserAgreementLedger $ledger,
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function hasConsent(string $recipient): bool {
    if (!self::FUNCTION_PUBLISHED) {
      return FALSE;
    }
    $storage = $this->entityTypes->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('mail', $recipient)
      ->range(0, 1)
      ->execute();
    $account = $ids === [] ? NULL : $storage->load(reset($ids));
    return $account !== NULL && $this->ledger->hasAcceptedAcultaUserAgreement((int) $account->id(), AcultaUserAgreementLedger::AGREEMENT_TYPE);
  }

}
