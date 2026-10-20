<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Context;

use Drupal\aculta_forum\Context\DiscussionContext;
use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupAccessResult;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves private LMS discussion topics through their Group relationship.
 *
 * This provider deliberately fails closed when a topic has no unique Group
 * relationship, an unsupported Group bundle, or an invalid class/course chain.
 */
final class LmsDiscussionContextProvider implements DiscussionContextProviderInterface {

  private const TOPIC_BUNDLE = 'aculta_lms_topic';

  private const NODE_RELATIONSHIP = 'group_node:aculta_lms_topic';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports(EntityInterface $entity): bool {
    return $entity instanceof NodeInterface
      && $entity->bundle() === self::TOPIC_BUNDLE;
  }

  /**
   * Prevents ungrouped or ambiguously grouped LMS topics from being public.
   *
   * Valid Group relationships return neutral so the Group module's own
   * entity-access handler remains authoritative for the configured roles.
   */
  public function getGroupRelationshipAccess(EntityInterface $entity): AccessResultInterface {
    if (!$this->supports($entity) || $entity->isNew() || !$entity->uuid()) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    $relationships = $this->relationshipStorage()->loadByEntity($entity, self::NODE_RELATIONSHIP);
    if (count($relationships) !== 1) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->addCacheTags([
          'group_relationship_list:plugin:' . self::NODE_RELATIONSHIP,
          'group_relationship_list:entity:' . $entity->id(),
        ]);
    }

