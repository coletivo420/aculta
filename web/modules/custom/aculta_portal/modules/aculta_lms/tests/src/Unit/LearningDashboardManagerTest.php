<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_portal\AccountCoursesManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Controller\CertificateVerificationController;
use Drupal\aculta_lms\Hook\LearningDashboardHooks;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\lms\Entity\Bundle\Course;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\Lesson;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\lms\TrainingManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests fail-closed access and isolated empty states for LMS dashboards. */
#[Group('aculta_lms')]
final class LearningDashboardManagerTest extends UnitTestCase {

  /**
   * Verifies the student dashboard stays scoped to the supplied account.
   */
  public function testStudentDashboardContainsOnlyTheSuppliedAccountScope(): void {
    $certificates = $this->createMock(CourseCertificateManagerInterface::class);
    $certificates->expects(self::once())->method('loadForStudent')->with(42)->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $manager = new LearningDashboardManager(
      NULL,
      $certificates,
      $entityTypeManager,
      $this->createMock(TrainingManager::class),
    );

    $dashboard = $manager->studentDashboard(new UserSession(['uid' => 42]));

    self::assertSame([], $dashboard['courses']);
    self::assertSame([], $dashboard['certificates']);
    self::assertContains('user', $dashboard['cache']->getCacheContexts());
  }

  /**
   * Verifies student progress counts only lessons available to the account.
   */
  public function testStudentProgressUsesCourseAccessAndPublishedLessons(): void {
    $account = new UserSession(['uid' => 42]);
    $visible_lesson = $this->createMock(Lesson::class);
    $visible_lesson->method('id')->willReturn('lesson-visible');
    $visible_lesson->method('getCacheContexts')->willReturn([]);
    $visible_lesson->method('getCacheTags')->willReturn(['lms_lesson:visible']);
    $visible_lesson->method('getCacheMaxAge')->willReturn(-1);
    $visible_lesson->method('isPublished')->willReturn(TRUE);
    $visible_lesson->expects(self::never())->method('access');

    $restricted_lesson = $this->createMock(Lesson::class);
    $restricted_lesson->method('id')->willReturn('lesson-restricted');
    $restricted_lesson->method('getCacheContexts')->willReturn([]);
    $restricted_lesson->method('getCacheTags')->willReturn(['lms_lesson:restricted']);
    $restricted_lesson->method('getCacheMaxAge')->willReturn(-1);
    $restricted_lesson->method('isPublished')->willReturn(FALSE);
    $restricted_lesson->expects(self::never())->method('access');

    $lesson_list = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $lesson_list->method('referencedEntities')->willReturn([$visible_lesson, $restricted_lesson]);
    $course = $this->createMock(Course::class);
    $course->method('takeAccess')->with($account)->willReturn(AccessResult::allowed()->addCacheContexts(['user.group_permissions']));
    $course->method('get')->with(CourseInterface::LESSONS)->willReturn($lesson_list);
    $course->method('id')->willReturn('course-1');
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn(['group:course-1']);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $membership = $this->createMock(GroupMembershipInterface::class);
    $membership->expects(self::once())->method('getGroup')->willReturn($course);
    $membership_loader = $this->createMock(GroupMembershipLoaderInterface::class);
    $membership_loader->expects(self::once())->method('loadByUser')->with($account)->willReturn([$membership]);
    $course->method('access')->with('view', $account)->willReturn(TRUE);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasField')->with('field_description')->willReturn(FALSE);
    $course->method('label')->willReturn('Curso');
    $course_status = $this->createMock(CourseStatusInterface::class);
    $course_status->method('id')->willReturn('status-1');
    $course_status->method('getStatus')->willReturn(CourseStatusInterface::STATUS_PROGRESS);
    $course_status->method('isFinished')->willReturn(FALSE);
    $course_status->method('getScore')->willReturn(NULL);
    $course_status->method('getCacheContexts')->willReturn([]);
    $course_status->method('getCacheTags')->willReturn(['lms_course_status:status-1']);
    $course_status->method('getCacheMaxAge')->willReturn(-1);
    $training_manager = $this->createMock(TrainingManager::class);
    $training_manager->expects(self::exactly(2))
      ->method('loadCourseStatus')
      ->with($course, $account, ['current' => TRUE])
      ->willReturnOnConsecutiveCalls(NULL, $course_status);
    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $domain_storage->expects(self::once())->method('load')->with('cursos_aculta_org')->willReturn(NULL);
    $domain_entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $domain_entity_type_manager->expects(self::once())->method('getStorage')->with('domain')->willReturn($domain_storage);
    $domain_purpose = new DomainPurposeManager(
      $domain_entity_type_manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $courses = new AccountCoursesManager($training_manager, $domain_purpose, $membership_loader);
    $group_storage = $this->createMock(EntityStorageInterface::class);
    $group_storage->expects(self::once())->method('load')->with('course-1')->willReturn($course);
    $status_query = $this->createMock(QueryAggregateInterface::class);
    $status_query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $status_query->method('condition')->willReturnCallback(function (string $field, mixed $value = NULL, ?string $operator = NULL) use ($status_query): QueryAggregateInterface {
      if ($field === LessonStatusInterface::LESSON_FIELD) {
        self::assertSame(['lesson-visible'], $value);
        self::assertSame('IN', $operator);
      }
      return $status_query;
    });
    $status_query->method('groupBy')->with(LessonStatusInterface::LESSON_FIELD)->willReturnSelf();
    $status_query->expects(self::once())->method('execute')->willReturn([['lesson_revision' => 'lesson-visible']]);
    $status_storage = $this->createMock(EntityStorageInterface::class);
    $status_storage->expects(self::once())->method('getAggregateQuery')->willReturn($status_query);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::exactly(2))
      ->method('getStorage')
      ->willReturnMap([
        ['group', $group_storage],
        ['lms_lesson_status', $status_storage],
      ]);
    $certificates = $this->createMock(CourseCertificateManagerInterface::class);
    $certificates->expects(self::once())->method('loadForStudent')->with(42)->willReturn([]);
    $manager = new LearningDashboardManager($courses, $certificates, $entityTypeManager, $training_manager);

    $dashboard = $manager->studentDashboard($account);

    self::assertSame(1, $dashboard['courses'][0]['total_lessons']);
    self::assertSame(1, $dashboard['courses'][0]['completed_lessons']);
    self::assertContains('user.group_permissions', $dashboard['cache']->getCacheContexts());
    self::assertContains('lms_lesson:restricted', $dashboard['cache']->getCacheTags());
  }

