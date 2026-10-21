<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Access;

use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\aculta_user\EditWindowExemptionInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Keeps contextual educator rights in LMS discussions outside the author window.
 *
 * The settings form promises that educators keep their course permissions. The
 * LMS context decides who may moderate, so this exemption reuses that decision.
 */
final class LmsEditWindowExemption implements EditWindowExemptionInterface {

  public function __construct(
    private readonly LmsDiscussionContextProvider $contextProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    if (!$entity instanceof CommentInterface) {
      return AccessResult::neutral();
    }

    $host = $entity->getCommentedEntity();
    if (!$host instanceof NodeInterface || !$this->contextProvider->supports($host)) {
      return AccessResult::neutral()->addCacheableDependency($entity);
    }

    $moderation = $this->contextProvider->getContext($host, $account)->getModerationAccess();
    return $moderation->isAllowed()
      ? AccessResult::allowed()->addCacheableDependency($moderation)->addCacheContexts(['user.group_permissions'])
      : AccessResult::neutral()->addCacheableDependency($moderation)->addCacheContexts(['user.group_permissions']);
  }

}
