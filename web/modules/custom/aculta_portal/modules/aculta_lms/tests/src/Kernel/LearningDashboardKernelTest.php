<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\aculta_portal\AccountCoursesManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\KernelTests\KernelTestBase;
use Drupal\group\PermissionScopeInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatus;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\Lesson;
use Drupal\lms\Entity\LessonStatus;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests student progress against real Group and LMS entities.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class LearningDashboardKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'field', 'filter', 'image', 'media', 'options', 'entity_print',
    'entity_usage', 'aculta_image', 'flexible_permissions', 'group', 'views', 'text', 'lms', 'aculta_lms',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['group', 'lms']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('action');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('lms_lesson');
    $this->installEntitySchema('lms_course_status');
    $this->installEntitySchema('lms_lesson_status');
    $this->installEntitySchema('aculta_lms_certificate');
  }

  /**
   * Verifies progress follows course access and published lessons.
   */
  public function testStudentDashboardCountsAccessibleLessonsOnly(): void {
    $owner = User::create(['name' => 'dashboard-owner', 'status' => 1]);
    $owner->save();
    $student = User::create(['name' => 'dashboard-student', 'status' => 1]);
    $student->save();

    $visible_lesson = Lesson::create(['name' => 'Aula visível', 'status' => 1, 'uid' => $owner->id()]);
    $visible_lesson->save();
    $restricted_lesson = Lesson::create(['name' => 'Aula restrita', 'status' => 1, 'uid' => $owner->id()]);
    $restricted_lesson->save();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso do painel',
      'uid' => $owner->id(),
      'status' => 1,
    ]);
    $course->get(CourseInterface::LESSONS)->appendItem(['target_id' => $visible_lesson->id()]);
    $course->get(CourseInterface::LESSONS)->appendItem(['target_id' => $restricted_lesson->id()]);
    $course->save();
    GroupRole::create([
      'id' => 'lms_course-dashboard_student',
      'label' => 'Estudante de teste',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['view group', 'take course'],
    ])->save();
    $course->addMember($student, ['group_roles' => ['lms_course-dashboard_student']]);
    self::assertCount(1, $this->container->get('group.membership_loader')->loadByUser($student));
    self::assertTrue($course->access('view', $student));
    self::assertTrue($course->takeAccess($student)->isAllowed());
    self::assertFalse($visible_lesson->access('view', $student), 'Generic lesson view access does not represent access through the enrolled course.');

    $restricted_lesson->setUnpublished();
    $restricted_lesson->save();

    $course_status = CourseStatus::create([
      CourseStatusInterface::COURSE_FIELD => $course->id(),
      'uid' => $student->id(),
      'status' => CourseStatusInterface::STATUS_NEEDS_EVALUATION,
      'finished' => 0,
      'current' => TRUE,
    ]);
    $course_status->save();
    foreach ([$visible_lesson, $restricted_lesson] as $lesson) {
      LessonStatus::create([
        'course_status' => $course_status->id(),
        LessonStatusInterface::LESSON_FIELD => $lesson->id(),
        'finished' => 1_760_000_000,
      ])->save();
    }
    $other_student = User::create(['name' => 'dashboard-other-student', 'status' => 1]);
    $other_student->save();
    $certificate_storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_certificate');
    $own_certificate = $certificate_storage->create([
      'uid' => $student->id(),
      'course_id' => $course->id(),
      'course_status_uuid' => $course_status->uuid(),
      'certificate_name' => 'dashboard-student',
      'course_title' => 'Curso do painel',
      'course_hours' => 3,
      'completed' => 1_760_000_000,
      'issued' => 1_760_000_100,
      'sequence' => 1,
      'supersedes_id' => 0,
      'verification_code' => str_repeat('D', 43),
      'template_version' => '1',
      'state' => 'active',
    ]);
    $own_certificate->save();
    $other_certificate = $certificate_storage->create([
      'uid' => $other_student->id(),
      'course_id' => $course->id(),
      'course_status_uuid' => 'other-course-status-uuid',
      'certificate_name' => 'other-student',
      'course_title' => 'Curso do painel',
      'course_hours' => 3,
      'completed' => 1_760_000_000,
      'issued' => 1_760_000_100,
      'sequence' => 1,
      'supersedes_id' => 0,
      'verification_code' => str_repeat('E', 43),
      'template_version' => '1',
      'state' => 'active',
    ]);
    $other_certificate->save();

    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $domain_storage->method('load')->willReturn(NULL);
    $domain_entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $domain_entity_type_manager->expects(self::never())->method('getStorage');
    $domain_purpose = new DomainPurposeManager(
      $domain_entity_type_manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $account_courses = new AccountCoursesManager(
      $this->container->get('lms.training_manager'),
      $domain_purpose,
      $this->container->get('group.membership_loader'),
    );
    $manager = new LearningDashboardManager(
      $account_courses,
      $this->container->get('aculta_lms.course_certificate_manager'),
      $this->container->get('entity_type.manager'),
      $this->container->get('lms.training_manager'),
    );

    $dashboard = $manager->studentDashboard($student);

    self::assertCount(1, $dashboard['courses']);
    self::assertSame(CourseStatusInterface::STATUS_NEEDS_EVALUATION, $dashboard['courses'][0]['status']);
    self::assertTrue($dashboard['courses'][0]['pending_evaluation']);
    self::assertSame(1, $dashboard['courses'][0]['total_lessons']);
    self::assertSame(1, $dashboard['courses'][0]['completed_lessons']);
    self::assertSame(100, $dashboard['courses'][0]['progress_percent']);
    self::assertCount(1, $dashboard['certificates']);
    $visible_certificates = array_values($dashboard['certificates']);
    self::assertSame((string) $student->id(), (string) $visible_certificates[0]->get('uid')->value);
    self::assertSame(str_repeat('D', 43), $visible_certificates[0]->get('verification_code')->value);
    self::assertContains('aculta_lms_certificate:' . $own_certificate->id(), $dashboard['cache']->getCacheTags());
    self::assertContains('user', $dashboard['cache']->getCacheContexts());
    self::assertContains('user.group_permissions', $dashboard['cache']->getCacheContexts());
    self::assertContains('lms_lesson:' . $restricted_lesson->id(), $dashboard['cache']->getCacheTags());
  }

}
