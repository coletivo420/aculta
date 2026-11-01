<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\Core\Session\AccountInterface;

/**
 * Aceite obrigatório dos Termos de Uso e da Política de Privacidade (acordo `aculta_user_agreement_terms`).
 *
 * Aceite obrigatório dos termos armazenado pelo ledger append-only do aculta_user.
 */
final class AcultaUserAgreementPolicy {

  public const AGREEMENT_TYPE = 'aculta_user_agreement_terms';

  public function __construct(private readonly \Drupal\aculta_user\Consent\AcultaUserAgreementLedger $ledger) {}

  /** TRUE quando a conta autenticada ainda precisa aceitar a versão atual dos termos. */
  public function needsAcceptance(AccountInterface $account): bool {
    if ($account->isAnonymous() || $account->hasPermission('administer aculta user agreements')) {
      return FALSE;
    }
    return !$this->ledger->hasAcceptedAcultaUserAgreement((int) $account->id(), self::AGREEMENT_TYPE);
  }

  /** Grava o aceite da versão atual. Retorna TRUE se foi gravado. */
  public function record(AccountInterface $account): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }
    $this->ledger->recordAcultaUserAgreement((int) $account->id(), self::AGREEMENT_TYPE, 'accepted');
    return TRUE;
  }

}
