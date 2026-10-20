<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Hook;

use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Keeps LMS topic Nodes private unless they belong to exactly one LMS Group.
 */
final class LmsDiscussionEntityAccessHooks {

  public function __construct(
    private readonly LmsDiscussionContextProvider $contextProvider,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'view' || !$this->contextProvider->supports($entity)) {
      return AccessResult::neutral();
    }

    return $this->contextProvider->getGroupRelationshipAccess($entity);
  }

}
