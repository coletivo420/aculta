<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies course Educator permissions are inherited by LMS classes.
 */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsClassEducatorPermissionKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'views',
    'node',
    'options',
    'flexible_permissions',
    'group',
    'gnode',
    'lms',
    'lms_classes',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'node', 'group', 'lms', 'lms_classes']);

    NodeType::create([
      'type' => 'aculta_lms_topic',
      'name' => 'LMS discussion topic',
    ])->save();

    $courseType = GroupType::load('lms_course');
    self::assertInstanceOf(GroupType::class, $courseType);
    $classType = GroupType::load('lms_class');
    self::assertInstanceOf(GroupType::class, $classType);

    $relationshipTypeStorage = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationshipTypeStorage);
    foreach ([
      [$courseType, 'lms_classes'],
      [$courseType, 'group_node:aculta_lms_topic'],
      [$classType, 'group_node:aculta_lms_topic'],
    ] as [$groupType, $pluginId]) {
      $relationshipType = $relationshipTypeStorage->createFromPlugin($groupType, $pluginId);
      if ($relationshipTypeStorage->load($relationshipType->id()) === NULL) {
        $relationshipTypeStorage->save($relationshipType);
      }
    }

    Role::create([
      'id' => 'lms_teacher',
      'label' => 'LMS Educator',
    ])->save();
    GroupRole::create([
      'id' => 'lms_course-test_teacher',
      'label' => 'Course test educator',
      'group_type' => 'lms_course',
      'scope' => 'insider',
      'global_role' => 'lms_teacher',
      'permissions' => ['grade students', 'view group'],
    ])->save();

    $syncPath = dirname(__DIR__, 9) . '/config/sync/lms_classes.settings.yml';
    self::assertFileExists($syncPath);
    $syncConfig = Yaml::parseFile($syncPath);
    $this->config('lms_classes.settings')
      ->set('class_permission_mappings', $syncConfig['class_permission_mappings'])
      ->save();
  }

  /**
   * Course permission grants the topic permission in its class, not globally.
   */
  public function testCourseEducatorPermissionIsInheritedByClassOnly(): void {
    $educator = User::create([
      'name' => 'lms_class_educator',
      'mail' => 'lms-class-educator@example.invalid',
      'status' => 1,
    ]);
    $educator->save();
    $educator->addRole('lms_teacher');
    $educator->save();
    $outsider = User::create([
      'name' => 'lms_class_outsider',
      'mail' => 'lms-class-outsider@example.invalid',
      'status' => 1,
    ]);
    $outsider->save();
    $outsider->addRole('lms_teacher');
    $outsider->save();

    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Educator permission course',
      'uid' => $educator->id(),
    ]);
    $course->save();
    $course->addMember($educator);

    $class = Group::create([
      'type' => 'lms_class',
      'label' => 'Course class',
      'uid' => $educator->id(),
    ]);
    $class->save();
    $course->addRelationship($class, 'lms_classes')->save();

    self::assertTrue($course->hasPermission('grade students', $educator));
    self::assertTrue($class->hasPermission('view group_node:aculta_lms_topic entity', $educator));
    self::assertFalse($class->hasPermission('view group_node:aculta_lms_topic entity', $outsider));
    self::assertFalse($outsider->hasPermission('view group_node:aculta_lms_topic entity'));
  }

}
