<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Account;

use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary;
use Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\lms\Entity\Bundle\Course;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\TrainingManager;

/**
 * Adapts authorized Group and Drupal LMS course data to ACULTA summaries.
 */
final class AccountCoursesReader implements AccountCoursesReaderInterface {

  public function __construct(
    private readonly TrainingManager $trainingManager,
    private readonly GroupMembershipLoaderInterface $membershipLoader,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAccountCourses(AccountInterface $account): array {
    $items = [];

    foreach ($this->membershipLoader->loadByUser($account) as $membership) {
      $course = $membership->getGroup();
      if (!$course instanceof Course || $course->bundle() !== 'lms_course') {
        continue;
      }

      // Do not expose metadata or progress through a stale membership.
      $access = $course->access('view', $account, TRUE);
      if (!$access->isAllowed()) {
        continue;
      }

      $status = $this->trainingManager->loadCourseStatus($course, $account, ['current' => TRUE]);
      $description = '';
      $descriptionFormat = '';
      if ($course->hasField('field_description') && !$course->get('field_description')->isEmpty()) {
        $description = (string) $course->get('field_description')->value;
        $descriptionFormat = (string) $course->get('field_description')->format;
      }

      $statusCode = $status?->getStatus() ?? '';
      $finished = $status?->isFinished() ?? FALSE;
      $url = NULL;
      if ($statusCode !== CourseStatusInterface::STATUS_NEEDS_EVALUATION) {
        $route = $finished ? 'lms.group.self_results' : 'lms.course.start';
        $url = $this->domainPurposeManager->routeUrl('courses', $route, ['group' => $course->id()]);
      }

      $score = $status?->getScore();
      $items[] = new AccountCourseSummary(
        id: (string) $course->id(),
        label: (string) $course->label(),
        description: $description,
        descriptionFormat: $descriptionFormat,
        status: $this->normalizeStatus($statusCode),
        score: is_numeric($score) ? (float) $score : NULL,
        finished: $finished,
        url: $url,
        cacheTags: array_values(array_unique(array_merge(
          $course->getCacheTags(),
          $access->getCacheTags(),
          $status?->getCacheTags() ?? [],
        ))),
      );
    }

    usort($items, static fn(AccountCourseSummary $a, AccountCourseSummary $b): int => strnatcasecmp($a->label, $b->label));
    return $items;
  }

  /**
   * Maps provider status to the stable ACULTA contract vocabulary.
   */
  private function normalizeStatus(string $status): string {
    return match ($status) {
      CourseStatusInterface::STATUS_PROGRESS => AccountCourseSummary::STATUS_IN_PROGRESS,
      CourseStatusInterface::STATUS_PASSED => AccountCourseSummary::STATUS_COMPLETED,
      CourseStatusInterface::STATUS_FAILED => AccountCourseSummary::STATUS_FAILED,
      CourseStatusInterface::STATUS_NEEDS_EVALUATION => AccountCourseSummary::STATUS_AWAITING_EVALUATION,
      default => AccountCourseSummary::STATUS_NOT_STARTED,
    };
  }

}
