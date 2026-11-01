<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary;
use Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface;
use Drupal\aculta_learning_contracts\Learning\CourseJourney;
use Drupal\aculta_learning_contracts\Learning\CourseJourneyLesson;
use Drupal\aculta_learning_contracts\Learning\CourseJourneyReaderInterface;
use Drupal\aculta_learning_contracts\Learning\StudentLearningCertificate;
use Drupal\aculta_learning_contracts\Learning\StudentLearningCourse;
use Drupal\aculta_learning_contracts\Learning\StudentLearningSummary;
use Drupal\aculta_learning_contracts\Learning\StudentLearningSummaryReaderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
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
 *
 * Implements the ACULTA Studio journey contract so presentation consumers can
 * stop importing Drupal LMS classes. The adapter remains the only place that
 * translates Group and LMS state into the ACULTA view model.
 */
final class LearningDashboardManager implements CourseJourneyReaderInterface, StudentLearningSummaryReaderInterface {

  public function __construct(
    private readonly ?AccountCoursesReaderInterface $accountCourses,
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
    // The provider is optional in isolated LMS Kernel tests. In the full site,
    // it supplies authorized course summaries and canonical purpose-aware URLs.
    foreach ($this->accountCourses?->getAccountCourses($account) ?? [] as $course_data) {
      if (!$course_data instanceof AccountCourseSummary) {
        continue;
      }
      $course = $this->entityTypeManager->getStorage('group')->load($course_data->id);
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
      $current_lesson = NULL;
      $last_activity = 0;
      if ($status !== NULL) {
        $metadata->addCacheableDependency($status);
        $last_activity = $status->getLastActivity();
        $current_lesson_status = $status->getCurrentLessonStatus();
        if ($current_lesson_status instanceof LessonStatusInterface
          && in_array((string) $current_lesson_status->getLessonId(), $availableLessonIds, TRUE)) {
          $metadata->addCacheableDependency($current_lesson_status);
          // The current lesson pointer is owned by LMS. Expose it only as the
          // canonical position already persisted for this account; never infer
          // a lesson from the curriculum order.
          $current_lesson = [
            'label' => (string) $current_lesson_status->getLesson()->label(),
            'delta' => $current_lesson_status->getCurrentLessonDelta(),
            'activity_delta' => $current_lesson_status->getCurrentActivityDelta() ?? 0,
            'finished' => $current_lesson_status->isFinished(),
          ];
        }
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
        'started' => $status !== NULL,
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
      if ((int) ($course['last_activity'] ?? 0) <= 0) {
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
    if (!$course instanceof CourseInterface) {
      return NULL;
    }
    return $this->courseJourney($course, $account);
  }

  /**
   * Builds the canonical course journey for one learner.
   *
   * Reads LMS/Group state only; it never initializes training, creates status
   * rows or infers completion. Lesson links are offered only when the LMS
   * navigation policy allows the destination.
   */
  public function courseJourney(CourseInterface $course, AccountInterface $account): CourseJourney {
    $metadata = (new CacheableMetadata())
      ->addCacheContexts(['user', 'user.group_permissions'])
      ->addCacheableDependency($course);
    $take_access = $course->takeAccess($account);
    $metadata->addCacheableDependency($take_access);
    if (!$take_access->isAllowed()) {
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

    $status = $this->trainingManager?->loadCourseStatus($course, $account, ['current' => TRUE]);
    if ($status !== NULL) {
      $metadata->addCacheableDependency($status);
    }
    $current_lesson_status = $status?->getCurrentLessonStatus();
    if ($current_lesson_status instanceof LessonStatusInterface) {
      $metadata->addCacheableDependency($current_lesson_status);
    }
    $current_lesson_id = $current_lesson_status?->getLessonId();
    $current_lesson_delta = $current_lesson_status?->getCurrentLessonDelta() ?? 0;
    $current_activity_delta = $current_lesson_status?->getCurrentActivityDelta() ?? 0;
    // A finished current lesson is not the place to resume; the LMS action and
    // the ordered outline carry the real next step, so do not label it current.
    $current_lesson_finished = $current_lesson_status?->isFinished() ?? FALSE;

    $lessons = [];
    $lesson_statuses = [];
    $total = 0;
    $completed = 0;
    $current_index = NULL;
    foreach ($course->get(CourseInterface::LESSONS) as $delta => $reference) {
      $lesson = $reference->entity ?? NULL;
      if (!$lesson instanceof LessonInterface || !$lesson instanceof EntityPublishedInterface || !$lesson->isPublished()) {
        continue;
      }
      $metadata->addCacheableDependency($lesson);
      $total++;
      $lesson_id = (string) $lesson->id();
      if ($status !== NULL && !isset($lesson_statuses[$lesson_id])) {
        $lesson_status = $this->trainingManager->loadLessonStatus($status->id(), $lesson_id);
        if ($lesson_status instanceof LessonStatusInterface) {
          $lesson_statuses[$lesson_id] = $lesson_status;
          $metadata->addCacheableDependency($lesson_status);
        }
      }
      $lesson_status = $lesson_statuses[$lesson_id] ?? NULL;
      $is_current = $current_lesson_id !== NULL && (string) $current_lesson_id === $lesson_id;
      if ($lesson_status?->isFinished()) {
        $state = 'finished';
        $completed++;
      }
      elseif ($is_current) {
        $state = 'current';
      }
      elseif ($lesson_status !== NULL) {
        $state = 'in_progress';
      }
      else {
        $state = 'not_started';
      }
      if ($is_current) {
        $current_index = $total;
      }

      // The current LMS position is always reachable by definition. Other
      // destinations follow the LMS navigation policy; a course without a
      // status starts at its first published lesson.
      $activity_delta = $is_current ? $current_activity_delta : 0;
      if ($is_current) {
        $allowed = TRUE;
      }
      elseif ($status === NULL) {
        $allowed = (int) $delta === 0;
      }
      else {
        $allowed = $this->trainingManager->checkActivityAccess($status, [
          'lesson' => (int) $delta,
          'activity' => $activity_delta,
          'current_lesson' => $current_lesson_delta,
          'current_activity' => $current_activity_delta,
        ]) === NULL;
      }
      $url = $allowed
        ? Url::fromRoute('lms.group.answer_form', [
          'group' => $course->id(),
          'lesson_delta' => (int) $delta,
          'activity_delta' => $activity_delta,
        ])
        : NULL;
      $lessons[] = new CourseJourneyLesson(
        id: $lesson_id,
        label: (string) $lesson->label(),
        delta: (int) $delta,
        state: $state,
        isCurrent: $is_current,
        allowed: $allowed,
        url: $url,
      );
    }

    $pending_evaluation = $status?->getStatus() === CourseStatusInterface::STATUS_NEEDS_EVALUATION;
    $continue_url = NULL;
    if (!$pending_evaluation && $total > 0) {
      $continue_url = $status?->isFinished()
        ? Url::fromRoute('lms.group.self_results', ['group' => $course->id()])
        : Url::fromRoute('lms.course.start', ['group' => $course->id()]);
    }

    return new CourseJourney(
      access: TRUE,
      courseId: (string) $course->id(),
      courseLabel: (string) $course->label(),
      description: $description,
      started: $status !== NULL,
      finished: $status?->isFinished() ?? FALSE,
      pendingEvaluation: $pending_evaluation,
      currentLessonId: $current_lesson_finished ? NULL : $current_lesson_id,
      currentLessonLabel: !$current_lesson_finished && $current_index !== NULL && $current_lesson_status !== NULL
        ? (string) $current_lesson_status->getLesson()->label()
        : NULL,
      currentLessonIndex: $current_lesson_finished ? NULL : $current_index,
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
    if ($class->bundle() !== 'lms_class' || !$course instanceof CourseInterface) {
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
    if (!$course instanceof CourseInterface) {
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
        'lms_course_status_list',
        'group_relationship_list:plugin:group_membership:group:' . $class->id(),
        'group_relationship_list:plugin:lms_classes:group:' . $course->id(),
      ]);
    return $counts;
  }

  /**
   * Counts active learners of a class by situation, without checking the viewer's access.
   *
   * The dashboard calls it after its access check. The daily class snapshot on cron calls it directly.
   *
   * @return array{students: int, not_started: int, in_progress: int, pending_evaluation: int, passed: int, failed: int, cache: \Drupal\Core\Cache\CacheableMetadata, by_student?: array<int, string>}
   *   Counts by situation and the cache metadata of the membership and status data read. `by_student` (uid to
   *   situation) só com `$perStudent`.
   */
  public function countClassStatuses(GroupInterface $class, CourseInterface $course, bool $perStudent = FALSE): array {
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
    $bySituation = [];
    foreach ($members as $member_id => $member) {
      $status = $statuses_by_student[$member_id] ?? NULL;
      if ($status === NULL || $status->getStatus() === CourseStatusInterface::STATUS_NEW) {
        $result['not_started']++;
        $bySituation[(int) $member_id] = 'not_started';
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
        $bySituation[(int) $member_id] = $key;
      }
      $result['cache']->addCacheableDependency($status);
    }
    // Situação por aluno: só quando pedida (série por aluno, com consentimento pelo aceite único). O painel não pede.
    if ($perStudent) {
      $result['by_student'] = $bySituation;
    }
    return $result;
  }

  /**
   * Finds the LMS course that owns a class relationship. */
  public function courseForClass(GroupInterface $class): ?CourseInterface {
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
