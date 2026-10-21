<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Access\CertificateIssueAccessCheck;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests certificate access cacheability.
 *
 * @coversDefaultClass \Drupal\aculta_lms\Access\CertificateIssueAccessCheck
 */
final class CertificateIssueAccessCheckTest extends UnitTestCase {

  /**
   * @covers ::access
   */
  public function testGroupPermissionAccessDeclaresGroupPermissionContext(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $course = $this->createMock(CourseInterface::class);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $course->method('hasPermission')->with('grade students', $account)->willReturn(TRUE);

    $result = (new CertificateIssueAccessCheck($account))->access($course);

    self::assertTrue($result->isAllowed());
    self::assertContains('user.group_permissions', $result->getCacheContexts());
  }

}
