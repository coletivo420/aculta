<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\EventSubscriber\AccountRouteSubscriber;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Entity access policies owned by the Portal.
 */
final class EntityHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'current_route_match')]
    private readonly CurrentRouteMatch $routeMatch,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'aculta_portal.email_confirmation_policy')]
    private readonly \Drupal\aculta_portal\Account\EmailConfirmationPolicy $emailPolicy,
  ) {}

  /**
   * Removes the "Ver" operation of users from entity operation lists.
   *
   * O perfil público de identidade (/identidade/{usuário}) existe só no purpose ACCOUNT. Nas listas
   * administrativas ele levava a 404 em MAIN. A edição continua disponível na mesma linha.
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {
    if ($entity->getEntityTypeId() === 'user') {
      unset($operations['view']);
    }
  }

  /**
   * Restricts Portal-owned entity operations without replacing entity handlers.
   */
  #[Hook('entity_access')]
  public function entityAccess(
    EntityInterface $entity,
    $operation,
    AccountInterface $account,
  ): AccessResultInterface {
    // Política de confirmação de e-mail: escrita na wiki e em comentários exige e-mail confirmado.
    if (in_array($operation, ['update', 'delete'], TRUE) && !$account->isAnonymous() && !$this->emailPolicy->isConfirmed($account)
      && (($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'wiki_entry')
        || ($entity->getEntityTypeId() === 'taxonomy_term' && $entity->bundle() === 'wiki_category')
        || $entity->getEntityTypeId() === 'comment')) {
      return AccessResult::forbidden('E-mail não confirmado.')->addCacheContexts(['user'])->cachePerUser();
    }

    $wikiEntity = ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'wiki_entry')
      || ($entity->getEntityTypeId() === 'taxonomy_term' && $entity->bundle() === 'wiki_category');
    if ($wikiEntity && $this->domainPurposeManager->getCurrentPurpose() !== 'wiki') {
      return AccessResult::forbidden()
        ->addCacheContexts(['domain'])
        ->addCacheableDependency($entity);
    }

    if ($entity->getEntityTypeId() === 'user'
      && $operation === 'update'
      && $this->routeMatch->getRouteName() === 'entity.user.edit_form'
      && !$account->hasPermission('administer users')) {
      $request = $this->requestStack->getCurrentRequest();
      if ($request !== NULL
        && AccountRouteSubscriber::isValidCorePasswordResetRequest(
          $request,
          (int) $entity->id(),
          (int) $account->id(),
        )) {
        // The one-time reset token lives in the request/session. Keep the
        // decision neutral, but never cache it as a permanent entity result.
        return AccessResult::neutral()
          ->cachePerPermissions()
          ->addCacheContexts(['route', 'user'])
          ->setCacheMaxAge(0);
      }

      // Defense in depth if a request lacks an upcasted target for the redirect
      // subscriber: regular users must never receive Drupal's generic account
      // editing interface.
      return AccessResult::forbidden()
        ->cachePerPermissions()
        ->addCacheContexts(['route', 'user'])
        ->setCacheMaxAge(0);
    }

    if ($entity->getEntityTypeId() !== 'commerce_payment_gateway'
      || $entity->id() !== 'mercado_pago'
      || $operation !== 'update') {
      return AccessResult::neutral();
    }

    return AccessResult::forbidden(
      'This gateway is managed through reviewed configuration and environment values; editing it here could persist credentials.',
    );
  }

}
