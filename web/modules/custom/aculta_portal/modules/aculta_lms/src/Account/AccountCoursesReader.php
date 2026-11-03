<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Account;

use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary;
use Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Adapts authorized Group course data and ACULTA progress to account summaries (ADR-042, decisão 6).
 */
final class AccountCoursesReader implements AccountCoursesReaderInterface {

  public function __construct(
    private readonly CourseProgressReaderInterface $progress,
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
      if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
        continue;
      }

      // Do not expose metadata or progress through a stale membership.
      $access = $course->access('view', $account, TRUE);
      if ($access instanceof AccessResultInterface) {
        if (!$access->isAllowed()) {
          continue;
        }
        $accessCacheTags = $access->getCacheTags();
      }
      elseif ($access === TRUE) {
        // Group access hooks can return a boolean despite the object flag.
        // Treat an explicit grant as allowed; the learner dashboard is already
        // varied by user and must not cache this result across requests.
        $accessCacheTags = [];
      }
      else {
        continue;
      }

      $progress = $this->progress->current($course, $account);
      $description = '';
      $descriptionFormat = '';
      if ($course->hasField('field_description') && !$course->get('field_description')->isEmpty()) {
        $description = (string) $course->get('field_description')->value;
        $descriptionFormat = (string) $course->get('field_description')->format;
      }

      $statusCode = $progress?->status ?? '';
      $finished = $progress?->finished ?? FALSE;
      $url = NULL;
      if ($statusCode !== CourseProgress::STATUS_NEEDS_EVALUATION) {
        $url = $this->domainPurposeManager->routeUrl('courses', 'entity.group.canonical', ['group' => $course->id()]);
      }

      $items[] = new AccountCourseSummary(
        id: (string) $course->id(),
        label: (string) $course->label(),
        description: $description,
        descriptionFormat: $descriptionFormat,
        status: $this->normalizeStatus($statusCode),
        score: $progress?->score,
        finished: $finished,
        url: $url,
        cacheTags: array_values(array_unique(array_merge(
          $course->getCacheTags(),
          $accessCacheTags,
          $progress?->cacheTags ?? [],
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
      CourseProgress::STATUS_PROGRESS => AccountCourseSummary::STATUS_IN_PROGRESS,
      CourseProgress::STATUS_PASSED => AccountCourseSummary::STATUS_COMPLETED,
      CourseProgress::STATUS_FAILED => AccountCourseSummary::STATUS_FAILED,
      CourseProgress::STATUS_NEEDS_EVALUATION => AccountCourseSummary::STATUS_AWAITING_EVALUATION,
      default => AccountCourseSummary::STATUS_NOT_STARTED,
    };
  }

}
