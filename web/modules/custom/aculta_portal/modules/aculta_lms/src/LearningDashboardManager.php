<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\aculta_lms\Learning\AccountCourseSummary;
use Drupal\aculta_lms\Learning\AccountCoursesReaderInterface;
use Drupal\aculta_lms\Learning\CourseJourney;
use Drupal\aculta_lms\Learning\CourseJourneyLesson;
use Drupal\aculta_lms\Learning\CourseJourneyReaderInterface;
use Drupal\aculta_lms\Learning\StudentLearningCertificate;
use Drupal\aculta_lms\Learning\StudentLearningCourse;
use Drupal\aculta_lms\Learning\StudentLearningSummary;
use Drupal\aculta_lms\Learning\StudentLearningSummaryReaderInterface;
use Drupal\aculta_lms\Learning\CourseLessonReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\aculta_lms\Learning\CourseLesson;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\user\UserInterface;

/**
 * Read-only learning dashboard data assembled from Group and ACULTA entities.
 *
 * Implements the ACULTA Studio journey contract so presentation consumers can
 * stop importing provider-specific entities. The manager translates Group
 * membership and ACULTA learning state into the neutral view model.
 */
final class LearningDashboardManager implements CourseJourneyReaderInterface, StudentLearningSummaryReaderInterface {

  public function __construct(
    private readonly ?AccountCoursesReaderInterface $accountCourses,
    private readonly CourseCertificateManagerInterface $certificates,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CourseProgressReaderInterface $progressReader,
    private readonly CourseLessonReaderInterface $lessonReader,
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
      ->addCacheTags(['aculta_lms_progress_list', 'aculta_lms_lesson_list']);
    $courses = [];
    // The provider is optional in isolated LMS Kernel tests. In the full site,
    // it supplies authorized course summaries and canonical purpose-aware URLs.
    foreach ($this->accountCourses?->getAccountCourses($account) ?? [] as $course_data) {
      if (!$course_data instanceof AccountCourseSummary) {
        continue;
      }
      $course = $this->entityTypeManager->getStorage('group')->load($course_data->id);
      if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
        continue;
      }
      $courseAccess = $this->courseTakeAccess($course, $account);
      $metadata->addCacheableDependency($courseAccess);
      if (!$courseAccess->isAllowed()) {
        continue;
      }
      $available_lessons = array_values(array_filter(
        $this->lessonReader->forCourse((int) $course->id()),
        static fn($lesson): bool => $lesson->published,
      ));
      foreach ($available_lessons as $lesson) {
        $metadata->addCacheTags(['aculta_lms_lesson:' . $lesson->id]);
      }
      $progress = $this->progressReader->current($course, $account);
      $metadata->addCacheTags($progress === NULL ? [] : $progress->cacheTags);
      $total = count($available_lessons);
      // P2 stores course-level completion only. It has no lesson-status rows
      // or current-lesson pointer, so a detailed lesson count cannot be read.
      $completed = $progress?->finished ? $total : 0;
      $current_lesson = NULL;
      $last_activity = $progress === NULL ? 0 : $progress->changedTime;
      $courses[] = [
        'id' => $course_data->id,
        'label' => $course_data->label,
        'description' => $course_data->description !== '' && $course_data->descriptionFormat !== ''
          ? [
            '#type' => 'processed_text',
            '#text' => $course_data->description,
            '#format' => $course_data->descriptionFormat,
          ]
          : [],
        'status' => $course_data->status,
        'score' => $course_data->score,
        'finished' => $course_data->finished,
        'url' => $course_data->url,
        'cache_tags' => $course_data->cacheTags,
        'completed_lessons' => $completed,
        'total_lessons' => $total,
        'progress_percent' => $total > 0 ? min(100, (int) round($completed / $total * 100)) : 0,
        'pending_evaluation' => $course_data->status === AccountCourseSummary::STATUS_AWAITING_EVALUATION,
        'started' => $progress !== NULL,
        'last_activity' => $last_activity,
        'current_lesson' => $current_lesson,
      ];
      $metadata->addCacheTags($course_data->cacheTags);
    }

