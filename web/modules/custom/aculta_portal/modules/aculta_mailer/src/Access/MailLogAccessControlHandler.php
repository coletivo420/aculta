<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Registros de e-mail são somente leitura para administradores; quem escreve é o próprio módulo.
 */
final class MailLogAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($operation === 'view' || $operation === 'view all') {
      return AccessResult::allowedIfHasPermission($account, 'administer aculta_mailer');
    }
    return AccessResult::forbidden('Registros de e-mail são gravados somente pelo aculta_mailer.');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::forbidden('Registros de e-mail são gravados somente pelo aculta_mailer.');
  }

}
