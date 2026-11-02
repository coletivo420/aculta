<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary;
use Drupal\aculta_lms\Account\AccountCoursesReader;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests adapting access results for a learner's courses.
 *
 * O progresso vem do contrato ACULTA (CourseProgressReaderInterface), não do LMS contrib (ADR-042, decisão 6).
 */
#[Group('aculta_lms')]
final class AccountCoursesReaderTest extends UnitTestCase {

  /**
   * Keeps an explicitly granted course when Group returns a boolean result.
   */
  public function testBooleanAccessGrantDoesNotBreakDashboard(): void {
    $account = $this->createMock(AccountInterface::class);
    $course = $this->createMock(GroupInterface::class);
    $course->expects(self::once())->method('access')->with('view', $account, TRUE)->willReturn(TRUE);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasField')->with('field_description')->willReturn(FALSE);
    $course->method('id')->willReturn('42');
    $course->method('label')->willReturn('Course 42');
    $course->method('getCacheTags')->willReturn(['group:42']);

    $membership = $this->createMock(GroupRelationshipInterface::class);
    $membership->method('getGroup')->willReturn($course);
    $membership_loader = $this->createMock(GroupMembershipLoaderInterface::class);
    $membership_loader->method('loadByUser')->with($account)->willReturn([$membership]);

    $progress = $this->createMock(CourseProgressReaderInterface::class);
    $progress->method('current')->with($course, $account)->willReturn(
      new CourseProgress(CourseProgress::STATUS_NEEDS_EVALUATION, FALSE, NULL, []),
    );
    $domain_purposes = (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor();

    $summaries = (new AccountCoursesReader($progress, $membership_loader, $domain_purposes))
      ->getAccountCourses($account);

    self::assertCount(1, $summaries);
    self::assertInstanceOf(AccountCourseSummary::class, $summaries[0]);
    self::assertSame('42', $summaries[0]->id);
    self::assertSame(['group:42'], $summaries[0]->cacheTags);
  }

  /**
   * Ignores a course when a non-object access result is not an explicit grant.
   */
  public function testBooleanAccessDenialFailsClosed(): void {
    $account = $this->createMock(AccountInterface::class);
    $course = $this->createMock(GroupInterface::class);
    $course->method('access')->with('view', $account, TRUE)->willReturn(FALSE);
    $course->method('bundle')->willReturn('lms_course');
    $membership = $this->createMock(GroupRelationshipInterface::class);
    $membership->method('getGroup')->willReturn($course);
    $membership_loader = $this->createMock(GroupMembershipLoaderInterface::class);
    $membership_loader->method('loadByUser')->with($account)->willReturn([$membership]);
    $progress = $this->createMock(CourseProgressReaderInterface::class);
    $progress->expects(self::never())->method('current');
    $domain_purposes = (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor();

    self::assertSame([], (new AccountCoursesReader($progress, $membership_loader, $domain_purposes))
      ->getAccountCourses($account));
  }

}
