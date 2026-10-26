<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Account\NicknameRegistry;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps Profile nickname writes and the UID slug projection in one transaction.
 */
final class ProfileNicknameRegistryHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.nickname_registry')]
    private readonly NicknameRegistry $registry,
  ) {}

  /**
   * Reserves a canonical route slug and syncs all role Profiles of the UID.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity instanceof ProfileInterface) {
      $this->registry->synchronizeProfile($entity);
    }
  }

  /**
   * Releases the current UID claim after the final role Profile is removed.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity instanceof ProfileInterface) {
      $this->registry->removeDeletedProfile($entity);
    }
  }

}
