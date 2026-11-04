<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Access\AnswerSubmissionAccessCheck;
use Drupal\aculta_lms\Access\LessonViewAccessCheck;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\aculta_lms\Learning\CourseLessonReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary;
use Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupRelationshipType;
use Drupal\group\Entity\GroupType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\group\PermissionScopeInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests student progress against Group and ACULTA-owned learning entities.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class LearningDashboardKernelTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'system', 'domain', 'user', 'file', 'field', 'filter', 'image', 'media', 'options', 'entity_print',
    'entity_usage', 'aculta_image', 'aculta_file_uploader', 'flexible_permissions', 'group', 'views', 'text', 'aculta_lms',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->registerLmsDependencyServices($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installConfig(['group']);
    $group_type = GroupType::create([
      'id' => 'lms_course',
      'label' => 'Curso',
      'creator_membership' => FALSE,
      'creator_roles' => [],
    ]);
    $group_type->save();
    $relationship_type = GroupRelationshipType::load('lms_course-group_membership') ?? GroupRelationshipType::create([
      'id' => 'lms_course-group_membership',
      'group_type' => 'lms_course',
      'relation_type' => 'group_membership',
      'plugin_config' => [
        'group_cardinality' => 0,
        'entity_cardinality' => 1,
      ],
    ]);
    $relationship_type->save();
    $field_storage = FieldStorageConfig::load('group_relationship.group_roles') ?? FieldStorageConfig::create([
      'field_name' => 'group_roles',
      'entity_type' => 'group_relationship',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'group_role'],
      'cardinality' => -1,
    ]);
    $field_storage->save();
    $field = FieldConfig::load('group_relationship.lms_course-group_membership.group_roles') ?? FieldConfig::create([
      'field_name' => 'group_roles',
      'entity_type' => 'group_relationship',
      'bundle' => 'lms_course-group_membership',
      'label' => 'Group roles',
      'settings' => ['handler' => 'default', 'handler_settings' => []],
    ]);
    $field->save();
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('action');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('aculta_lms_lesson');
    $this->installEntitySchema('aculta_lms_activity');
    $this->installEntitySchema('aculta_lms_progress');
    $this->installEntitySchema('aculta_lms_certificate');
  }

  /**
   * Verifies progress follows course access and published lessons.
   */
  public function testStudentDashboardUsesOwnedLessonAndProgressEntities(): void {
    $owner = User::create(['name' => 'dashboard-owner', 'status' => 1]);
    $owner->save();
    $student = User::create(['name' => 'dashboard-student', 'status' => 1]);
    $student->save();

    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso do painel',
      'uid' => $owner->id(),
      'status' => 1,
    ]);
    $course->save();
    $lesson_storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_lesson');
    $visible_lesson = $lesson_storage->create([
      'course_id' => $course->id(),
      'title' => 'Aula visível',
      'published' => TRUE,
      'weight' => 1,
    ]);
    $visible_lesson->save();
    $restricted_lesson = $lesson_storage->create([
      'course_id' => $course->id(),
      'title' => 'Aula restrita',
      'published' => FALSE,
      'weight' => 0,
    ]);
    $restricted_lesson->save();
    GroupRole::create([
      'id' => 'lms_course-dashboard_student',
      'label' => 'Estudante de teste',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['view group', 'take course'],
    ])->save();
    $course->addMember($student, ['group_roles' => ['lms_course-dashboard_student']]);
    self::assertTrue($course->access('view', $student));
    self::assertTrue($course->hasPermission('take course', $student));

    $progress = $this->container->get('entity_type.manager')->getStorage('aculta_lms_progress')->create([
      'course_id' => $course->id(),
      'uid' => $student->id(),
      'status' => CourseProgress::STATUS_NEEDS_EVALUATION,
      'finished' => FALSE,
      'score' => NULL,
    ]);
    $progress->save();
    $other_student = User::create(['name' => 'dashboard-other-student', 'status' => 1]);
    $other_student->save();
    $certificate_storage = $this->container->get('entity_type.manager')->getStorage('aculta_lms_certificate');
    $own_certificate = $certificate_storage->create([
      'uid' => $student->id(),
      'course_id' => $course->id(),
      'course_status_uuid' => $progress->uuid(),
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

    $manager = new LearningDashboardManager(
      $this->container->get(AccountCoursesReaderInterface::class),
      $this->container->get('aculta_lms.course_certificate_manager'),
      $this->container->get('entity_type.manager'),
      $this->container->get(CourseProgressReaderInterface::class),
      $this->container->get(CourseLessonReaderInterface::class),
    );

    $dashboard = $manager->studentDashboard($student);

    self::assertCount(1, $dashboard['courses']);
    self::assertSame(AccountCourseSummary::STATUS_AWAITING_EVALUATION, $dashboard['courses'][0]['status']);
    self::assertTrue($dashboard['courses'][0]['pending_evaluation']);
    self::assertSame(1, $dashboard['courses'][0]['total_lessons']);
    self::assertSame(0, $dashboard['courses'][0]['completed_lessons']);
    self::assertSame(0, $dashboard['courses'][0]['progress_percent']);
    self::assertCount(1, $dashboard['certificates']);
    $visible_certificates = array_values($dashboard['certificates']);
    self::assertSame((string) $student->id(), (string) $visible_certificates[0]->get('uid')->value);
    self::assertSame(str_repeat('D', 43), $visible_certificates[0]->get('verification_code')->value);
    self::assertContains('aculta_lms_certificate:' . $own_certificate->id(), $dashboard['cache']->getCacheTags());
    self::assertContains('user', $dashboard['cache']->getCacheContexts());
    self::assertContains('user.group_permissions', $dashboard['cache']->getCacheContexts());
    self::assertContains('aculta_lms_lesson:' . $visible_lesson->id(), $dashboard['cache']->getCacheTags());

    $journey = $manager->courseJourney($course, $student);
    self::assertCount(1, $journey->lessons);
    self::assertSame(1, $journey->lessons[0]->delta);
    self::assertSame('aculta_lms.lesson_view', $journey->lessons[0]->url?->getRouteName());
    self::assertSame((string) $course->id(), (string) $journey->lessons[0]->url?->getRouteParameters()['group']);

    $lesson_page = $this->container->get('aculta_lms.course_lesson_controller')
      ->view($course, $journey->lessons[0]->delta);
    self::assertSame('Aula visível', $lesson_page['title']['#value']);
    self::assertSame('Esta aula ainda não tem atividades.', (string) $lesson_page['activities']['#empty']);
    self::assertContains('aculta_lms_lesson_list', $lesson_page['#cache']['tags']);
    self::assertContains('aculta_lms_activity_list', $lesson_page['#cache']['tags']);

    $cache = $this->container->get('cache.default');
    $cache_key = 'aculta_lms_test_activity_list_invalidation';
    $cache->set($cache_key, 'cached', Cache::PERMANENT, $lesson_page['#cache']['tags']);
    self::assertNotFalse($cache->get($cache_key));
    $activity = $this->container->get('entity_type.manager')
      ->getStorage('aculta_lms_activity')
      ->create([
        'lesson_id' => (int) $visible_lesson->id(),
        'type' => 'free_text',
        'graded' => FALSE,
        'weight' => 0,
        'answer_config' => '{"question":"Atividade criada no Kernel"}',
      ]);
    $activity->save();
    self::assertFalse($cache->get($cache_key));

    $updated_lesson_page = $this->container->get('aculta_lms.course_lesson_controller')
      ->view($course, $journey->lessons[0]->delta);
    self::assertCount(1, $updated_lesson_page['activities']['#rows']);

    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('isAuthenticated')->willReturn(TRUE);
    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->method('isContentAvailable')->willReturn(TRUE);
    $eligibility->method('hasCurrentEnrollment')->willReturn(TRUE);
    $eligibility->method('activityBelongsToCourse')->willReturn(TRUE);
    $slugs = $this->container->get('aculta_portal.lms_friendly_slugs');
    $lesson_access = (new LessonViewAccessCheck($slugs, $eligibility, $current_user))
      ->access($course, $journey->lessons[0]->delta);
    self::assertTrue($lesson_access->isAllowed());
    self::assertContains('aculta_lms_lesson_list', $lesson_access->getCacheTags());
    $answer_access = (new AnswerSubmissionAccessCheck($slugs, $eligibility, $current_user))
      ->access($course, $journey->lessons[0]->delta, 0);
    self::assertTrue($answer_access->isAllowed());
    self::assertContains('aculta_lms_lesson_list', $answer_access->getCacheTags());
    self::assertContains('aculta_lms_activity_list', $answer_access->getCacheTags());
  }

}
