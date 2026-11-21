<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Read-only access for the historic archive.
 *
 * Archive content accepts no new comments, reactions, flags or moderation
 * actions. Only administrators may view it; no account edits or deletes it
 * through the entity API. The import runs through the entity API, which
 * bypasses this handler by design.
 */
final class LegacyForumContentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation !== 'view') {
      return AccessResult::forbidden('O acervo histórico é somente leitura.')->addCacheableDependency($entity);
    }
    return AccessResult::allowedIfHasPermission($account, 'administer aculta legado')->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $context
   *   Access context; unused, creation is always denied.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::forbidden('O acervo histórico não aceita criação pela interface.');
  }

}
