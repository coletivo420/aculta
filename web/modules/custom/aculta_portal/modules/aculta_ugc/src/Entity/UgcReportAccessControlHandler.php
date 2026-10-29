<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_ugc\ModerationScope;

/**
 * Controle de acesso a denúncias de UGC.
 *
 * Moderadores só acessam denúncias do purpose que moderam (ARQ-06A). O
 * denunciante vê somente as próprias denúncias.
 */
final class UgcReportAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    $purpose = (string) $entity->get('purpose')->value;
    if (ModerationScope::allows($account, $purpose)) {
      return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
    }

    if ($operation === 'view') {
      $uid = (int) $entity->get('uid')->target_id;
      if ($uid > 0 && $uid === (int) $account->id()) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
      }
    }

    return AccessResult::forbidden()->cachePerUser()->cachePerPermissions()->addCacheableDependency($entity);
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIf($account->isAuthenticated())->cachePerPermissions()->cachePerUser();
  }

}
