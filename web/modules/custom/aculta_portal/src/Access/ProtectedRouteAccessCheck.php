<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Access;

use Drupal\aculta_portal\Account\AccountTermsPolicy;
use Drupal\commerce_checkout\Controller\CheckoutController;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checkout e cursos: a regra original do dono do recurso continua valendo, e o e-mail confirmado é exigido junto.
 *
 * A política de confirmação de e-mail não substitui a regra do Commerce (dono do pedido, loja publicada,
 * itens, permissão "access checkout") nem a do LMS (group.take). Cada rota tem um único _custom_access,
 * então a combinação acontece aqui, com andIf(), e o cache das duas regras é acumulado.
 */
final class ProtectedRouteAccessCheck {

  public function __construct(
    private readonly EmailConfirmedAccessCheck $emailCheck,
    private readonly ClassResolverInterface $classResolver,
    private readonly AccountTermsPolicy $termsPolicy,
  ) {}

  /** Checkout do Commerce: rota /checkout/{pedido}/{etapa}. */
  public function checkout(RouteMatchInterface $routeMatch, AccountInterface $account): AccessResult {
    $commerce = $this->classResolver->getInstanceFromDefinition(CheckoutController::class);
    $order = $routeMatch->getParameter('commerce_order');
    $emailAccess = $order instanceof OrderInterface && $order->bundle() === 'curso'
      ? AccessResult::allowed()->cachePerUser()
      : $this->emailCheck->access($account);
    return $commerce->checkAccess($routeMatch, $account)
      ->andIf($emailAccess)
      ->andIf($this->terms($account));
  }

  /** Curso e lição do LMS: rotas com o parâmetro group. */
  public function course(RouteMatchInterface $routeMatch, AccountInterface $account): AccessResult {
    $group = $routeMatch->getParameter('group');
    $take = $group ? $group->access('take', $account, TRUE) : AccessResult::neutral();
    return $take->andIf($this->emailCheck->access($account))->andIf($this->terms($account));
  }

  /** Rotas sem recurso próprio (ex.: /checkout): e-mail confirmado e aceite dos termos. */
  public function account(AccountInterface $account): AccessResult {
    return $this->emailCheck->access($account)->andIf($this->terms($account));
  }

  /** Aceite da versão atual dos termos. Visitante anônimo recebe resultado neutro, como no e-mail. */
  private function terms(AccountInterface $account): AccessResult {
    if ($account->isAnonymous()) {
      return AccessResult::neutral()->cachePerUser();
    }
    return $this->termsPolicy->needsAcceptance($account)
      ? AccessResult::forbidden('Aceite dos termos pendente.')->cachePerUser()
      : AccessResult::allowed()->cachePerUser();
  }

}
