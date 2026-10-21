<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Caminhos legados de troca de e-mail: /seguranca/email e /seguranca/email/{user}.
 *
 * Não há mais página própria. Os dois caminhos levam à aba E-mail da Segurança, e só a própria conta pode usá-los.
 */
final class ChangemailRedirectController implements ContainerInjectionInterface {

  public function __construct(
    private readonly DomainPurposeManager $domainPurpose,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.domain_purpose'),
      $container->get('current_user'),
    );
  }

  /**
   * Redireciona para a Segurança da conta; {user} precisa ser a própria conta.
   */
  public function redirectToSecurity(?UserInterface $user = NULL): RedirectResponse {
    if ($user !== NULL && (int) $user->id() !== (int) $this->currentUser->id()) {
      throw new AccessDeniedHttpException();
    }
    $target = $this->domainPurpose->routeUrl('account', 'aculta_portal.security');
    if ($target === NULL) {
      throw new NotFoundHttpException();
    }
    return new TrustedRedirectResponse($target->toString());
  }

}
