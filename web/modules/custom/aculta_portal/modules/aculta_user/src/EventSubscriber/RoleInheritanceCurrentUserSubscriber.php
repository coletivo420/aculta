<?php

declare(strict_types=1);

namespace Drupal\aculta_user\EventSubscriber;

use Drupal\aculta_user\Role\HerancaDePapeis;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\UserSession;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Estende os papéis do usuário logado com os herdados (a sessão não passa por user_load).
 *
 * Prioridade 299: logo depois da autenticação do Core (300), antes do restante da requisição.
 */
final class RoleInheritanceCurrentUserSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly HerancaDePapeis $heranca,
    private readonly AccountProxyInterface $usuario,
  ) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => [['estenderUsuario', 299]]];
  }

  public function estenderUsuario(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $conta = $this->usuario->getAccount();
    if (!$conta instanceof UserSession) {
      return;
    }
    $estendido = new UserSession([
      'uid' => $conta->id(),
      'mail' => $conta->getEmail(),
      'name' => $conta->getAccountName(),
      'roles' => $this->heranca->papeisEstendidos($conta),
      'access' => $conta->getLastAccessedTime(),
      'timezone' => $conta->getTimeZone(),
      'preferred_langcode' => $conta->getPreferredLangcode(),
      'preferred_admin_langcode' => $conta->getPreferredAdminLangcode(),
    ]);
    $this->usuario->setAccount($estendido);
  }

}
