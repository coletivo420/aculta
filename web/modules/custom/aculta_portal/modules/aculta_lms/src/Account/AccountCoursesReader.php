<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Account;

use Drupal\aculta_lms\Learning\AccountCourseSummary;
use Drupal\aculta_lms\Learning\AccountCoursesReaderInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\aculta_lms\Group\GroupMembershipReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;

/**
 * Adapts Group course data and ACULTA progress to account summaries.
 */
final class AccountCoursesReader implements AccountCoursesReaderInterface {

  public function __construct(
    private readonly CourseProgressReaderInterface $progress,
    private readonly GroupMembershipReaderInterface $membershipLoader,
    private readonly DomainSiteManager $siteManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAccountCourses(AccountInterface $account): array {
    $items = [];

    foreach ($this->membershipLoader->loadByUser($account) as $membership) {
      $course = $membership->getGroup();
      if ($course->bundle() !== 'lms_course') {
        continue;
      }

      // Do not expose metadata or progress through a stale membership.
      $access = $this->viewAccess($course, $account);
      if ($access === TRUE) {
        // Group access hooks may return a boolean despite the object flag.
        // Treat an explicit grant as allowed; the learner dashboard is already
        // varied by user and must not cache this result across requests.
        $accessCacheTags = [];
      }
      elseif ($access instanceof AccessResultInterface) {
        if (!$access->isAllowed()) {
          continue;
        }
        $accessCacheTags = CacheableMetadata::createFromObject($access)->getCacheTags();
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

      $statusCode = $progress === NULL ? '' : $progress->status;
      $finished = $progress !== NULL && $progress->finished;
      $url = NULL;
      if ($statusCode !== CourseProgress::STATUS_NEEDS_EVALUATION) {
        $url = $this->siteManager->routeUrl(SiteKey::Courses, 'entity.group.canonical', ['group' => $course->id()]);
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
          $progress === NULL ? [] : $progress->cacheTags,
        ))),
      );
    }

    usort($items, static fn(AccountCourseSummary $a, AccountCourseSummary $b): int => strnatcasecmp($a->label, $b->label));
    return $items;
  }

  /**
   * Reads course view access as the group access hooks actually return it.
   *
   * Group access hooks may return a boolean even when an object was requested,
   * so the union is the honest runtime contract at this call site.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|bool
   *   The access result object or an explicit boolean grant.
   */
  private function viewAccess(GroupInterface $course, AccountInterface $account): AccessResultInterface|bool {
    return $course->access('view', $account, TRUE);
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
