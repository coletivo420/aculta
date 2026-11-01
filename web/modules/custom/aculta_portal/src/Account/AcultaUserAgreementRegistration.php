<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\Core\Session\AccountInterface;

/**
 * Registra o aceite dos termos (acordo aculta_user_agreement_terms) no momento do cadastro, quando o visitante
 * marca o checkbox do formulário. Assim o aceite não depende de uma etapa posterior.
 */
final class AcultaUserAgreementRegistration {

  public function __construct(private readonly \Drupal\aculta_user\Consent\AcultaUserAgreementLedger $ledger) {}

  /** Grava o aceite do acordo para a conta recém-criada. Retorna TRUE se foi gravado. */
  public function record(AccountInterface $account): bool {
    if ((int) $account->id() <= 0) {
      return FALSE;
    }
    $this->ledger->recordAcultaUserAgreement((int) $account->id(), 'aculta_user_agreement_terms', 'accepted');
    return TRUE;
  }

}
