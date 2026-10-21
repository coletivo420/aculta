<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Access\EmailConfirmedAccessCheck;
use Drupal\aculta_portal\Access\ProtectedRouteAccessCheck;
use Drupal\aculta_portal\Account\AcultaUserAgreementPolicy;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface as UserModuleDataInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\aculta_user\Consent\AcultaUserAgreementLedger;
use PHPUnit\Framework\Attributes\Group;

/** Verifies LMS participation uses Group access without email verification. */
#[Group('aculta_portal')]
final class ProtectedRouteAccessCheckTest extends UnitTestCase {

  /** A current course member can start without confirming their email. */
  public function testCourseAccessDoesNotRequireEmailConfirmation(): void {
    $student = $this->createMock(AccountInterface::class);
    $student->method('id')->willReturn('123');
    $student->method('isAnonymous')->willReturn(FALSE);
    $student->method('hasPermission')->willReturn(FALSE);

    $userData = $this->createMock(UserModuleDataInterface::class);
    $userData->expects(self::never())->method('get');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $policy = new EmailConfirmationPolicy($userData, $entityTypeManager);
    $emailCheck = new EmailConfirmedAccessCheck($policy);

    $terms = new AcultaUserAgreementPolicy($this->createMock(AcultaUserAgreementLedger::class));

    $course = $this->createMock(GroupInterface::class);
    $course->expects(self::once())->method('access')->with('take', $student, TRUE)->willReturn(AccessResult::allowed()->cachePerUser());
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')->with('group')->willReturn($course);

    $checker = new ProtectedRouteAccessCheck(
      $emailCheck,
      $this->createMock(ClassResolverInterface::class),
      $terms,
    );

    self::assertTrue($checker->course($routeMatch, $student)->isAllowed());
  }

  /** An account without the Group take grant still cannot enter the course. */
  public function testCourseAccessStillRequiresGroupTakeAccess(): void {
    $student = $this->createMock(AccountInterface::class);
    $student->method('isAnonymous')->willReturn(FALSE);
    $student->method('hasPermission')->willReturn(FALSE);

    $entityTypeManager = $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class);
    $terms = new AcultaUserAgreementPolicy($this->createMock(AcultaUserAgreementLedger::class));
    $emailCheck = new EmailConfirmedAccessCheck(new EmailConfirmationPolicy(
      $this->createMock(UserModuleDataInterface::class),
      $entityTypeManager,
    ));
    $course = $this->createMock(GroupInterface::class);
    $course->method('access')->with('take', $student, TRUE)->willReturn(AccessResult::forbidden()->cachePerUser());
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')->with('group')->willReturn($course);

    $checker = new ProtectedRouteAccessCheck(
      $emailCheck,
      $this->createMock(ClassResolverInterface::class),
      $terms,
    );

    self::assertFalse($checker->course($routeMatch, $student)->isAllowed());
  }

}
