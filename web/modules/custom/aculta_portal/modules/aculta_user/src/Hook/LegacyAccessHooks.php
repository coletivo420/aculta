<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Legacy\LegacyAccountManager;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;

/**
 * Contas legadas do Fórum não têm página pública individual (ACULTA Fórum, F-D08).
 *
 * Nega a visualização do User e do perfil participante de origem histórica a quem não administra usuários. O
 * memorial lista nomes sem usar a visualização das entidades. Motivo: o Core expõe /user/{uid} e as entidades
 * de perfil por padrão; a regra fica no ponto único de acesso, não em Twig nem em rota.
 */
final class LegacyAccessHooks {

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if ($operation !== 'view' || !$this->isLegacy($entity)) {
      return NULL;
    }
    if ($account->hasPermission('administer users')) {
      return NULL;
    }
    return AccessResult::forbidden('Conta histórica sem página pública individual.')
      ->addCacheableDependency($entity)
      ->addCacheContexts(['user.permissions']);
  }

  /**
   * Indica se a entidade é usuário legado ou perfil de origem histórica.
   */
  private function isLegacy(EntityInterface $entity): bool {
    if ($entity instanceof UserInterface) {
      return $entity->hasRole(LegacyAccountManager::ROLE);
    }
    if ($entity instanceof ProfileInterface && $entity->bundle() === LegacyAccountManager::PROFILE_TYPE) {
      return $entity->hasField('field_forum_origem_id') && !$entity->get('field_forum_origem_id')->isEmpty();
    }
    return FALSE;
  }

}
