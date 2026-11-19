<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Restricts private student previews to educators of the owning course.
 */
final class CourseLessonPreviewAccessCheck {

  public function __construct(
    private readonly CourseAuthoringAccessCheck $authoringAccess,
  ) {}

  /**
   * Checks the existing educator grant and exact course-to-lesson relation.
   */
  public function access(GroupInterface $course, ContentEntityInterface $lesson): AccessResult {
    return $this->authoringAccess->access($course, $lesson);
  }

}
