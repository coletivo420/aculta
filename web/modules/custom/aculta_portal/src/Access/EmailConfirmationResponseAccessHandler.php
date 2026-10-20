<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmationAccessControlHandler;

/**
 * Permite a resposta à confirmação de e-mail a qualquer visitante, inclusive anônimo.
 *
 * O módulo email_confirmer restringe pedidos privados ao dono do pedido, o que impede quem clica no link
 * de confirmar a troca quando o pedido foi feito por um usuário logado. O segredo passa a ser o hash da URL
 * (43 caracteres), que o próprio confirm() verifica. A permissão só vale para pedidos pendentes e não expirados.
 * As demais operações seguem o handler original.
 */
final class EmailConfirmationResponseAccessHandler extends AcultaMailConfirmationAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'response' && $entity->isPending() && !$entity->isExpired()) {
      return AccessResult::allowed()->addCacheableDependency($entity)->setCacheMaxAge(0);
    }
    return parent::checkAccess($entity, $operation, $account);
  }

}
