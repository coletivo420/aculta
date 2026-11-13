<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Access\ClassDashboardAccessCheck;
use Drupal\aculta_lms\Certificate\CourseCertificateManager;
use Drupal\aculta_lms\Controller\LearningDashboardController;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\aculta_lms\Learning\ClassStatusHistory;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\aculta_lms\Learning\CourseLessonReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgressReaderInterface;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\aculta_lms\Entity\ProgressEntity;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\group\PermissionScopeInterface;
use Drupal\user\UserInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Verifies LMS class dashboard counts remain scoped to current class members. */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class ClassDashboardKernelTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

  use UserCreationTrait;

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'key',
    'system', 'domain', 'node', 'comment', 'gnode',
    'user',
    'file',
    'image',
    'media',
    'media_gallery', 'media_library', 'photoswipe',
    'entity_usage',
    'aculta_image', 'aculta_file_uploader',
    'field',
    'filter',
    'flexible_permissions',
    'group',
    'views',
    'editor',
    'ckeditor5',
    'options',
    'text',
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
    // The focus is Group authorization, not Portal's unrelated route/config
    // install chain. Load the actual production classes used by this test.
    $lms_root = dirname(__DIR__, 3);
    $portal_root = dirname($lms_root, 2);
    $this->classLoader->addPsr4('Drupal\\aculta_lms\\', $lms_root . '/src');
    $this->classLoader->addPsr4('Drupal\\aculta_studio\\', $portal_root . '/modules/aculta_studio/src');
    $this->prepareAcultaLmsGroupTypes();
    $this->installEntitySchema('node');
    $this->prepareAcultaLmsPartialConfigDependencies();
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->prepareAcultaLmsClassRelationship();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('aculta_lms_lesson');
    $this->installEntitySchema('aculta_lms_activity');
    $this->installEntitySchema('aculta_lms_progress');
  }

  /**
   * Checks contextual educator access and student denial for class metrics.
   */
  public function testEducatorDashboardAggregatesOnlyCurrentClassMembers(): void {
    $owner = $this->createUser();
    $educator = $this->createUser();
    $memberPending = $this->createUser();
    $memberPassed = $this->createUser();
    $memberBlocked = $this->createUser();
    $memberBlocked->block();
    $memberBlocked->save();
    $outsiderFailed = $this->createUser();
    $teacher = $this->createUser();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso do painel',
      'uid' => $owner->id(),
    ]);
    $course->save();
    GroupRole::create([
      'id' => 'lms_course-test_teacher',
      'label' => 'Educador de teste',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students', 'take course'],
    ])->save();
    $course->addMember($educator, ['group_roles' => ['lms_course-test_teacher']]);
    $course->addMember($teacher, ['group_roles' => ['lms_course-test_teacher']]);
    $class = Group::create([
      'type' => 'lms_class',
      'label' => 'Turma do painel',
      'uid' => $owner->id(),
    ]);
    $class->save();
    $course->addRelationship($class, 'lms_classes');
    $class->addMember($memberPending);
    $class->addMember($memberPassed);
    $class->addMember($memberBlocked);
    $class->addMember($teacher);

    $this->createStatus($course, $memberPending, CourseProgress::STATUS_NEEDS_EVALUATION);
    $this->createStatus($course, $memberPassed, CourseProgress::STATUS_PASSED);
    $this->createStatus($course, $outsiderFailed, CourseProgress::STATUS_FAILED);
    self::assertTrue($course->hasPermission('grade students', $teacher));

    $entityTypeManager = $this->container->get('entity_type.manager');
    $manager = new LearningDashboardManager(
      NULL,
      new CourseCertificateManager(
        $entityTypeManager,
        $this->container->get('datetime.time'),
        $this->container->get('database'),
        $this->container->get('event_dispatcher'),
        $this->container->get('logger.factory')->get('aculta_lms'),
        $this->container->get('lock'),
      ),
      $entityTypeManager,
      $this->container->get(CourseProgressReaderInterface::class),
      $this->container->get(CourseLessonReaderInterface::class),
    );
    $educatorAccess = $manager->classDashboardAccess($class, $educator);
    self::assertTrue($educatorAccess->isAllowed());
    $cache = $this->container->get('cache.default');
    $cache->set('aculta_lms_test.linked_class_dashboard', TRUE, Cache::PERMANENT, $educatorAccess->getCacheTags());
    self::assertNotFalse($cache->get('aculta_lms_test.linked_class_dashboard'));
    self::assertFalse($manager->classDashboardAccess($class, $memberPending)->isAllowed(), 'A student cannot read educator aggregates.');
    self::assertNull($manager->classDashboard($class, $memberPending));
    $metrics = $manager->classDashboard(
      $class,
      $educator,
    );

    self::assertNotNull($metrics);
    self::assertSame(2, $metrics['students']);
    self::assertSame(1, $metrics['pending_evaluation']);
    self::assertSame(1, $metrics['passed']);
    self::assertSame(0, $metrics['failed'], 'The failed status belonging to an outsider is excluded.');
    self::assertContains('user:' . $memberBlocked->id(), $metrics['cache']->getCacheTags(), 'Changing the blocked account invalidates the aggregate so it can be recounted if reactivated.');
    self::assertArrayNotHasKey('answers', $metrics);
    self::assertArrayNotHasKey('student_ids', $metrics);
    self::assertContains('group_relationship_list:plugin:group_membership:group:' . $class->id(), $metrics['cache']->getCacheTags());
    self::assertContains('group_relationship_list:plugin:group_membership:entity:' . $memberPending->id(), $metrics['cache']->getCacheTags());
    self::assertContains('config:group_role_list', $metrics['cache']->getCacheTags());
    self::assertContains('config:user.role_list', $metrics['cache']->getCacheTags());
    self::assertContains('user.group_permissions', $metrics['cache']->getCacheContexts());

    $this->container->get('current_user')->setAccount($educator);
    $routeAccess = new ClassDashboardAccessCheck($manager, $this->container->get('current_user'));
    self::assertTrue($routeAccess->access($course, $class)->isAllowed());
    $unrelatedCourse = Group::create([
      'type' => 'lms_course',
      'label' => 'Outro curso',
      'uid' => $owner->id(),
    ]);
    $unrelatedCourse->save();
    self::assertFalse($routeAccess->access($unrelatedCourse, $class)->isAllowed(), 'The dashboard route cannot pair a class with another course.');
    $currentUser = $this->container->get('current_user');
    $dateFormatter = $this->container->get('date.formatter');
    $translation = $this->container->get('string_translation');
    $deadlineAccess = new ClassActivityDeadlineAccessCheck($entityTypeManager, $currentUser);
    $statisticsStorage = $this->createMock(AggregateStorageInterface::class);
    $statisticsStorage->method('currentDay')->willReturn(200);
    $statisticsStorage->expects(self::once())
      ->method('dailyDimensionTotalsForDimensions')
      ->with(
        ClassStatusHistory::METRIC,
        187,
        200,
        array_map(static fn(string $situation): string => $course->id() . '/' . $class->id() . '|' . $situation, ClassStatusHistory::SITUATIONS),
      )
      ->willReturn([200 => [$course->id() . '/' . $class->id() . '|students' => 2]]);
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/painel?dias=14'));
    $controller = new LearningDashboardController(
      $manager,
      new ClassActivityDeadlineManager($entityTypeManager, $deadlineAccess),
      $entityTypeManager,
      $currentUser,
      $dateFormatter,
      $translation,
      new ClassStatusHistory($statisticsStorage),
      $requestStack,
    );
    $build = $controller->class($class, $course);
    self::assertCount(14, $build['status_history']['table']['#rows']);
    self::assertSame(2, $build['status_history']['table']['#rows'][13][1]);
    self::assertSame(0, $build['status_history']['table']['#rows'][13][2]);
    self::assertCount(3, $build['status_history']['period']['options']['#items']);
    $periodLinks = $build['status_history']['period']['options']['#items'];
    self::assertSame([7, 14, 30], array_map(
      static fn(array $link): int => (int) $link['#url']->getOption('query')['dias'],
      $periodLinks,
    ));
    self::assertSame('page', $periodLinks[1]['#attributes']['aria-current']);
    self::assertSame('aculta_lms.class_dashboard', $periodLinks[1]['#url']->getRouteName());
    self::assertSame(0, $build['#cache']['max-age']);
    self::assertContains('user', $build['#cache']['contexts']);
    self::assertContains('group_relationship_list:plugin:group_membership:group:' . $class->id(), $build['#cache']['tags']);

    $classCourseRelationships = $entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $class->id(),
      'plugin_id' => 'lms_classes',
    ]);
    $classCourseRelationship = reset($classCourseRelationships);
    self::assertNotFalse($classCourseRelationship);
    $classCourseRelationship->delete();
    self::assertFalse($cache->get('aculta_lms_test.linked_class_dashboard'), 'Removing the parent course invalidates cached educator dashboard access.');
  }

  /**
   * Creates a course status with the requested state.
   */
  private function createStatus(Group $course, UserInterface $student, string $status): void {
    ProgressEntity::create([
      'course_id' => $course->id(),
      'uid' => $student->id(),
      'status' => $status,
      'finished' => $status === CourseProgress::STATUS_PASSED,
    ])->save();
  }

}
