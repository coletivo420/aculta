<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Access;

use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\node\NodeInterface;

/**
 * Lists only LMS contexts where the current account is an authorized Educator.
 */
final class LmsForumModerationManager {

  private const DISCUSSION_FIELD = 'field_aculta_discussion';

  private const TOPIC_BUNDLE = 'aculta_lms_topic';

  private const TOPIC_RELATIONSHIP = 'group_node:aculta_lms_topic';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LmsDiscussionContextProvider $contextProvider,
  ) {}

  /**
   * Returns route access only when the account moderates at least one Group.
   */
  public function overviewAccess(AccountInterface $account): AccessResultInterface {
    [$groups, $cacheability] = $this->getModerationGroups($account);
    $access = $groups === [] ? AccessResult::forbidden() : AccessResult::allowed();
    return $access->addCacheableDependency($cacheability);
  }

  /**
   * Returns route access for a specific course or class Group.
   */
  public function groupAccess(GroupInterface $group, AccountInterface $account): AccessResultInterface {
    return $this->contextProvider->getGroupModerationAccess($group, $account);
  }

  /**
   * Returns the one LMS Group that owns a valid discussion Comment.
   */
  public function getCommentGroup(CommentInterface $comment): ?GroupInterface {
    if ($comment->bundle() !== 'aculta_discussion_post'
      || $comment->getFieldName() !== self::DISCUSSION_FIELD
      || $comment->getCommentedEntityTypeId() !== 'node') {
      return NULL;
    }

    $host = $comment->getCommentedEntity();
    if (!$host instanceof NodeInterface || $host->bundle() !== self::TOPIC_BUNDLE) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('group_relationship');
    if (!$storage instanceof GroupRelationshipStorageInterface) {
      return NULL;
    }
    $relationships = $storage->loadByEntity($host, self::TOPIC_RELATIONSHIP);
    if (count($relationships) !== 1) {
      return NULL;
    }

    $group = reset($relationships)->getGroup();
    return $group instanceof GroupInterface
      && in_array($group->bundle(), ['lms_course', 'lms_class'], TRUE)
      ? $group
      : NULL;
  }

  /**
   * Checks a status transition for a Comment in exactly the route's Group.
   */
  public function commentModerationAccess(
    GroupInterface $group,
    CommentInterface $comment,
    string $decision,
    AccountInterface $account,
  ): AccessResultInterface {
    $host = $comment->getCommentedEntity();
    $commentGroup = $this->getCommentGroup($comment);
    $matchesContext = $host instanceof NodeInterface
      && $commentGroup instanceof GroupInterface
      && (string) $commentGroup->id() === (string) $group->id();
    $isRejected = $comment->hasField('field_aculta_rejected')
      && !$comment->get('field_aculta_rejected')->isEmpty()
      && (bool) $comment->get('field_aculta_rejected')->value;
    $transitionAllowed = match ($decision) {
      'approve' => !$comment->isPublished(),
      'reject' => !$isRejected,
      default => FALSE,
    };

    $contextAccess = $this->groupAccess($group, $account);
    $commentAccess = AccessResult::allowedIf($matchesContext && $transitionAllowed)
      ->addCacheableDependency($comment)
      ->addCacheableDependency($group);
    if ($host instanceof NodeInterface) {
      $commentAccess->addCacheableDependency($host);
    }
    if ($commentGroup instanceof GroupInterface) {
      $commentAccess->addCacheableDependency($commentGroup);
    }
    return $contextAccess->andIf($commentAccess);
  }

  /**
   * Applies the decision after revalidating contextual access.
   */
  public function applyModerationDecision(
    GroupInterface $group,
    CommentInterface $comment,
    string $decision,
    AccountInterface $account,
  ): bool {
    if (!$this->commentModerationAccess($group, $comment, $decision, $account)->isAllowed()) {
      return FALSE;
    }

    if ($decision === 'approve') {
      $comment->setPublished();
      $comment->set('field_aculta_rejected', 0);
    }
    else {
      $comment->setUnpublished();
      $comment->set('field_aculta_rejected', 1);
    }
    $comment->save();
    return TRUE;
  }

  /**
   * Gets the groups that the account may moderate and their cache metadata.
   *
   * The Group entity query applies installed Group query access first. The
   * contextual Educator permission is then checked again for every result.
   * This keeps student memberships out of the returned list.
   *
   * @return array{0: list<\Drupal\group\Entity\GroupInterface>, 1: \Drupal\Core\Cache\CacheableMetadata}
   *   Authorized groups and metadata for the result, including denied checks.
   */
  public function getModerationGroups(AccountInterface $account): array {
    $cacheability = (new CacheableMetadata())
      ->setCacheContexts(['user', 'user.group_permissions'])
      ->setCacheTags(['group_list', 'group_relationship_list']);

    if ($account->isAnonymous()) {
      return [[], $cacheability];
    }

    $storage = $this->entityTypeManager->getStorage('group');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', ['lms_course', 'lms_class'], 'IN')
      ->sort('label')
      ->execute();
    $groups = $storage->loadMultiple($ids);
    $moderatable = [];
    foreach ($groups as $group) {
      if (!$group instanceof GroupInterface) {
        continue;
      }
      $access = $this->contextProvider->getGroupModerationAccess($group, $account);
      $cacheability->addCacheableDependency($group);
      $cacheability->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $moderatable[] = $group;
      }
    }

    return [$moderatable, $cacheability];
  }

}
