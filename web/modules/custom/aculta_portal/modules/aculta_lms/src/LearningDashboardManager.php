<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_portal\AccountCoursesManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\lms\TrainingManager;
use Drupal\user\UserInterface;

/**
 * Read-only LMS dashboard data assembled from Group and LMS source entities.
 */
final class LearningDashboardManager {

  public function __construct(
    private readonly ?AccountCoursesManager $accountCourses,
    private readonly CourseCertificateManagerInterface $certificates,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?TrainingManager $trainingManager,
  ) {}

  /**
   * Gets course and completion information belonging to one learner.
   *
   * @return array
   *   The learner's courses, certificates and cache metadata.
   */
  public function studentDashboard(AccountInterface $account): array {
    $metadata = (new CacheableMetadata())
      ->addCacheContexts(['user', 'user.permissions'])
      ->addCacheTags(['lms_course_status_list', 'lms_lesson_status_list']);
    $courses = [];
    // The Portal service is optional in isolated LMS Kernel tests; when the
    // full site is installed, it supplies the canonical purpose-aware links.
    foreach ($this->accountCourses?->getCourses($account) ?? [] as $course_data) {
      $course = $this->entityTypeManager->getStorage('group')->load($course_data['id']);
      if (!$course instanceof CourseInterface) {
        continue;
      }
      $courseAccess = $course->takeAccess($account);
      $metadata
        ->addCacheableDependency($course)
        ->addCacheableDependency($courseAccess);
      if (!$courseAccess->isAllowed()) {
        continue;
      }
      $availableLessonIds = [];
      foreach ($course->get(CourseInterface::LESSONS)->referencedEntities() as $lesson) {
        if (!$lesson instanceof LessonInterface) {
          continue;
        }
        $metadata->addCacheableDependency($lesson);
        // LMS lesson routes are protected by takeAccess() on the parent course.
        // Generic lesson view access is for entity use/edit permissions and
        // denies enrolled learners even when the lesson is published in their
        // course curriculum.
        if ($lesson instanceof EntityPublishedInterface && $lesson->isPublished()) {
          $availableLessonIds[] = (string) $lesson->id();
        }
      }
      if ($this->trainingManager === NULL) {
        throw new \LogicException('Drupal LMS must be enabled to build learning dashboards.');
      }
      $status = $this->trainingManager->loadCourseStatus($course, $account, ['current' => TRUE]);
      $total = count($availableLessonIds);
      $completed = 0;
      if ($status !== NULL) {
        $metadata->addCacheableDependency($status);
        if ($availableLessonIds !== []) {
          $rows = $this->entityTypeManager->getStorage('lms_lesson_status')
            ->getAggregateQuery()
            // The status is fetched for this authenticated user above; this
            // internal count is scoped to that exact course status and its
            // currently viewable lesson entities.
            ->accessCheck(FALSE)
            ->condition('course_status', $status->id())
            ->condition(LessonStatusInterface::LESSON_FIELD, $availableLessonIds, 'IN')
            ->condition('finished', 0, '<>')
            ->groupBy(LessonStatusInterface::LESSON_FIELD)
            ->execute();
          $completed = count($rows);
        }
      }
      $courses[] = $course_data + [
        'completed_lessons' => $completed,
        'total_lessons' => $total,
        'progress_percent' => $total > 0 ? min(100, (int) round($completed / $total * 100)) : 0,
        'pending_evaluation' => $course_data['status'] === CourseStatusInterface::STATUS_NEEDS_EVALUATION,
      ];
      $metadata->addCacheTags($course_data['cache_tags']);
    }

    $certificates = $this->certificates->loadForStudent((int) $account->id());
    foreach ($certificates as $certificate) {
      $metadata->addCacheableDependency($certificate);
    }
    return ['courses' => $courses, 'certificates' => $certificates, 'cache' => $metadata];
  }

  /**
   * Checks course-scoped grading authority for an LMS class dashboard.
   */
  public function classDashboardAccess(GroupInterface $class, AccountInterface $account): AccessResult {
    $course = $this->courseForClass($class);
    if ($class->bundle() !== 'lms_class' || !$course instanceof CourseInterface) {
      return AccessResult::forbidden()->addCacheableDependency($class);
    }
    $allowed = $account->hasPermission('administer lms') || $course->hasPermission('grade students', $account);
    return AccessResult::allowedIf($allowed)
      ->addCacheableDependency($class)
      ->addCacheableDependency($course)
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheContexts(['user.group_permissions']);
  }

