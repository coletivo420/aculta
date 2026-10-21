<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\Core\Session\AccountInterface;

/**
 * Aceite obrigatório dos Termos de Uso e da Política de Privacidade (acordo `aculta_account_terms`).
 *
 * Aceite obrigatório dos termos armazenado pelo ledger append-only do aculta_user.
 */
final class AccountTermsPolicy {

  public const AGREEMENT = 'aculta_account_terms';

  public function __construct(private readonly \Drupal\aculta_user\Consent\ConsentLedger $ledger) {}

  /** TRUE quando a conta autenticada ainda precisa aceitar a versão atual dos termos. */
  public function needsAcceptance(AccountInterface $account): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }
    return !$this->ledger->hasAccepted((int) $account->id(), self::AGREEMENT);
  }

  /** Grava o aceite da versão atual. Retorna TRUE se foi gravado. */
  public function record(AccountInterface $account): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }
    $this->ledger->record((int) $account->id(), self::AGREEMENT, 'accepted');
    return TRUE;
  }

}
