<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Acesso a evento: rascunho só para quem pode ver rascunhos (MODELO-DE-DADOS.md, sitemap).
 *
 * Publicado e cancelado são visíveis; cancelado aparece com aviso na página (regra do modelo).
 */
final class EventAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
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
        if (\Drupal::hasService('aculta_user.organization_access_service')) {
          /** @var \Drupal\aculta_user\Organization\OrganizationAccessService $orgAccess */
          $orgAccess = \Drupal::service('aculta_user.organization_access_service');
          if ($orgAccess->isContentEditorScoped($entity, $account)) {
            return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
          }
        }
        return AccessResult::forbidden('Editor sem vínculo comprovado com a organização do evento.')->cachePerUser()->addCacheableDependency($entity);
      }
    }

    return AccessResult::allowedIfHasPermission($account, 'administer aculta events');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return \Drupal::service('aculta_events.permission_policy')->createEventAccessResult($account);
  }

}
