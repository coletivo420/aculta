<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Keeps lesson and activity administration inside the selected course.
 */
final class CourseFirstAdminMenuHooks {

  /**
   * Removes global content shortcuts supplied by the LMS contrib module.
   *
   * Lessons are ordered on the course entity and activities belong to lessons.
   * Their contrib collections remain available to entity access checks, while
   * the administrative navigation starts at the course collection.
   *
   * @param array<string, mixed> $links
   *   Discovered menu link plugin definitions.
   */
  #[Hook('menu_links_discovered_alter')]
  public function alterMenuLinks(array &$links): void {
    unset(
      $links['system.admin_lms.lessons'],
      $links['system.admin_lms.activities'],
      $links['views_view:views.classes_admin.page'],
    );
  }

}
