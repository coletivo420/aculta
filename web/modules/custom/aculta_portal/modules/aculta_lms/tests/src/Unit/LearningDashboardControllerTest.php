<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Controller\LearningDashboardController;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\Learning\CourseLessonReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests fail-closed dashboard behavior for groups outside the LMS.
 */
#[CoversClass(LearningDashboardController::class)]
#[Group('aculta_lms')]
final class LearningDashboardControllerTest extends UnitTestCase {

  /**
   * A non-LMS Group never renders dashboard content.
   */
  public function testDashboardRejectsGroupOutsideLms(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $relationshipStorage = $this->createMock(EntityStorageInterface::class);
    $relationshipStorage->method('loadByProperties')->willReturn([]);
    $entityTypeManager->method('getStorage')->with('group_relationship')->willReturn($relationshipStorage);
    $dashboards = new LearningDashboardManager(
      NULL,
      $this->createMock(CourseCertificateManagerInterface::class),
      $entityTypeManager,
      $this->createMock(CourseProgressReaderInterface::class),
      $this->createMock(CourseLessonReaderInterface::class),
    );
    $deadlines = new ClassActivityDeadlineManager(
      $entityTypeManager,
      new ClassActivityDeadlineAccessCheck($entityTypeManager, $account),
    );
    $controller = new LearningDashboardController(
      $dashboards,
      $deadlines,
      $entityTypeManager,
      $account,
      $this->createMock(DateFormatterInterface::class),
      $this->getStringTranslationStub(),
    );
    $group = $this->createMock(GroupInterface::class);
    $group->method('bundle')->willReturn('not_an_lms_class');
    $group->method('id')->willReturn('2');
    $group->method('getCacheContexts')->willReturn([]);
    $group->method('getCacheTags')->willReturn([]);
    $group->method('getCacheMaxAge')->willReturn(-1);
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('1');

    self::assertSame('Turma indisponível.', (string) $controller->class($group, $course)['#plain_text']);
  }

}