    // Retomada honesta: only a started, unfinished course with a canonical
    // destination and a recorded activity timestamp can lead the home. A NULL
    // LMS URL (for example, while an evaluation is pending) is never replaced
    // with a guessed course/lesson.
    $continue = NULL;
    foreach ($courses as $course) {
      if (empty($course['started']) || $course['finished'] || !$course['url'] instanceof Url) {
        continue;
      }
      if ((int) $course['last_activity'] <= 0) {
        continue;
      }
      if ($continue === NULL || (int) $course['last_activity'] > (int) $continue['last_activity']) {
        $continue = $course;
      }
    }

    $certificates = $this->certificates->loadForStudent((int) $account->id());
    foreach ($certificates as $certificate) {
      $metadata->addCacheableDependency($certificate);
    }
    return [
      'courses' => $courses,
      'continue' => $continue,
      'certificates' => $certificates,
      'cache' => $metadata,
    ];
  }

  /**
   * Returns learner dashboard data as provider-neutral ACULTA DTOs.
   */
  public function getStudentLearningSummary(AccountInterface $account): StudentLearningSummary {
    $data = $this->studentDashboard($account);
    $courses = [];
    foreach ($data['courses'] as $course) {
      $courses[] = $this->studentLearningCourse($course);
    }

    $continue = is_array($data['continue'] ?? NULL)
      ? $this->studentLearningCourse($data['continue'])
      : NULL;
    $certificates = [];
    foreach ($data['certificates'] as $certificate) {
      if (!$certificate instanceof CourseCertificate) {
        continue;
      }
      $certificates[] = new StudentLearningCertificate(
        courseTitle: (string) $certificate->get('course_title')->value,
        state: (string) $certificate->get('state')->value,
        issuedAt: (int) $certificate->get('issued')->value,
        verificationUrl: Url::fromRoute('aculta_lms.certificate_verify', [
          'codigo' => (string) $certificate->get('verification_code')->value,
        ]),
      );
    }

    return new StudentLearningSummary(
      courses: $courses,
      continueCourse: $continue,
      certificates: $certificates,
      cache: $data['cache'],
    );
  }

  /**
   * Converts one already-authorized LMS course record to the neutral contract.
   *
   * @param array<string, mixed> $course
   *   Course projection returned by studentDashboard().
   */
  private function studentLearningCourse(array $course): StudentLearningCourse {
    $current = $course['current_lesson'] ?? NULL;
    $url = $course['url'] ?? NULL;

    return new StudentLearningCourse(
      id: (string) $course['id'],
      label: (string) $course['label'],
      continueUrl: $url instanceof Url ? clone $url : NULL,
      completedLessons: (int) ($course['completed_lessons'] ?? 0),
      totalLessons: (int) ($course['total_lessons'] ?? 0),
      progressPercent: (int) ($course['progress_percent'] ?? 0),
      pendingEvaluation: (bool) ($course['pending_evaluation'] ?? FALSE),
      started: (bool) ($course['started'] ?? FALSE),
      finished: (bool) ($course['finished'] ?? FALSE),
      currentLessonLabel: is_array($current) ? (string) ($current['label'] ?? '') : NULL,
      currentLessonFinished: is_array($current) && !empty($current['finished']),
    );
  }

  /**
   * Loads one course journey by identifier through the ACULTA contract.
   *
   * The identifier is resolved to the provider's course entity inside this
   * adapter. Consumers of the contract never receive Group or LMS entities.
   */
  public function getCourseJourney(string $course_id, AccountInterface $account): ?CourseJourney {
    $course = $this->entityTypeManager->getStorage('group')->load($course_id);
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      return NULL;
    }
    return $this->courseJourney($course, $account);
  }

  /**
   * Builds the course journey for one learner from Group and ACULTA state.
   */
  public function courseJourney(GroupInterface $course, AccountInterface $account): CourseJourney {
    $metadata = (new CacheableMetadata())
      ->addCacheContexts(['user', 'user.group_permissions'])
      ->addCacheableDependency($course);
    $course_access = $this->courseTakeAccess($course, $account);
    $metadata->addCacheableDependency($course_access);
    if (!$course_access->isAllowed()) {
      return new CourseJourney(
        access: FALSE,
        courseId: (string) $course->id(),
        courseLabel: (string) $course->label(),
        description: [],
        started: FALSE,
        finished: FALSE,
        pendingEvaluation: FALSE,
        currentLessonId: NULL,
        currentLessonLabel: NULL,
        currentLessonIndex: NULL,
        totalLessons: 0,
        completedLessons: 0,
        progressPercent: 0,
        lessons: [],
        continueUrl: NULL,
        cache: $metadata,
      );
    }

    $description = [];
    if ($course->hasField('field_description') && !$course->get('field_description')->isEmpty()) {
      $description = $course->get('field_description')->view([
        'label' => 'hidden',
        'type' => 'text_default',
      ]);
      $metadata->addCacheableDependency(CacheableMetadata::createFromRenderArray($description));
    }

    $progress = $this->progressReader->current($course, $account);
    $metadata->addCacheTags($progress === NULL ? [] : $progress->cacheTags);
    $lessons = [];
    $total = 0;
    $lesson_delta = 0;
    foreach ($this->lessonReader->forCourse((int) $course->id()) as $lesson) {
      $source_delta = $lesson_delta++;
      if (!$lesson->published) {
        continue;
      }
      $metadata->addCacheTags(['aculta_lms_lesson:' . $lesson->id]);
      $total++;
      $lessons[] = new CourseJourneyLesson(
        id: (string) $lesson->id,
        label: $lesson->title,
        delta: $source_delta,
        state: $progress?->finished ? 'finished' : 'not_started',
        isCurrent: FALSE,
        allowed: TRUE,
        url: Url::fromRoute('aculta_lms.lesson_view', [
          'group' => $course->id(),
          'lesson_delta' => $source_delta,
        ]),
      );
    }
    $completed = $progress?->finished ? $total : 0;
    $pending_evaluation = $progress?->status === CourseProgress::STATUS_NEEDS_EVALUATION;
    $continue_url = NULL;
    if (!$pending_evaluation && $total > 0) {
      $continue_url = Url::fromRoute('entity.group.canonical', ['group' => $course->id()]);
    }

    return new CourseJourney(
      access: TRUE,
      courseId: (string) $course->id(),
      courseLabel: (string) $course->label(),
      description: $description,
      started: $progress !== NULL,
      finished: $progress !== NULL && $progress->finished,
      pendingEvaluation: $pending_evaluation,
      currentLessonId: NULL,
      currentLessonLabel: NULL,
      currentLessonIndex: NULL,
      totalLessons: $total,
      completedLessons: $completed,
      progressPercent: $total > 0 ? min(100, (int) round($completed / $total * 100)) : 0,
      lessons: $lessons,
      continueUrl: $continue_url,
      cache: $metadata,
    );
  }

  /**
   * Checks course-scoped grading authority for an LMS class dashboard.
   */
  public function classDashboardAccess(GroupInterface $class, AccountInterface $account): AccessResult {
    $course = $this->courseForClass($class);
    if ($class->bundle() !== 'lms_class' || !$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      return AccessResult::forbidden()
        ->addCacheableDependency($class)
        // The class may gain its parent-course relationship after this
        // negative result has been cached.
        ->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $class->id()]);
    }
    $allowed = $account->hasPermission('administer lms') || $course->hasPermission('grade students', $account);
    return AccessResult::allowedIf($allowed)
      ->addCacheableDependency($class)
      ->addCacheableDependency($course)
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheContexts(['user.group_permissions'])
      // Removing or changing the class-to-course relation must revoke cached
      // dashboard access immediately.
      ->addCacheTags([
        'group_relationship_list:plugin:lms_classes:entity:' . $class->id(),
        'group_relationship_list:plugin:lms_classes:group:' . $course->id(),
      ]);
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
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      return NULL;
    }

    $counts = $this->countClassStatuses($class, $course);
    $counts['course'] = $course;
    $counts['cache'] = (new CacheableMetadata())
      ->addCacheableDependency($access)
      ->addCacheableDependency($class)
      ->addCacheableDependency($course)
      ->merge($counts['cache'])
      ->addCacheTags([
        'aculta_lms_progress_list',
        'group_relationship_list:plugin:group_membership:group:' . $class->id(),
        'group_relationship_list:plugin:lms_classes:group:' . $course->id(),
      ]);
    return $counts;
  }

  /**
   * Counts active learners of a class by situation, without checking access.
   *
   * The dashboard calls this after its access check. The daily class snapshot
   * on cron calls it directly.
   *
   * @return array{students: int, not_started: int, in_progress: int, pending_evaluation: int, passed: int, failed: int, cache: \Drupal\Core\Cache\CacheableMetadata, by_student?: array<int, string>}
   *   Counts by situation and cache metadata for membership and status data.
   *   `by_student` (uid to situation) is only included when `$perStudent` is
   *   TRUE.
   */
  public function countClassStatuses(GroupInterface $class, GroupInterface $course, bool $perStudent = FALSE): array {
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
      'students' => count($members),
      'not_started' => 0,
      'in_progress' => 0,
      'pending_evaluation' => 0,
      'passed' => 0,
      'failed' => 0,
      'cache' => (new CacheableMetadata())->merge($memberAccessCache),
    ];
    if ($members !== []) {
      $statuses = $this->entityTypeManager
        ->getStorage('aculta_lms_progress')
        ->loadByProperties(['course_id' => (int) $course->id()]);
    }
    else {
      $statuses = [];
    }
    $statuses_by_student = [];
    foreach ($statuses as $status) {
      if (!$status instanceof ContentEntityInterface) {
        continue;
      }
      $uid = (int) $status->get('uid')->value;
      if (isset($members[$uid])) {
        $statuses_by_student[$uid] = $status;
        $result['cache']->addCacheableDependency($status);
      }
    }
    $bySituation = [];
    foreach ($members as $member_id => $member) {
      $status = $statuses_by_student[$member_id] ?? NULL;
      if ($status === NULL) {
        $result['not_started']++;
        $bySituation[(int) $member_id] = 'not_started';
        continue;
      }
      $key = match ((string) $status->get('status')->value) {
        CourseProgress::STATUS_PROGRESS => 'in_progress',
        CourseProgress::STATUS_NEEDS_EVALUATION => 'pending_evaluation',
        CourseProgress::STATUS_PASSED => 'passed',
        CourseProgress::STATUS_FAILED => 'failed',
        default => NULL,
      };
      if ($key !== NULL) {
        $result[$key]++;
        $bySituation[(int) $member_id] = $key;
      }
    }
    // Include per-student status only when requested. The dashboard does not
    // request it.
    if ($perStudent) {
      $result['by_student'] = $bySituation;
    }
    return $result;
  }

  /**
   * Finds the LMS course that owns a class relationship.
   */
  public function courseForClass(GroupInterface $class): ?GroupInterface {
    $relationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $class->id(),
      'plugin_id' => 'lms_classes',
    ]);
    foreach ($relationships as $relationship) {
      if (!$relationship instanceof GroupRelationshipInterface) {
        continue;
      }
      $course = $relationship->getGroup();
      if ($course->bundle() === 'lms_course') {
        return $course;
      }
    }
    return NULL;
  }

  /**
   * Reproduces the course take-access policy using Group permissions alone.
   */
  private function courseTakeAccess(GroupInterface $course, AccountInterface $account): AccessResult {
    $allowed = $course->hasPermission('take course', $account);
    $contexts = ['user.group_permissions'];
    if (!$course->isPublished() && $allowed) {
      $allowed = $course->hasPermission('view any unpublished group', $account);
      if (!$allowed && $course->hasPermission('view own unpublished group', $account)) {
        $contexts[] = 'user';
        $allowed = (string) $course->getOwnerId() === (string) $account->id();
      }
    }
    return AccessResult::allowedIf($allowed)
      ->addCacheableDependency($course)
      ->addCacheContexts($contexts);
  }

}
