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
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controle de acesso a locais de evento (aculta_event_location).
 *
 * Locais são públicos para visualização por padrão, mas respeitam hooks
 * de acesso, purposes do portal e permissão administrativa para mutações.
 */
final class EventLocationAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
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
    if (!$entity instanceof ContentEntityInterface) {
      return AccessResult::forbidden();
    }

    $status = $entity->hasField('field_status') && !$entity->get('field_status')->isEmpty()
      ? (string) $entity->get('field_status')->value
      : 'rascunho';

    if ($operation === 'view') {
      if ($status === 'publicado') {
        return AccessResult::allowed()->addCacheableDependency($entity);
      }

      if ($account->hasPermission('administer aculta events')) {
        return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
      }

      $organizadorUid = (int) ($entity->get('field_organizador')->target_id ?? 0);
      if ($organizadorUid > 0 && (int) $account->id() === $organizadorUid) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
      }

      if (in_array('content_editor', $account->getRoles(), TRUE)
        && $this->organizationAccess?->isContentEditorScoped($entity, $account)) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
      }

      return AccessResult::forbidden('Local em rascunho ou privado não é visível publicamente.')
        ->cachePerUser()
        ->addCacheableDependency($entity);
    }

    if ($operation === 'update' || $operation === 'delete') {
      if ($operation === 'delete'
        && $this->entityTypeManager->hasDefinition('aculta_event_date')) {
        // Bloqueia exclusão de local associado a datas de evento (LOC-01/02).
        $inUse = count($this->entityTypeManager->getStorage('aculta_event_date')
          ->getQuery()
          ->accessCheck(FALSE)
          ->condition('field_local', (int) $entity->id())
          ->execute());
        if ($inUse > 0) {
          return AccessResult::forbidden('Local em uso por datas de eventos não pode ser excluído.')
            ->addCacheableDependency($entity)
            ->addCacheTags(['aculta_event_date_list']);
        }
      }

      if ($account->hasPermission('administer aculta events')) {
        return AccessResult::allowed()->cachePerPermissions();
      }

      $organizadorUid = (int) ($entity->get('field_organizador')->target_id ?? 0);
      if ($organizadorUid > 0 && (int) $account->id() === $organizadorUid) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
      }

      if (in_array('content_editor', $account->getRoles(), TRUE)) {
        if ($this->organizationAccess?->isContentEditorScoped($entity, $account)) {
          return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
        }
        return AccessResult::forbidden('Editor sem vínculo comprovado com a organização do local.')
          ->cachePerUser()
          ->addCacheableDependency($entity);
      }
    }

    return AccessResult::allowedIfHasPermission($account, 'administer aculta events');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return $this->permissionPolicy->createLocationAccessResult($account);
  }

}
