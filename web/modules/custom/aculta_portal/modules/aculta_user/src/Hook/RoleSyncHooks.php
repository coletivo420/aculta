<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;

/**
 * Concede wiki_contributor a quem tem o papel email_confirmed.
 *
 * Decisão do responsável (plano aculta_user, decisão 28, reafirmada em 2026-10-12): a Wiki é para quem confirmou o
 * e-mail, e a regra vale em todos os casos. Drupal não mapeia papel para papel, então a regra roda na gravação da
 * conta. Não remove wiki_contributor quando email_confirmed sai: essa remoção ainda não foi decidida.
 */
final class RoleSyncHooks {

  /**
   * Papel de origem (e-mail confirmado).
   */
  private const SOURCE_ROLE = 'email_confirmed';

  /**
   * Papel concedido (colaborador da Wiki).
   */
  private const TARGET_ROLE = 'wiki_contributor';

  /**
   * Implements hook_user_insert().
   */
  #[Hook('user_insert')]
  public function userInsert(UserInterface $account): void {
    $this->grantWikiContributor($account);
  }

  /**
   * Implements hook_user_update().
   */
  #[Hook('user_update')]
  public function userUpdate(UserInterface $account): void {
    $this->grantWikiContributor($account);
  }

  /**
   * Adiciona wiki_contributor se a conta tem email_confirmed e ainda não tem o papel.
   */
  private function grantWikiContributor(UserInterface $account): void {
    if (!$account->hasRole(self::SOURCE_ROLE) || $account->hasRole(self::TARGET_ROLE)) {
      return;
    }
    // O segundo save cai no ramo de retorno: a conta já tem o papel.
    $account->addRole(self::TARGET_ROLE);
    $account->save();
  }

}
