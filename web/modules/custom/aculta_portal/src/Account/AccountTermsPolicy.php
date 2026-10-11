<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\agreement\AgreementHandlerInterface;
use Drupal\agreement\Entity\Agreement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Aceite obrigatório dos Termos de Uso e da Política de Privacidade (acordo `aculta_account_terms`).
 *
 * Uma única fonte de verdade para todos os hosts: o aceite é gravado por usuário na tabela `agreement` do módulo
 * agreement. Quem se cadastra pelo formulário aceita ali (checkbox obrigatório) e não vê o popup. Quem entra por
 * outra via (OAuth, por exemplo) recebe o popup até aceitar. Trocar a versão dos termos é alterar `reset_date` do
 * acordo: `Agreement::getAgreementFrequencyTimestamp()` usa o maior entre `reset_date` e a frequência, então todos
 * precisam aceitar de novo. Ver docs/integrations/AUTHENTICATION.md.
 */
final class AccountTermsPolicy {

  public const AGREEMENT = 'aculta_account_terms';

  public function __construct(
    private readonly AgreementHandlerInterface $agreementHandler,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** TRUE quando a conta autenticada ainda precisa aceitar a versão atual dos termos. */
  public function needsAcceptance(AccountInterface $account): bool {
    if ($account->isAnonymous() || $account->hasPermission('bypass agreement')) {
      return FALSE;
    }
    $agreement = $this->agreement();
    return $agreement !== NULL && !$this->agreementHandler->hasAgreed($agreement, $account);
  }

  /** Grava o aceite da versão atual. Retorna TRUE se foi gravado. */
  public function record(AccountInterface $account): bool {
    $agreement = $this->agreement();
    if ($agreement === NULL || $account->isAnonymous()) {
      return FALSE;
    }
    $this->agreementHandler->agree($agreement, $account, 1);
    return TRUE;
  }

  private function agreement(): ?Agreement {
    $agreement = $this->entityTypeManager->getStorage('agreement')->load(self::AGREEMENT);
    return $agreement instanceof Agreement ? $agreement : NULL;
  }

}
