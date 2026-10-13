<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\user\UserInterface;

/**
 * Regra de invariável: o papel email_confirmed não pode ser removido de uma conta.
 *
 * A confirmação de e-mail é uma etapa para proteger o site contra bots e contas falsas. Ela não é uma permissão
 * editorial. O papel pula o CAPTCHA (a configuração do CAPTCHA é global; a dispensa é por papel), e a Wiki
 * usa a mesma marca (wiki_contributor acompanha email_confirmed). Remover o papel reabriria a proteção, por isso:
 * - o formulário de administração trava o checkbox do papel;
 * - o presave restaura o papel se ele sair por qualquer outro caminho (API, script, importação).
 */
final class EmailConfirmedRoleGuard {

  /** Papel de e-mail confirmado. */
  public const ROLE = EmailConfirmationPolicy::ROLE;

  /**
   * Indica se a gravação está removendo o papel de uma conta que o tinha.
   */
  public static function isRemoval(UserInterface $account, ?UserInterface $original): bool {
    return $original !== NULL && $original->hasRole(self::ROLE) && !$account->hasRole(self::ROLE);
  }

}