  /**
   * Verifies an unrelated or orphan class has no educator dashboard.
   */
  public function testClassWithoutLmsCourseRelationshipIsDeniedAndHasNoMetrics(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::exactly(2))->method('loadByProperties')->with([
      'entity_id' => 'class-1',
      'plugin_id' => 'lms_classes',
    ])->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::exactly(2))->method('getStorage')->with('group_relationship')->willReturn($storage);
    $manager = new LearningDashboardManager(
      NULL,
      $this->createMock(CourseCertificateManagerInterface::class),
      $entityTypeManager,
      $this->createMock(TrainingManager::class),
    );
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

  /**
   * Verifies only authenticated accounts receive the LMS dashboard link. */
  public function testAccountNavigationHidesDashboardFromAnonymousAccounts(): void {
    $hooks = new LearningDashboardHooks();
    $sections = [];
    $hooks->addStudentDashboard($sections, new UserSession(['uid' => 0]));
    self::assertArrayNotHasKey('aculta_lms.student_dashboard', $sections);

    $hooks->addStudentDashboard($sections, new UserSession(['uid' => 42]));
    self::assertSame('aculta_lms.student_dashboard', $sections['aculta_lms.student_dashboard']['route']);
    self::assertSame('mortarboard', $sections['aculta_lms.student_dashboard']['icon']);
  }

  /**
   * Ensures missing PDF tooling produces a controlled response.
   */
  public function testCertificatePdfRouteFailsClosedWhenPrintEngineIsMissing(): void {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(
      static fn(): TranslatableMarkup => new TranslatableMarkup('PDF indisponível.'),
    );
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

}
