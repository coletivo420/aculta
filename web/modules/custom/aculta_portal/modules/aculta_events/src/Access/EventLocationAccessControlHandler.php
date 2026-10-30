<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controle de acesso a locais de evento (aculta_event_location).
 *
 * Locais são públicos para visualização por padrão, mas respeitam hooks
 * de acesso, purposes do portal e permissão administrativa para mutações.
 */
final class EventLocationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
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

      if (in_array('content_editor', $account->getRoles(), TRUE)) {
        if (\Drupal::hasService('aculta_user.organization_access_service')) {
          /** @var \Drupal\aculta_user\Organization\OrganizationAccessService $orgAccess */
          $orgAccess = \Drupal::service('aculta_user.organization_access_service');
          if ($orgAccess->isContentEditorScoped($entity, $account)) {
            return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
          }
        }
      }

      return AccessResult::forbidden('Local em rascunho ou privado não é visível publicamente.')
        ->cachePerUser()
        ->addCacheableDependency($entity);
    }

    if ($operation === 'update' || $operation === 'delete') {
      if ($operation === 'delete') {
        // Bloqueia exclusão de local associado a datas de evento (LOC-01/02).
        if (\Drupal::hasService('entity_type.manager') && \Drupal::entityTypeManager()->hasDefinition('aculta_event_date')) {
          $inUse = count(\Drupal::entityTypeManager()->getStorage('aculta_event_date')
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
      }

      if ($account->hasPermission('administer aculta events')) {
        return AccessResult::allowed()->cachePerPermissions();
      }

      $organizadorUid = (int) ($entity->get('field_organizador')->target_id ?? 0);
      if ($organizadorUid > 0 && (int) $account->id() === $organizadorUid) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
      }

      if (in_array('content_editor', $account->getRoles(), TRUE)) {
        if (\Drupal::hasService('aculta_user.organization_access_service')) {
          /** @var \Drupal\aculta_user\Organization\OrganizationAccessService $orgAccess */
          $orgAccess = \Drupal::service('aculta_user.organization_access_service');
          if ($orgAccess->isContentEditorScoped($entity, $account)) {
            return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
          }
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
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta events')
      ->orIf(AccessResult::allowedIf(in_array('content_editor', $account->getRoles(), TRUE)));
  }

}
