<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Controller\LearningDashboardController;
use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests learner dashboard cacheability while preserving its private policy.
 */
#[CoversClass(LearningDashboardController::class)]
#[Group('aculta_lms')]
final class LearningDashboardControllerTest extends UnitTestCase {

  /**
   * Entity metadata does not replace the dashboard's no-cache max age.
   */
  public function testStudentDashboardMergesCacheMetadata(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn('7');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $certificates = $this->createMock(CourseCertificateManagerInterface::class);
    $certificates->method('loadForStudent')->with(7)->willReturn([]);
    $dashboards = new LearningDashboardManager(NULL, $certificates, $entityTypeManager, NULL);
    $deadlineAccess = new ClassActivityDeadlineAccessCheck($entityTypeManager, $account);
    $deadlines = new ClassActivityDeadlineManager($entityTypeManager, $deadlineAccess);

    $controller = new LearningDashboardController(
      $dashboards,
      $deadlines,
      $entityTypeManager,
      $account,
      $this->createMock(DateFormatterInterface::class),
      $this->getStringTranslationStub(),
    );

    $build = $controller->student();

    self::assertSame(0, $build['#cache']['max-age']);
    self::assertContains('user', $build['#cache']['contexts']);
    self::assertContains('lms_course_status_list', $build['#cache']['tags']);
  }

}
