<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_lms\Entity\ActivityEntity;
use Drupal\aculta_lms\Entity\LessonEntity;
use Drupal\group\Entity\Group;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests class-specific activity deadlines with real Group entities.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class ClassActivityDeadlineTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'system', 'domain',
    'datetime',
    'user',
    'file',
    'image',
    'media',
    'options',
    'entity_print',
    'entity_usage',
    'aculta_image', 'aculta_file_uploader',
    'field',
    'filter',
    'flexible_permissions',
    'group',
    'views',
    'options',
    'text',
    'user',
    'aculta_lms',
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
    $this->installConfig(['system', 'group']);
    foreach ([
      'group.type.lms_course',
      'group.type.lms_class',
      'group.relationship_type.lms_course-group_membership',
      'group.relationship_type.lms_class-group_membership',
      'group.relationship_type.lms_course-lms_classes',
      'group.relationship_type.lms_class-lms_class_activity_due',
      'group.role.lms_course-anonymous',
      'group.role.lms_course-course_creator',
      'group.role.lms_course-member',
      'group.role.lms_course-outsider',
      'group.role.lms_course-teacher',
      'group.role.lms_class-member',
      'field.storage.group_relationship.field_activity_due_at',
      'field.field.group_relationship.lms_class-lms_class_activity_due.field_activity_due_at',
      'core.entity_form_display.group_relationship.lms_class-lms_class_activity_due.default',
      'core.entity_view_display.group_relationship.lms_class-lms_class_activity_due.default',
    ] as $config_id) {
      $path = DRUPAL_ROOT . '/../config/sync/' . $config_id . '.yml';
      $this->container->get('config.storage')->write($config_id, Yaml::decode(file_get_contents($path)));
    }
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('group_relation_type.manager')->clearCachedPluginMaps();
    $this->installEntitySchema('action');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('aculta_lms_lesson');
    $this->installEntitySchema('aculta_lms_activity');
  }

  /**
   * Tests that one LMS activity can have an independent deadline per class.
   */
  public function testDeadlinePersistsPerClassWithoutChangingActivity(): void {
    $owner = $this->createUser();
    $course = Group::create(['type' => 'lms_course', 'label' => 'Curso', 'uid' => $owner->id()]);
    $course->save();
    $lesson = LessonEntity::create([
      'course_id' => $course->id(),
      'title' => 'Aula',
      'published' => TRUE,
      'weight' => 0,
    ]);
    $lesson->save();
    $activity = ActivityEntity::create([
      'lesson_id' => $lesson->id(),
      'type' => 'true_false',
      'graded' => TRUE,
      'weight' => 0,
    ]);
    $activity->save();
    $unlinkedClass = Group::create(['type' => 'lms_class', 'label' => 'Turma sem vínculo', 'uid' => $owner->id()]);
    $unlinkedClass->save();
    $accessCheck = $this->container->get('aculta_lms.class_activity_deadline_access');
    $denied = $accessCheck->access($course, $unlinkedClass, $activity);
    self::assertTrue($denied->isForbidden());
    $cache = $this->container->get('cache.default');
    $cache->set('aculta_lms_test.unlinked_class_access', TRUE, Cache::PERMANENT, $denied->getCacheTags());
    self::assertNotFalse($cache->get('aculta_lms_test.unlinked_class_access'));
    $dashboardDenied = $this->container->get('aculta_lms.learning_dashboard')
      ->classDashboardAccess($unlinkedClass, $owner);
    self::assertTrue($dashboardDenied->isForbidden());
    $cache->set('aculta_lms_test.unlinked_class_dashboard', TRUE, Cache::PERMANENT, $dashboardDenied->getCacheTags());
    self::assertNotFalse($cache->get('aculta_lms_test.unlinked_class_dashboard'));
    $course->addRelationship($unlinkedClass, 'lms_classes');
    self::assertFalse($cache->get('aculta_lms_test.unlinked_class_access'), 'Adding the parent course invalidates the cached negative access result.');
    self::assertFalse($cache->get('aculta_lms_test.unlinked_class_dashboard'), 'Adding the parent course invalidates the cached class-dashboard denial.');

    $classA = Group::create(['type' => 'lms_class', 'label' => 'Turma A', 'uid' => $owner->id()]);
    $classA->save();
    $classB = Group::create(['type' => 'lms_class', 'label' => 'Turma B', 'uid' => $owner->id()]);
    $classB->save();
    $course->addRelationship($classA, 'lms_classes');
    $course->addRelationship($classB, 'lms_classes');

    $classA->addRelationship($activity, 'lms_class_activity_due', [
      'field_activity_due_at' => '2026-10-22T12:00:00',
    ]);
    $classB->addRelationship($activity, 'lms_class_activity_due', [
      'field_activity_due_at' => '2026-10-23T12:00:00',
    ]);

    $manager = $this->container->get('aculta_lms.class_activity_deadline_manager');
    self::assertNotNull($manager->courseForClass($classA), 'The class is linked to its parent course.');
    self::assertTrue($manager->activityBelongsToCourse($activity, $course), 'The activity is referenced by a published course lesson.');
    self::assertCount(1, $classA->getRelationshipsByEntity($activity, 'lms_class_activity_due'));
    $deadlineA = $manager->deadlineForClass($classA, $activity);
    $deadlineB = $manager->deadlineForClass($classB, $activity);
    self::assertNotNull($deadlineA);
    self::assertNotNull($deadlineB);
    self::assertSame('2026-10-22T12:00:00', $deadlineA->get('field_activity_due_at')->value);
    self::assertSame('2026-10-23T12:00:00', $deadlineB->get('field_activity_due_at')->value);
    $learner = $this->createUser();
    $learner->set('timezone', 'America/Sao_Paulo');
    $learner->save();
    $outsider = $this->createUser();
    $classA->addMember($learner);
    self::assertTrue($course->hasPermission('take course', $learner), 'A class membership grants take-course permission on its parent course.');
    self::assertFalse($classA->hasPermission('take course', $learner), 'Take-course permission is evaluated on the parent course, not on the class group.');
    self::assertSame($deadlineA->id(), $manager->deadlineForLearner($course, $activity, $learner)?->id());
    self::assertNull($manager->deadlineForLearner($course, $activity, $outsider));
    self::assertSame('true_false', $activity->get('type')->value);
  }

}
