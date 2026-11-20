<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;


use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentingStatus;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Validates access to reply in a discussion context.
 */
class DiscussionReplyAccess {

  public function __construct(
    protected readonly DiscussionContextResolver $contextResolver,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DiscussionOriginResolverInterface $originResolver,
    protected readonly TopicAuthorDeletion $topicState,
  ) {}

  /**
   * Checks whether the given account can reply to the discussion host.
   */
  public function checkAccess(EntityInterface $host, AccountInterface $account, ?int $parentId = NULL): AccessResultInterface {
    // Tópico fechado pela moderação: leitura continua, novas respostas não.
    if ($host instanceof NodeInterface && ($this->topicState->isClosed((int) $host->id()) || $this->topicState->isArchived((int) $host->id()))) {
      return AccessResult::forbidden('Topic is closed by moderation.')->addCacheableDependency($host);
    }
    $origin = $this->originResolver->classify($host);
    if (in_array($origin->origin, [DiscussionOrigin::ARCHIVE, DiscussionOrigin::UNKNOWN], TRUE)) {
      return AccessResult::forbidden('Discussion origin is not writable.')
        ->addCacheableDependency($origin);
    }
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

    if (!$host instanceof FieldableEntityInterface) {
      return AccessResult::forbidden('Host discussion entity cannot carry discussion fields.')
        ->addCacheableDependency($host);
    }

    // Check if discussion field is open if present.
    if ($host->hasField('field_aculta_discussion') && !$host->get('field_aculta_discussion')->isEmpty()) {
      $statusBruto = $host->get('field_aculta_discussion')->status;
      $status = is_scalar($statusBruto) ? (int) $statusBruto : 0;
      if ($status !== CommentingStatus::Open->value) {
        return AccessResult::forbidden('Discussion is closed.')->addCacheableDependency($host);
      }
    }

    // If replying to a specific parent comment, validate parent.
    if ($parentId !== NULL && $parentId > 0) {
      if ($host instanceof NodeInterface && $host->bundle() === 'aculta_forum_topic') {
        $parentPost = $this->entityTypeManager->getStorage('aculta_forum_post')->load($parentId);
        if (!$parentPost instanceof ForumPost) {
          return AccessResult::forbidden('Parent ForumPost not found.');
        }
        $parentOrigin = $this->originResolver->classify($parentPost);
        if (in_array($parentOrigin->origin, [DiscussionOrigin::ARCHIVE, DiscussionOrigin::UNKNOWN], TRUE)) {
          return AccessResult::forbidden('Parent ForumPost origin is not writable.')
            ->addCacheableDependency($parentOrigin);
        }
        if (!$parentPost->isPublished()
          || !$parentPost->isDefaultRevision()
          || (string) $parentPost->get('topic')->target_id !== (string) $host->id()) {
          return AccessResult::forbidden('Parent ForumPost is not part of this published topic.')
            ->addCacheableDependency($parentPost);
        }
        $parentAccess = $parentPost->access('view', $account, TRUE);
        if (!$parentAccess->isAllowed()) {
          return AccessResult::forbidden('Parent ForumPost is not accessible.')
            ->addCacheableDependency($parentAccess);
        }

        return AccessResult::allowed()
          ->addCacheableDependency($host)
          ->addCacheableDependency($parentPost)
          ->addCacheableDependency($parentAccess)
          ->addCacheableDependency($readAccess)
          ->addCacheableDependency($writeAccess);
      }

      $parentComment = $this->entityTypeManager->getStorage('comment')->load($parentId);
      if (!$parentComment instanceof CommentInterface) {
        return AccessResult::forbidden('Parent comment not found.');
      }
      $parentOrigin = $this->originResolver->classify($parentComment);
      if (in_array($parentOrigin->origin, [DiscussionOrigin::ARCHIVE, DiscussionOrigin::UNKNOWN], TRUE)) {
        return AccessResult::forbidden('Parent discussion origin is not writable.')
          ->addCacheableDependency($parentOrigin);
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
