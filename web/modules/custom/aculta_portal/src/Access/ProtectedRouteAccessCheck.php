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
 * Checkout e cursos: preserva acesso original; cursos seguem matrícula Group sem exigir e-mail confirmado.
 *
 * A política de confirmação de e-mail não substitui a regra do Commerce (dono do pedido, loja publicada,
 * itens, permissão "access checkout") nem a do LMS (group.take). Para loja e rotas sem recurso, combina a regra de
 * e-mail; para cursos, combina somente o acesso Group com os termos vigentes.
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

  /** Curso e lição do LMS: Group decide a matrícula; termos seguem obrigatórios. */
  public function course(RouteMatchInterface $routeMatch, AccountInterface $account): AccessResult {
    $group = $routeMatch->getParameter('group');
    $take = $group ? $group->access('take', $account, TRUE) : AccessResult::neutral();
    // A inscrição em cursos não exige confirmação de e-mail. Não substituir
    // nem ampliar o acesso nativo: só participa quem passa pelo Group take.
    return $take->andIf($this->terms($account));
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
