<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\aculta_user\Organization\OrganizationAccessService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controla acesso a Eventos publicados e rascunhos (modelo e sitemap).
 *
 * Publicados e cancelados são visíveis; cancelados mostram um aviso.
 */
final class EventAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    protected readonly OrganizationAccessService $organizationAccess,
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
      $container->get('aculta_user.organization_access_service'),
      $container->get('aculta_events.permission_policy'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    if (!$entity instanceof ContentEntityInterface) {
      return AccessResult::forbidden();
    }

    if ($operation === 'view') {
      $situacao = (string) $entity->get('field_status')->value;
      if ($situacao === 'rascunho') {
        return AccessResult::allowedIfHasPermission($account, 'view aculta event drafts')
          ->orIf(AccessResult::allowedIfHasPermission($account, 'administer aculta events'))
          ->addCacheableDependency($entity);
      }
      return AccessResult::allowed()->addCacheableDependency($entity);
    }

    if ($operation === 'update' || $operation === 'delete') {
      if ($account->hasPermission('administer aculta events')) {
        return AccessResult::allowed()->cachePerPermissions();
      }

      if (in_array('content_editor', $account->getRoles(), TRUE)) {
        if ($this->organizationAccess->isContentEditorScoped($entity, $account)) {
          return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
        }
        return AccessResult::forbidden('Editor sem vínculo comprovado com a organização do evento.')->cachePerUser()->addCacheableDependency($entity);
      }
    }

    return AccessResult::allowedIfHasPermission($account, 'administer aculta events');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return $this->permissionPolicy->createEventAccessResult($account);
  }

}
