<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Access;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Exige e-mail confirmado para cursos e checkout (política de confirmação de e-mail).
 *
 * Visitante anônimo recebe resultado neutro: as demais checagens da rota já o tratam.
 */
final class EmailConfirmedAccessCheck {

  public function __construct(
    #[Autowire(service: 'aculta_portal.email_confirmation_policy')]
    private readonly EmailConfirmationPolicy $policy,
  ) {}

  public function access(AccountInterface $account): AccessResult {
    if ($account->isAnonymous()) {
      return AccessResult::neutral()->cachePerUser();
    }
    return $this->policy->isConfirmed($account)
      ? AccessResult::allowed()->cachePerUser()
      : AccessResult::forbidden('E-mail não confirmado.')->cachePerUser();
  }

}
