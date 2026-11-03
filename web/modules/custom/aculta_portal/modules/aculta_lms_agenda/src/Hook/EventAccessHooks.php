<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_lms_agenda\EventClassAccess;

/**
 * Enforces class membership for events attached to LMS classes.
 */
final class EventAccessHooks {

  public function __construct(
    private readonly EventClassAccess $eventAccess,
  ) {}

  /**
   * Denies access to class events unless the account belongs to that class.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult {
    if ($entity->getEntityTypeId() !== 'aculta_event' || $operation !== 'view'
      || !$entity->hasField('field_turma') || $entity->get('field_turma')->isEmpty()) {
      return AccessResult::neutral();
    }

    return $this->eventAccess->access($entity, $account);
  }

}
