<?php

declare(strict_types=1);

namespace Drupal\aculta_user\EventSubscriber;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_user\Role\HerancaDePapeis;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\UserSession;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    #[Autowire(service: 'aculta_portal.email_confirmation_policy')]
    private readonly ?EmailConfirmationPolicy $emailConfirmationPolicy = NULL,
  ) {}

  /**
   * Declara o evento de requisição com a prioridade de herança de papéis.
   *
   * @return array
   *   Mapa de eventos para os métodos tratadores.
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => [['estenderUsuario', 299]]];
  }

  /**
   * Substitui a sessão pelo usuário com os papéis herdados.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Evento da requisição atual.
   */
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
      'roles' => $this->heranca->papeisEstendidos(
        $conta,
        $this->emailConfirmationPolicy?->isConfirmed($conta) ?? TRUE,
      ),
      'access' => $conta->getLastAccessedTime(),
      'timezone' => $conta->getTimeZone(),
      'preferred_langcode' => $conta->getPreferredLangcode(),
      'preferred_admin_langcode' => $conta->getPreferredAdminLangcode(),
    ]);
    $this->usuario->setAccount($estendido);
  }

}
