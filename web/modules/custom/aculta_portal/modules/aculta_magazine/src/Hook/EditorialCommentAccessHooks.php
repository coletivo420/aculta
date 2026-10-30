<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_magazine\Access\EditorialCapability;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Enforces Studio's configured contextual moderation cap on direct Comment routes. */
final class EditorialCommentAccessHooks {

  public function __construct(private readonly EditorialCapability $capabilities, private readonly DiscussionContextResolver $discussionContexts) {}

  /**
   * Restricts direct comment mutations to configured publication moderators.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if (!$entity instanceof CommentInterface || !in_array($operation, ['approve', 'update', 'delete'], TRUE)
      || $entity->getFieldName() !== 'field_aculta_discussion') {
      return AccessResult::neutral();
    }
    $host = $entity->getCommentedEntity();
    $context = $host ? $this->discussionContexts->resolve($host, $account) : NULL;
    if ($context === NULL || $context->getType() !== 'publication') {
      return AccessResult::neutral()->addCacheableDependency($entity);
    }
    $capability = $this->capabilities->access(EditorialCapability::COMMENTS, $account);
    if (!$capability->isAllowed()) {
      return AccessResult::forbidden()->addCacheableDependency($entity)->addCacheableDependency($context)->addCacheableDependency($capability);
    }
    return $context->getModerationAccess()->addCacheableDependency($entity)->addCacheableDependency($context)->addCacheableDependency($capability);
  }

}
