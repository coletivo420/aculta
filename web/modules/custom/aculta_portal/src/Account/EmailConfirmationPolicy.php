<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserDataInterface;

/**
 * Estado de confirmação do e-mail por usuário (política de confirmação de e-mail, ver docs/portal).
 *
 * Usuário sem a marca "confirmado" tem e-mail não confirmado: pode entrar e navegar, mas não pode
 * realizar cursos, editar a wiki, comentar, comprar na loja nem apoiar. Contas existentes começam não
 * confirmadas. Login por OAuth e troca de e-mail confirmada marcam o e-mail como confirmado.
 */
final class EmailConfirmationPolicy {

  private const MODULE = 'aculta_portal';

  private const KEY = 'email_confirmed';

  /** Papel dos usuários com e-mail confirmado (pula o CAPTCHA; ver config/sync/user.role.email_confirmed.yml). */
  public const ROLE = 'email_confirmed';

  public function __construct(
    private readonly UserDataInterface $userData,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Verdadeiro quando o e-mail do usuário foi confirmado. Anônimo nunca é confirmado. */
  public function isConfirmed(AccountInterface $account): bool {
    $uid = (int) $account->id();
    if ($uid <= 0) {
      return FALSE;
    }
    return (bool) $this->userData->get(self::MODULE, $uid, self::KEY);
  }

  /** Marca o e-mail do usuário como confirmado. */
  public function markConfirmed(int $uid): void {
    if ($uid > 0) {
      $this->userData->set(self::MODULE, $uid, self::KEY, TRUE);
      $this->grantRole($uid);
    }
  }

  /** Adiciona o papel email_confirmed à conta. O papel não é removido (ver EmailConfirmedRoleGuard). */
  private function grantRole(int $uid): void {
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof \Drupal\user\UserInterface || $account->hasRole(self::ROLE)) {
      return;
    }
    $account->addRole(self::ROLE);
    $account->save();
  }

}
