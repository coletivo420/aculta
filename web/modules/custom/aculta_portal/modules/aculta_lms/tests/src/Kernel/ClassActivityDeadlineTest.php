<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\group\Entity\Group;
use Drupal\lms\Entity\Activity;
use Drupal\lms\Entity\ActivityType;
use Drupal\lms\Entity\Lesson;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests class-specific activity deadlines with real Group entities.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class ClassActivityDeadlineTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'datetime',
    'user',
    'file',
    'image',
    'media',
    'options',
    'entity_print',
    'entity_usage',
    'aculta_image',
    'field',
    'filter',
    'flexible_permissions',
    'group',
    'lms',
    'lms_classes',
    'views',
    'options',
    'text',
    'user',
    'aculta_lms',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['group', 'lms', 'lms_classes']);
    foreach ([
      'group.relationship_type.lms_class-lms_class_activity_due',
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
    $this->installEntitySchema('lms_lesson');
    $this->installEntitySchema('lms_activity');
  }

  /**
   * Tests that one LMS activity can have an independent deadline per class.
   */
  public function testDeadlinePersistsPerClassWithoutChangingActivity(): void {
    $owner = $this->createUser();
    ActivityType::create(['id' => 'deadline_test', 'name' => 'Deadline test', 'pluginId' => 'no_answer'])->save();
    $activity = Activity::create([
      'type' => 'deadline_test',
      'name' => 'Atividade compartilhada',
      'status' => 1,
      'uid' => $owner->id(),
    ]);
    $activity->save();
    $lesson = Lesson::create(['name' => 'Aula', 'status' => 1, 'uid' => $owner->id()]);
    $lesson->get('activities')->appendItem(['target_id' => $activity->id()]);
    $lesson->save();
    $course = Group::create(['type' => 'lms_course', 'label' => 'Curso', 'uid' => $owner->id()]);
    $course->get('lessons')->appendItem(['target_id' => $lesson->id()]);
    $course->save();
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
    self::assertSame('Atividade compartilhada', $activity->label());
  }

}
