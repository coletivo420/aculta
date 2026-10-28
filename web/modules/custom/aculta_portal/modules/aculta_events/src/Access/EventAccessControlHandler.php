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

    return AccessResult::allowedIfHasPermission($account, 'administer aculta events');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta events');
  }

}
