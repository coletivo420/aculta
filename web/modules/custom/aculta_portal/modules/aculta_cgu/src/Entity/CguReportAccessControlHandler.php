<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_cgu\ModerationScope;

/**
 * Controle de acesso a denúncias de UGC.
 *
 * Moderadores só acessam denúncias do purpose que moderam (ARQ-06A). O
 * denunciante vê somente as próprias denúncias.
 */
final class CguReportAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if (!$entity instanceof ContentEntityInterface) {
      return AccessResult::neutral();
    }

    $purposeValue = $entity->get('purpose')->value;
    $purpose = is_scalar($purposeValue) ? (string) $purposeValue : '';
    if (ModerationScope::allows($account, $purpose)) {
      return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
    }

    if ($operation === 'view') {
      $uidItem = $entity->get('uid')->first();
      $uidValue = $uidItem?->get('target_id')->getValue();
      $uid = is_numeric($uidValue) ? (int) $uidValue : 0;
      if ($uid > 0 && $uid === (int) $account->id()) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
      }
    }

    return AccessResult::forbidden()->cachePerUser()->cachePerPermissions()->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $context
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIf($account->isAuthenticated())->cachePerPermissions()->cachePerUser();
  }

}

