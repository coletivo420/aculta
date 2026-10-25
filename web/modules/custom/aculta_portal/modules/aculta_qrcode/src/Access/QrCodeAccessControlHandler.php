<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Administração de códigos QR dinâmicos restrita à permissão própria.
 *
 * Nenhum papel recebe acesso por nome; somente `administer aculta qr codes`
 * libera leitura, criação, alteração e exclusão. O redirecionamento público
 * não passa por esta verificação: usa `QrCodeManager::findActive()`.
 */
final class QrCodeAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta qr codes')
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta qr codes');
  }

}
