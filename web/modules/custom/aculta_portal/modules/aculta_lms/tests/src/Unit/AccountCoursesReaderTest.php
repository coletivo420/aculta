<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Learning\AccountCourseSummary;
use Drupal\aculta_lms\Account\AccountCoursesReader;
use Drupal\aculta_lms\Group\GroupMembershipReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests adapting access results for a learner's courses.
 *
 * O progresso vem do contrato ACULTA (CourseProgressReaderInterface), não do LMS contrib (ADR-042, decisão 6).
 */
#[Group('aculta_lms')]
final class AccountCoursesReaderTest extends UnitTestCase {

  /**
   * Uses Group's canonical destination for a completed course.
   */
  public function testCompletedCourseUsesGroupCanonicalRoute(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('7');
    $course = $this->createMock(GroupInterface::class);
    $course->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasField')->with('field_description')->willReturn(FALSE);
    $course->method('id')->willReturn('42');
    $course->method('label')->willReturn('Course 42');
    $course->method('getCacheTags')->willReturn(['group:42']);
    $membership = $this->createMock(GroupRelationshipInterface::class);
    $membership->method('getGroup')->willReturn($course);
    $membership_loader = $this->createMock(GroupMembershipReaderInterface::class);
    $membership_loader->method('loadByUser')->willReturn([$membership]);
    $progress = $this->createMock(CourseProgressReaderInterface::class);
    $progress->method('current')->willReturn(new CourseProgress(CourseProgress::STATUS_PASSED, TRUE, 100));

    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $domain_storage->method('load')->with('cursos_aculta_org')->willReturn($domain);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('domain')->willReturn($domain_storage);
    $domain_negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domain_negotiator->method('getActiveDomain')->willReturn(NULL);
    $domain_sites = new DomainSiteManager(
      $entity_type_manager,
      $domain_negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    $summaries = (new AccountCoursesReader($progress, $membership_loader, $domain_sites))
      ->getAccountCourses($account);

    self::assertCount(1, $summaries);
    self::assertNotNull($summaries[0]->url);
    self::assertSame('entity.group.canonical', $summaries[0]->url->getRouteName());
    self::assertSame(['group' => '42'], $summaries[0]->url->getRouteParameters());
  }

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
    $membership_loader = $this->createMock(GroupMembershipReaderInterface::class);
    $membership_loader->method('loadByUser')->with($account)->willReturn([$membership]);

    $progress = $this->createMock(CourseProgressReaderInterface::class);
    $progress->method('current')->with($course, $account)->willReturn(
      new CourseProgress(CourseProgress::STATUS_NEEDS_EVALUATION, FALSE, NULL, []),
    );
    $domain_sites = (new \ReflectionClass(DomainSiteManager::class))->newInstanceWithoutConstructor();

    $summaries = (new AccountCoursesReader($progress, $membership_loader, $domain_sites))
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
    $membership_loader = $this->createMock(GroupMembershipReaderInterface::class);
    $membership_loader->method('loadByUser')->with($account)->willReturn([$membership]);
    $progress = $this->createMock(CourseProgressReaderInterface::class);
    $progress->expects(self::never())->method('current');
    $domain_sites = (new \ReflectionClass(DomainSiteManager::class))->newInstanceWithoutConstructor();

    self::assertSame([], (new AccountCoursesReader($progress, $membership_loader, $domain_sites))
      ->getAccountCourses($account));
  }

}
