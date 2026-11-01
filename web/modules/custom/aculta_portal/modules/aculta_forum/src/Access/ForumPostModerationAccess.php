<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Restricts revision queue and review forms to eligible Forum revisions.
 */
final class ForumPostModerationAccess {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Restricts the moderation queue to the main administrative purpose.
   */
  public function queueAccess(): AccessResultInterface {
    return AccessResult::allowedIf($this->domainPurposeManager->getCurrentPurpose() === 'main')
      ->addCacheContexts(['domain', 'user.permissions']);
  }

  /**
   * Checks access to one exact pending review revision on the main purpose.
   */
  public function reviewRevision(AccountInterface $account, string $revision_id): AccessResultInterface {
    $post = $this->entityTypeManager->getStorage('aculta_forum_post')->loadRevision($revision_id);
    if ($post === NULL) {
      return AccessResult::forbidden()->addCacheContexts(['domain', 'user.permissions']);
    }

    $context = $this->domainPurposeManager->getCurrentPurpose() === 'main'
      ? AccessResult::allowed()
      : AccessResult::forbidden();
    $state = $post->hasField('moderation_state') && !$post->get('moderation_state')->isEmpty()
      ? (string) $post->get('moderation_state')->value
      : '';
    $isReviewRevision = $state === 'review';
    $transitionAccess = $post->access('update', $account, TRUE);
    $topic = $post->hasField('topic') ? $post->get('topic')->entity : NULL;
    $topicDomainPurpose = $topic instanceof NodeInterface
      && $topic->hasField('field_domain_source')
      && !$topic->get('field_domain_source')->isEmpty()
      ? $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id)
      : NULL;
    $topicAccess = $topic instanceof NodeInterface
      && $topic->bundle() === 'aculta_forum_topic'
      && $topic->isPublished()
      && $topicDomainPurpose === 'forum'
      ? $topic->access('view', $account, TRUE)
      : AccessResult::forbidden();

    $access = $context
      ->andIf(AccessResult::allowedIf($isReviewRevision)->addCacheableDependency($post))
      ->andIf($transitionAccess)
      ->andIf($topicAccess)
      ->addCacheableDependency($post)
      ->addCacheContexts(['domain', 'user', 'user.permissions']);
    if ($topic instanceof NodeInterface) {
      $access->addCacheableDependency($topic);
    }
    return $access;
  }

  /**
   * Checks access to the revision history of a Forum post.
   */
  public function revisionHistory(AccountInterface $account, ForumPost $aculta_forum_post): AccessResultInterface {
    $context = $this->domainPurposeManager->getCurrentPurpose() === 'main'
      ? AccessResult::allowed()
      : AccessResult::forbidden();
    $topic = $aculta_forum_post->hasField('topic') ? $aculta_forum_post->get('topic')->entity : NULL;
    $topicPurpose = $topic instanceof NodeInterface
      && $topic->bundle() === 'aculta_forum_topic'
      && $topic->isPublished()
      && $topic->hasField('field_domain_source')
      && !$topic->get('field_domain_source')->isEmpty()
      ? $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id)
      : NULL;
    $topicAccess = $topicPurpose === 'forum'
      ? $topic->access('view', $account, TRUE)
      : AccessResult::forbidden();

    $access = $context
      ->andIf($topicAccess)
      ->addCacheableDependency($aculta_forum_post)
      ->addCacheContexts(['domain', 'user', 'user.permissions']);
    if ($topic instanceof NodeInterface) {
      $access->addCacheableDependency($topic);
    }
    return $access;
  }

  /**
   * Checks comparison access and binds both revisions to the history post.
   */
  public function compareRevisions(
    AccountInterface $account,
    ForumPost $aculta_forum_post,
    string $left_revision,
    string $right_revision,
  ): AccessResultInterface {
    $historyAccess = $this->revisionHistory($account, $aculta_forum_post);
    if (!$historyAccess->isAllowed()) {
      return $historyAccess;
    }

    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $left = $storage->loadRevision($left_revision);
    $right = $storage->loadRevision($right_revision);
    if ($left === NULL || $right === NULL) {
      return AccessResult::forbidden()
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.path']);
    }

    $belongsToPost = (string) $left->id() === (string) $aculta_forum_post->id()
      && (string) $right->id() === (string) $aculta_forum_post->id()
      && (string) $left->getRevisionId() === $left_revision
      && (string) $right->getRevisionId() === $right_revision
      && (string) $left->getRevisionId() !== (string) $right->getRevisionId();
    return $historyAccess
      ->andIf(AccessResult::allowedIf($belongsToPost)
        ->addCacheableDependency($left)
        ->addCacheableDependency($right)
        ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.path']));
  }

}
