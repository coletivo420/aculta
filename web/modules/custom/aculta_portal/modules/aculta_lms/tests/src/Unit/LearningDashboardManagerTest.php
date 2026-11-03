<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Controller\CertificateVerificationController;
use Drupal\aculta_lms\Learning\CourseLesson;
use Drupal\aculta_lms\Learning\CourseLessonReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary;
use Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Tests ACULTA-backed learning dashboard projections and access. */
#[Group('aculta_lms')]
final class LearningDashboardManagerTest extends UnitTestCase {

  public function testEmptyStudentDashboardStaysScopedToTheSuppliedAccount(): void {
    $certificates = $this->createMock(CourseCertificateManagerInterface::class);
    $certificates->expects(self::once())->method('loadForStudent')->with(42)->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $manager = $this->manager(NULL, $certificates, $entityTypeManager);

    $dashboard = $manager->studentDashboard(new UserSession(['uid' => 42]));

    self::assertSame([], $dashboard['courses']);
    self::assertSame([], $dashboard['certificates']);
    self::assertContains('user', $dashboard['cache']->getCacheContexts());
  }

  public function testStudentDashboardUsesOwnPublishedLessonsAndCourseLevelProgress(): void {
    $account = new UserSession(['uid' => 42]);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('1');
    $course->method('isPublished')->willReturn(TRUE);
    $course->method('hasPermission')->with('take course', $account)->willReturn(TRUE);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn(['group:1']);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $groupStorage = $this->createMock(EntityStorageInterface::class);
    $groupStorage->expects(self::once())->method('load')->with('1')->willReturn($course);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())->method('getStorage')->with('group')->willReturn($groupStorage);

    $summary = new AccountCourseSummary(
      id: '1', label: 'Curso', description: '', descriptionFormat: '',
      status: AccountCourseSummary::STATUS_IN_PROGRESS, score: NULL,
      finished: FALSE, url: NULL, cacheTags: ['group:1'],
    );
    $coursesReader = $this->createMock(AccountCoursesReaderInterface::class);
    $coursesReader->method('getAccountCourses')->with($account)->willReturn([$summary]);
    $progressReader = $this->createMock(CourseProgressReaderInterface::class);
    $progressReader->expects(self::once())->method('current')->with($course, $account)->willReturn(
      new CourseProgress(CourseProgress::STATUS_PROGRESS, FALSE, NULL, ['aculta_lms_progress:1'], 123),
    );
    $lessonReader = $this->createMock(CourseLessonReaderInterface::class);
    $lessonReader->expects(self::once())->method('forCourse')->with(1)->willReturn([
      new CourseLesson(5, 1, 'Aula publicada', TRUE, 0),
      new CourseLesson(6, 1, 'Aula ainda oculta', FALSE, 1),
    ]);
    $certificates = $this->createMock(CourseCertificateManagerInterface::class);
    $certificates->method('loadForStudent')->with(42)->willReturn([]);
    $manager = new LearningDashboardManager($coursesReader, $certificates, $entityTypeManager, $progressReader, $lessonReader);

    $dashboard = $manager->studentDashboard($account);

    self::assertCount(1, $dashboard['courses']);
    self::assertSame(1, $dashboard['courses'][0]['total_lessons']);
    self::assertSame(0, $dashboard['courses'][0]['completed_lessons']);
    self::assertSame(0, $dashboard['courses'][0]['progress_percent']);
    self::assertTrue($dashboard['courses'][0]['started']);
    self::assertContains('user.group_permissions', $dashboard['cache']->getCacheContexts());
  }

  public function testClassWithoutLmsCourseRelationshipIsDenied(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::exactly(2))->method('loadByProperties')->with([
      'entity_id' => 'class-1', 'plugin_id' => 'lms_classes',
    ])->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::exactly(2))->method('getStorage')->with('group_relationship')->willReturn($storage);
    $manager = $this->manager(NULL, $this->createMock(CourseCertificateManagerInterface::class), $entityTypeManager);
    $class = $this->createMock(GroupInterface::class);
    $class->method('bundle')->willReturn('lms_class');
    $class->method('id')->willReturn('class-1');
    $class->method('getCacheContexts')->willReturn([]);
    $class->method('getCacheTags')->willReturn([]);
    $class->method('getCacheMaxAge')->willReturn(-1);
    $account = new UserSession(['uid' => 7, 'permissions' => ['administer lms']]);

    self::assertFalse($manager->classDashboardAccess($class, $account)->isAllowed());
    self::assertNull($manager->classDashboard($class, $account));
  }

  public function testCertificatePdfRouteFailsClosedWhenPrintEngineIsMissing(): void {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturn('PDF indisponível.');
    $controller = new CertificateVerificationController(
      $this->createMock(CourseCertificateManagerInterface::class),
      NULL,
      $this->createMock(DateFormatterInterface::class),
      $translation,
      NULL,
    );

    $this->expectException(ServiceUnavailableHttpException::class);
    $controller->pdf('opaque-verification-code');
  }

  private function manager(?AccountCoursesReaderInterface $coursesReader, CourseCertificateManagerInterface $certificates, EntityTypeManagerInterface $entityTypeManager): LearningDashboardManager {
    return new LearningDashboardManager(
      $coursesReader,
      $certificates,
      $entityTypeManager,
      $this->createMock(CourseProgressReaderInterface::class),
      $this->createMock(CourseLessonReaderInterface::class),
    );
  }

}
