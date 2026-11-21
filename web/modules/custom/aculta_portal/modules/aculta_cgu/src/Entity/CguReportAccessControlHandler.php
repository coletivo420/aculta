<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_cgu\ModerationScope;
use Drupal\aculta_cgu\Scope\ReportTargetScope;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controle de acesso a denúncias de UGC.
 *
 * Moderadores só acessam denúncias do purpose que moderam (ARQ-06A). Purposes com
 * adaptador de alvo (ex.: LMS por Group) liberam apenas as denúncias sobre alvos
 * que a conta pode moderar. O denunciante vê somente as próprias denúncias.
 */
final class CguReportAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  public function __construct(EntityTypeInterface $entity_type, protected readonly ReportTargetScope $targetScope) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static($entity_type, $container->get('aculta_cgu.report_target_scope'));
  }

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
    if ($entity instanceof CguReport && $this->targetScope->allowsReport($entity, $account)) {
      return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
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

