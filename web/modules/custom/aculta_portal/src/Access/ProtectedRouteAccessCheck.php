<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Access;

use Drupal\aculta_portal\Account\AcultaUserAgreementPolicy;
use Drupal\commerce_checkout\Controller\CheckoutController;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Commerce and LMS access checks preserve their owners' authorization rules.
 *
 * Checkout is an exception to the email-confirmation policy. Commerce still
 * verifies order ownership, store publication, cart items, and `access
 * checkout`; authenticated customers must also accept the current terms.
 */
final class ProtectedRouteAccessCheck {

  public function __construct(
    private readonly ClassResolverInterface $classResolver,
    private readonly AcultaUserAgreementPolicy $termsPolicy,
  ) {}

  /**
   * Checkout do Commerce: rota /checkout/{pedido}/{etapa}. */
  public function checkout(RouteMatchInterface $routeMatch, AccountInterface $account): AccessResult {
    $commerce = $this->classResolver->getInstanceFromDefinition(CheckoutController::class);
    $commerceAccess = $commerce->checkAccess($routeMatch, $account);
    // Antes do login não há e-mail nem aceite a verificar: o Commerce já decide
    // o visitante anônimo pela sessão do carrinho. Combinar os resultados
    // neutros com um acesso permitido o tornaria neutro -- e neutro é negado.
    if ($account->isAnonymous()) {
      return $commerceAccess;
    }
    return $commerceAccess->andIf($this->terms($account));
  }

  /**
   * Curso e lição do LMS: Group decide a matrícula; termos seguem obrigatórios. */
  public function course(RouteMatchInterface $routeMatch, AccountInterface $account): AccessResult {
    $group = $routeMatch->getParameter('group');
    $take = $group ? $group->access('take', $account, TRUE) : AccessResult::neutral();
    // A inscrição em cursos não exige confirmação de e-mail. Não substituir
    // nem ampliar o acesso nativo: só participa quem passa pelo Group take.
    return $take->andIf($this->terms($account));
  }

  /**
   * Commerce checkout entry route; its permission still controls access. */
  public function checkoutStart(AccountInterface $account): AccessResult {
    if ($account->isAnonymous()) {
      // The route's own `access checkout` permission applies; keep anonymous
      // cart checkout available and leave cart ownership to Commerce.
      return AccessResult::allowed()->cachePerUser();
    }
    return $this->terms($account);
  }

  /**
   * Aceite da versão atual dos termos. Visitante anônimo recebe resultado neutro, como no e-mail. */
  private function terms(AccountInterface $account): AccessResult {
    if ($account->isAnonymous()) {
      return AccessResult::neutral()->cachePerUser();
    }
    return $this->termsPolicy->needsAcceptance($account)
      ? AccessResult::forbidden('Aceite dos termos pendente.')->cachePerUser()
      : AccessResult::allowed()->cachePerUser();
  }

}