    $relationship = reset($relationships);
    $group = $relationship->getGroup();
    if (!$group instanceof GroupInterface
      || !in_array($group->bundle(), ['lms_course', 'lms_class'], TRUE)) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($relationship);
    }

    $dependencies = [
      $entity,
      $relationship,
      $group,
    ];
    if ($group->bundle() === 'lms_class') {
      $parents = $this->relationshipStorage()->loadByEntity($group, 'lms_classes');
      $parent = count($parents) === 1 ? reset($parents) : NULL;
      $course = $parent?->getGroup();
      if (!$course instanceof CourseInterface) {
        return AccessResult::forbidden()
          ->addCacheableDependency($entity)
          ->addCacheableDependency($relationship)
          ->addCacheableDependency($group)
          ->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $group->id()]);
      }
      $dependencies[] = $parent;
      $dependencies[] = $course;
    }

    $access = AccessResult::neutral();
    foreach ($dependencies as $dependency) {
      $access->addCacheableDependency($dependency);
    }
    return $access;
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(EntityInterface $entity, AccountInterface $account): DiscussionContextInterface {
    if (!$this->supports($entity) || $entity->isNew() || !$entity->uuid()) {
      throw new \LogicException('LMS discussion context requires a persisted aculta_lms_topic node with a UUID.');
    }

    $relationships = $this->relationshipStorage()->loadByEntity($entity, self::NODE_RELATIONSHIP);
    $group = count($relationships) === 1 ? reset($relationships)->getGroup() : NULL;
    $validGroup = $group instanceof GroupInterface
      && in_array($group->bundle(), ['lms_course', 'lms_class'], TRUE);

    $nodeAccess = $entity->access('view', $account, TRUE);
    $groupAccess = $validGroup
      ? $group->access('view', $account, TRUE)
      : AccessResult::forbidden()->addCacheableDependency($entity);
    $contextAccess = $validGroup
      ? $this->contextMembershipAccess($group, $account)
      : AccessResult::forbidden()->addCacheableDependency($entity);

    $readAccess = $nodeAccess
      ->andIf($groupAccess)
      ->andIf($contextAccess);

    $commentField = $entity->hasField('field_aculta_discussion')
      ? $entity->get('field_aculta_discussion')
      : NULL;
    $commentStatus = $commentField?->getValue()[0]['status'] ?? 0;
    $writeAccess = $readAccess
      ->andIf(AccessResult::allowedIf((int) $commentStatus === 2)->addCacheableDependency($entity))
      ->andIf(AccessResult::allowedIf($account->hasPermission('post comments'))->addCacheContexts(['user.permissions']));

    $moderationAccess = $validGroup
      ? $this->educatorAccess($group, $account)
      : AccessResult::forbidden()->addCacheableDependency($entity);
    $moderationAccess = $readAccess->andIf($moderationAccess);

    return new DiscussionContext(
      $entity,
      'lms',
      'lms:node:' . $entity->uuid(),
      $readAccess,
      $writeAccess,
      $moderationAccess,
      $entity->toUrl('canonical'),
      'group_members_only',
      'private_search_only',
      'membership_revalidated',
    );
  }

  /**
   * Evaluates membership in the exact course or class context.
   */
  private function contextMembershipAccess(GroupInterface $group, AccountInterface $account): AccessResultInterface {
    if ($group instanceof CourseInterface) {
      return $group->takeAccess($account)
        ->orIf($this->educatorAccess($group, $account));
    }

    $membershipAccess = $this->membershipAccess($group, $account);

    $parents = $this->relationshipStorage()->loadByEntity($group, 'lms_classes');
    if (count($parents) !== 1) {
      return AccessResult::forbidden()
        ->addCacheableDependency($group)
        ->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $group->id()]);
    }
    $course = reset($parents)->getGroup();
    if (!$course instanceof CourseInterface) {
      return AccessResult::forbidden()->addCacheableDependency($group);
    }

    return $membershipAccess
      ->orIf($this->educatorAccess($course, $account))
      ->addCacheableDependency($group)
      ->addCacheableDependency($course);
  }

  /**
   * Checks the account's real Group membership without legacy wrappers.
   */
  private function membershipAccess(GroupInterface $group, AccountInterface $account): AccessResultInterface {
    $uid = $account->id();
    if ($uid === NULL) {
      return AccessResult::forbidden()
        ->addCacheContexts(['user'])
        ->addCacheTags(['group_relationship_list:plugin:group_membership:group:' . $group->id()]);
    }

    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user === NULL) {
      return AccessResult::forbidden()
        ->addCacheContexts(['user'])
        ->addCacheTags(['group_relationship_list:plugin:group_membership:group:' . $group->id()]);
    }

    $memberships = $this->relationshipStorage()->loadByEntity($user, 'group_membership');
    foreach ($memberships as $membership) {
      if ((string) $membership->getGroupId() === (string) $group->id()) {
        return AccessResult::allowed()
          ->addCacheableDependency($membership)
          ->addCacheableDependency($user)
          ->addCacheableDependency($group)
          ->addCacheContexts(['user']);
      }
    }

    return AccessResult::forbidden()
      ->addCacheableDependency($user)
      ->addCacheableDependency($group)
      ->addCacheContexts(['user'])
      ->addCacheTags(['group_relationship_list:plugin:group_membership:group:' . $group->id()]);
  }

  /**
   * Grants moderation only to educators with permission in this course.
   */
  private function educatorAccess(GroupInterface $group, AccountInterface $account): AccessResultInterface {
    $course = $group;
    if ($group->bundle() === 'lms_class') {
      $parents = $this->relationshipStorage()->loadByEntity($group, 'lms_classes');
      if (count($parents) !== 1) {
        return AccessResult::forbidden()
          ->addCacheableDependency($group)
          ->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $group->id()]);
      }
      $course = reset($parents)->getGroup();
    }

    if (!$course instanceof CourseInterface) {
      return AccessResult::forbidden()->addCacheableDependency($group);
    }

    return GroupAccessResult::allowedIfHasGroupPermission($course, $account, 'grade students')
      ->orIf(AccessResult::forbidden())
      ->addCacheableDependency($course);
  }

  /**
   * Gets the installed Group relationship storage.
   */
  private function relationshipStorage(): GroupRelationshipStorageInterface {
    $storage = $this->entityTypeManager->getStorage('group_relationship');
    if (!$storage instanceof GroupRelationshipStorageInterface) {
      throw new \LogicException('The Group relationship storage is unavailable.');
    }
    return $storage;
  }

}
