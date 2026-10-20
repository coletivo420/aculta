<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Access;

use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Lists only LMS contexts where the current account is an authorized Educator.
 */
final class LmsForumModerationManager {

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
