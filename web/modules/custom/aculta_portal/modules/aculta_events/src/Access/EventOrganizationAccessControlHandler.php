<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_user\Organization\OrganizationAccessService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controle de acesso a Organizações Culturais (ARQ-05).
 */
final class EventOrganizationAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    protected readonly ?OrganizationAccessService $organizationAccess,
    protected readonly EventPermissionPolicy $permissionPolicy,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->has('aculta_user.organization_access_service')
        ? $container->get('aculta_user.organization_access_service')
        : NULL,
      $container->get('aculta_events.permission_policy'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    if (!$entity instanceof EventOrganization) {
      return AccessResult::neutral();
    }

    if ($operation === 'view') {
      $status = (string) $entity->get('field_status')->value;
      if (!$entity->isPublished() || $status === 'rascunho') {
        return AccessResult::allowedIfHasPermission($account, 'view aculta event drafts')
          ->orIf(AccessResult::allowedIfHasPermission($account, 'administer aculta events'))
          ->addCacheableDependency($entity);
      }
      return AccessResult::allowed()->addCacheableDependency($entity);
    }

    if ($operation === 'update') {
      if ($account->hasPermission('administer aculta events')) {
        return AccessResult::allowed()->cachePerPermissions();
      }

      // A reivindicada permite edição ao titular e membros aprovados.
      if ($entity->isClaimed()) {
        $titularId = $entity->getOwnerId();
        if ($titularId !== NULL && $titularId === (int) $account->id()) {
          return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
        }

        if ($this->organizationAccess?->canActOnBehalf($account, (string) $entity->id())) {
          return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
        }
      }

      return AccessResult::forbidden('Usuário sem poderes de edição sobre a organização.')->cachePerUser()->addCacheableDependency($entity);
    }

    if ($operation === 'suggest') {
      if (!$entity->isUnclaimed()) {
        return AccessResult::forbidden('Somente organizações sem titular aceitam sugestões.')->addCacheableDependency($entity);
      }
      return $this->permissionPolicy->createEventAccessResult($account)
        ->andIf(AccessResult::neutral()->addCacheableDependency($entity));
    }

    if ($operation === 'delete') {
      if ($account->hasPermission('administer aculta events')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      if ($entity->isClaimed() && $entity->getOwnerId() === (int) $account->id()) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
      }
      return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($entity);
    }

    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return $this->permissionPolicy->createEventAccessResult($account);
  }

  /**
   * Access to direct edit or the suggestion form, as appropriate.
   */
  public static function editFormAccess(
    AccountInterface $account,
    EventOrganization $aculta_event_organization,
  ): AccessResultInterface {
    $operation = $account->hasPermission('administer aculta events') || $aculta_event_organization->isClaimed()
      ? 'update'
      : 'suggest';
    return $aculta_event_organization->access($operation, $account, TRUE);
  }

}