  /**
   * Gets counts only for current learners in one class, without answer data.
   *
   * @return array|null
   *   Aggregated class metrics and cache metadata, or NULL when unavailable.
   */
  public function classDashboard(GroupInterface $class, AccountInterface $account): ?array {
    $access = $this->classDashboardAccess($class, $account);
    if (!$access->isAllowed()) {
      return NULL;
    }
    $course = $this->courseForClass($class);
    if (!$course instanceof CourseInterface) {
      return NULL;
    }

    $members = [];
    $memberAccessCache = new CacheableMetadata();
    foreach (GroupMembership::loadByGroup($class) as $membership) {
      if ($membership->getPluginId() !== 'group_membership') {
        continue;
      }
      $member = $membership->getEntity();
      if (!$member instanceof UserInterface) {
        continue;
      }
      $memberAccessCache
        ->addCacheableDependency($membership)
        ->addCacheableDependency($member)
        ->addCacheTags([
          'group_relationship_list:plugin:group_membership:entity:' . $member->id(),
          'config:group_role_list',
          'config:user.role_list',
        ]);
      if ($member->isActive()
        && !$member->hasPermission('administer lms')
        && $course->hasPermission('take course', $member)
        && !$course->hasPermission('grade students', $member)) {
        $members[(int) $member->id()] = $member;
      }
    }

    $result = [
      'course' => $course,
      'students' => count($members),
      'not_started' => 0,
      'in_progress' => 0,
      'pending_evaluation' => 0,
      'passed' => 0,
      'failed' => 0,
      'cache' => (new CacheableMetadata())
        ->addCacheableDependency($access)
        ->addCacheableDependency($class)
        ->addCacheableDependency($course)
        ->merge($memberAccessCache)
        ->addCacheTags([
          'lms_course_status_list',
          'group_relationship_list:plugin:group_membership:group:' . $class->id(),
          'group_relationship_list:plugin:lms_classes:group:' . $course->id(),
        ]),
    ];
    if ($members !== []) {
      if ($this->trainingManager === NULL) {
        throw new \LogicException('Drupal LMS must be enabled to build learning dashboards.');
      }
      $status_storage = $this->entityTypeManager->getStorage('lms_course_status');
      $status_ids = $status_storage->getQuery()
        // This query is constrained to the authorized class's course and
        // current member IDs; LMS status entities have no generic view access.
        ->accessCheck(FALSE)
        ->condition(CourseStatusInterface::COURSE_FIELD, $course->id())
        ->condition('uid', array_keys($members), 'IN')
        ->condition('current', TRUE)
        ->execute();
      $statuses = $status_storage->loadMultiple($status_ids);
    }
    else {
      $statuses = [];
    }
    $statuses_by_student = [];
    foreach ($statuses as $status) {
      $statuses_by_student[(int) $status->getUserId()] = $status;
    }
    foreach ($members as $member_id => $member) {
      $status = $statuses_by_student[$member_id] ?? NULL;
      if ($status === NULL || $status->getStatus() === CourseStatusInterface::STATUS_NEW) {
        $result['not_started']++;
        continue;
      }
      $key = match ($status->getStatus()) {
        CourseStatusInterface::STATUS_PROGRESS => 'in_progress',
        CourseStatusInterface::STATUS_NEEDS_EVALUATION => 'pending_evaluation',
        CourseStatusInterface::STATUS_PASSED => 'passed',
        CourseStatusInterface::STATUS_FAILED => 'failed',
        default => NULL,
      };
      if ($key !== NULL) {
        $result[$key]++;
      }
      $result['cache']->addCacheableDependency($status);
    }
    return $result;
  }

  /**
   * Finds the LMS course that owns a class relationship. */
  private function courseForClass(GroupInterface $class): ?CourseInterface {
    $relationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $class->id(),
      'plugin_id' => 'lms_classes',
    ]);
    foreach ($relationships as $relationship) {
      $course = $relationship->getGroup();
      if ($course instanceof CourseInterface && $course->bundle() === 'lms_course') {
        return $course;
      }
    }
    return NULL;
  }

}
