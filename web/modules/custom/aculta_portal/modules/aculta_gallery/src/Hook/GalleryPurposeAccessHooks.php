<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Galerias são publicadas somente na Revista (decisão D13, docs/ARQUITETURA.md §4.1).
 *
 * A visualização de galeria só é permitida no purpose `magazine`. Nos demais purposes é negada, com
 * contexto de cache `domain`, para que a decisão não vaze pelo cache de outro host.
 */
final class GalleryPurposeAccessHooks {

  /** Purpose onde galerias são publicadas. */
  private const PURPOSE = 'magazine';

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposeManager,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($entity->getEntityTypeId() !== 'media_gallery' || $operation !== 'view') {
      return AccessResult::neutral();
    }
    if ($this->purposeManager->getCurrentPurpose() === self::PURPOSE) {
      return AccessResult::neutral();
    }
    return AccessResult::forbidden('Galerias são publicadas somente na Revista.')
      ->addCacheContexts(['domain'])
      ->addCacheableDependency($entity);
  }

}
