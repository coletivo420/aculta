<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controla o acesso às autorias históricas.
 *
 * O memorial controla a leitura pública. A entidade não é visível diretamente
 * para visitantes ou contas comuns.
 */
final class LegacyForumUserAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta legado');
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Conta que solicita acesso.
   * @param array<array-key, mixed> $context
   *   Dados recebidos.
   * @param string|null $entity_bundle
   *   Bundle opcional da entidade.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta legado');
  }

}
