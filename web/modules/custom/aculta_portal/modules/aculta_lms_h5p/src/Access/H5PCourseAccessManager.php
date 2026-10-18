<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;

/** Checks that an H5P content ID belongs to a course the account may access. */
final class H5PCourseAccessManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Checks access for an educator editor request.
   *
   * A zero content ID is only valid when creating new content in an editable
   * course. Existing content must already be referenced by that course.
   */
  public function editorAccess(int $groupId, int $contentId, AccountInterface $account): AccessResult {
    return $this->courseAccess($groupId, $contentId, $account, TRUE);
  }

  /** Checks access for student playback, progress and protected content files. */
  public function studentAccess(int $groupId, int $contentId, AccountInterface $account): AccessResult {
    return $this->courseAccess($groupId, $contentId, $account, FALSE);
  }

  private function courseAccess(int $groupId, int $contentId, AccountInterface $account, bool $editor): AccessResult {
    $cacheContexts = ['user', 'user.permissions', 'user.group_permissions'];
    $course = $this->entityTypeManager->getStorage('group')->load($groupId);
    if (!$course instanceof CourseInterface || !$course->hasField('field_h5p_activities')) {
      return AccessResult::forbidden()
        ->addCacheContexts($cacheContexts)
        ->addCacheTags(['group_list']);
    }

    $courseAccess = $editor
      ? $course->access('update', $account, TRUE)
      : $course->takeAccess($account);
    $result = AccessResult::allowedIf($courseAccess->isAllowed())
      ->addCacheableDependency($course)
      ->addCacheableDependency($courseAccess)
      ->addCacheContexts($cacheContexts);

    if ($contentId < 1) {
      return $result->andIf(AccessResult::allowedIf($editor));
    }

    $content = $this->entityTypeManager->getStorage('h5p_content')->load($contentId);
    if ($content === NULL) {
      return $result->andIf(AccessResult::forbidden()->addCacheTags(['h5p_content_list']));
    }

    $referenced = FALSE;
    foreach ($course->get('field_h5p_activities') as $item) {
      if ((int) $item->h5p_content_id === $contentId) {
        $referenced = TRUE;
        break;
      }
    }

    return $result
      ->andIf(AccessResult::allowedIf($referenced))
      ->addCacheableDependency($content);
  }

}
