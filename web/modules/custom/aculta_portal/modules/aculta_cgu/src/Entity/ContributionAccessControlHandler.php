<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Entity;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_cgu\Adapter\ContributionAdapterRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access control for private proposals. */
final class ContributionAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    protected readonly ContributionAdapterRegistry $adapters,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc} */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $registry = $container->has('aculta_cgu.contribution_adapter_registry')
      ? $container->get('aculta_cgu.contribution_adapter_registry')
      : $container->get('aculta_contributions.adapter_registry');
    return new static($entity_type, $registry);
  }

  /**
   * {@inheritdoc} */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    if ($account->hasPermission('administer aculta contributions')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if (!$entity instanceof Contribution || !$account->isAuthenticated()) {
      return AccessResult::forbidden()->cachePerUser()->cachePerPermissions();
    }

    try {
      $adapter = $this->adapters->get((string) $entity->get('source')->value);
    }
    catch (\InvalidArgumentException) {
      return AccessResult::forbidden()->cachePerUser()->cachePerPermissions();
    }
    return $adapter->entityAccess($account, $entity, (string) $operation)
      ->addCacheableDependency($entity)
      ->addCacheContexts(['user', 'user.permissions']);
  }

  /**
   * {@inheritdoc} */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer aculta contributions')
      ->cachePerPermissions();
  }

}
