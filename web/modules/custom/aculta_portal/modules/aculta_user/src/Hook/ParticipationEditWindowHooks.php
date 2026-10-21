<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\ParticipationEditWindow;
use Drupal\aculta_user\ParticipationRules;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applies the discussion author's edit window to Entity Access.
 */
final class ParticipationEditWindowHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.participation_edit_window')]
    private readonly ParticipationEditWindow $editWindow,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($entity->getEntityTypeId() === 'comment' && $entity->bundle() !== ParticipationRules::DISCUSSION_COMMENT_TYPE) {
      return AccessResult::neutral();
    }
    if (!in_array($entity->getEntityTypeId(), ['comment', 'aculta_forum_post'], TRUE)) {
      return AccessResult::neutral();
    }

    return $this->editWindow->access($entity, $operation, $account);
  }

}
