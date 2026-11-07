<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Marcador de associado: só a administração de associados acessa a tabela (ACULTA Associados, marcador puro).
 */
final class AssociadoMarcadorAccessControlHandler extends EntityAccessControlHandler {

  /**
   * Verifica o acesso à entidade marcador de associado.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entidade verificada.
   * @param string $operation
   *   Operação solicitada.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Conta que solicita o acesso.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Resultado conforme a permissão de administração.
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta associados');
  }

  /**
   * Verifica o acesso à criação do marcador de associado.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Conta que solicita o acesso.
   * @param array $context
   *   Contexto adicional da criação.
   * @param string|null $entity_bundle
   *   Bundle da entidade, quando informado.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Resultado conforme a permissão de administração.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta associados');
  }

}
