<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Access\EmailConfirmedAccessCheck;
use Drupal\aculta_portal\Access\ProtectedRouteAccessCheck;
use Drupal\aculta_portal\Account\AccountTermsPolicy;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface as UserModuleDataInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\agreement\AgreementHandlerInterface;
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

    $agreementStorage = $this->createMock(EntityStorageInterface::class);
    $agreementStorage->method('load')->with(AccountTermsPolicy::AGREEMENT)->willReturn(NULL);
    $entityTypeManager->method('getStorage')->with('agreement')->willReturn($agreementStorage);
    $terms = new AccountTermsPolicy($this->createMock(AgreementHandlerInterface::class), $entityTypeManager);

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

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($this->createMock(EntityStorageInterface::class));
    $terms = new AccountTermsPolicy($this->createMock(AgreementHandlerInterface::class), $entityTypeManager);
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
