<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentingStatus;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Validates access to reply in a discussion context.
 */
class DiscussionReplyAccess {

  public function __construct(
    protected readonly DiscussionContextResolver $contextResolver,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Checks whether the given account can reply to the discussion host.
   */
  public function checkAccess(EntityInterface $host, AccountInterface $account, ?int $parentId = NULL): AccessResultInterface {
    $context = $this->contextResolver->resolve($host, $account);
    if ($context === NULL) {
      return AccessResult::forbidden('Host does not have a registered discussion context.')->addCacheableDependency($host);
    }

    $readAccess = $context->getReadAccess();
    if (!$readAccess->isAllowed()) {
      return AccessResult::forbidden('Read access denied to discussion.')->addCacheableDependency($readAccess);
    }

    $writeAccess = $context->getWriteAccess();
    if (!$writeAccess->isAllowed()) {
      return AccessResult::forbidden('Write access denied to discussion.')->addCacheableDependency($writeAccess);
    }

    // Check if discussion field is open if present.
    if ($host->hasField('field_aculta_discussion') && !$host->get('field_aculta_discussion')->isEmpty()) {
      $status = (int) $host->get('field_aculta_discussion')->status;
      if ($status !== CommentingStatus::Open->value) {
        return AccessResult::forbidden('Discussion is closed.')->addCacheableDependency($host);
      }
    }

    // If replying to a specific parent comment, validate parent.
    if ($parentId !== NULL && $parentId > 0) {
      $parentComment = $this->entityTypeManager->getStorage('comment')->load($parentId);
      if (!$parentComment instanceof CommentInterface) {
        return AccessResult::forbidden('Parent comment not found.');
      }
      if (!$parentComment->isPublished()) {
        return AccessResult::forbidden('Parent comment is not published.')->addCacheableDependency($parentComment);
      }
      if ($parentComment->getCommentedEntityTypeId() !== $host->getEntityTypeId() || (string) $parentComment->getCommentedEntityId() !== (string) $host->id()) {
        return AccessResult::forbidden('Parent comment does not belong to the host entity.');
      }
      $parentAccess = $parentComment->access('view', $account, TRUE);
      if (!$parentAccess->isAllowed()) {
        return AccessResult::forbidden('Parent comment is not accessible.')->addCacheableDependency($parentAccess);
      }
    }

    return AccessResult::allowed()
      ->addCacheableDependency($host)
      ->addCacheableDependency($readAccess)
      ->addCacheableDependency($writeAccess);
  }

}
