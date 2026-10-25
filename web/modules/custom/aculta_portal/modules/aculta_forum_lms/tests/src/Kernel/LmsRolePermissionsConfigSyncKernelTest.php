<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\comment\Entity\CommentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Proves the exported LMS roles and class settings survive Config Sync.
 *
 * The roles are imported through the real Config Sync path, which preserves
 * the exported dependencies and permission order instead of recalculating
 * them as a regular entity save would.
 */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsRolePermissionsConfigSyncKernelTest extends KernelTestBase {

  /**
   * The exported Group roles owned by the adapter.
   */
  private const ROLE_CONFIGS = [
    'group.role.lms_course-teacher',
    'group.role.lms_course-member',
    'group.role.lms_course-course_creator',
    'group.role.lms_class-member',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'comment',
    'views',
    'node',
    'options',
    'flexible_permissions',
    'group',
    'gnode',
    'lms',
    'lms_classes',
    // The exported roles declare these modules as dependencies, and Group
    // validates them when the configuration is imported through Config Sync.
    'file',
    'image',
    'media',
    'aculta_document_viewer_group',
    // Enabled so the exported module dependency is satisfiable; the fixture
    // supplies the external services the module declares.
    'aculta_forum_lms',
    'aculta_forum_lms_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'user', 'node', 'comment', 'group', 'lms', 'lms_classes']);

    CommentType::create([
      'id' => 'aculta_discussion_post',
      'label' => 'Discussion post',
      'target_entity_type_id' => 'node',
    ])->save();
    $this->container->get('comment.manager')->addBodyField('aculta_discussion_post');
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'body',
      'type' => 'text_with_summary',
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_aculta_discussion',
      'type' => 'comment',
      'settings' => ['comment_type' => 'aculta_discussion_post'],
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();
    $this->container->get('config.installer')
      ->installDefaultConfig('module', 'aculta_forum_lms');
    self::assertInstanceOf(NodeType::class, NodeType::load('aculta_lms_topic'));

    FieldStorageConfig::create([
      'entity_type' => 'comment',
      'field_name' => 'field_aculta_rejected',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'comment',
      'bundle' => 'aculta_discussion_post',
      'field_name' => 'field_aculta_rejected',
      'label' => 'Rejected',
    ])->save();

    $courseType = GroupType::load('lms_course');
    $classType = GroupType::load('lms_class');
    self::assertInstanceOf(GroupType::class, $courseType);
    self::assertInstanceOf(GroupType::class, $classType);
    $relationshipTypeStorage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
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
    foreach (self::ROLE_CONFIGS as $roleConfigName) {
      $this->importGroupRoleFromSync($roleConfigName);
    }
    Role::load('authenticated')
      ?->grantPermission('access content')
        ->grantPermission('post comments')
        ->save();

    // Mirror the exported class settings, including the conditional default.
    $settings = $this->readSyncConfig('lms_classes.settings');
    $this->config('lms_classes.settings')->setData($settings)->save();
  }

  /**
   * Every role read back from storage equals its exported YAML.
   */
  public function testImportedRolesMatchExportExactly(): void {
    foreach (self::ROLE_CONFIGS as $configName) {
      $export = $this->readSyncConfig($configName);
      $stored = $this->config($configName)->getRawData();
      self::assertEquals(
        $export,
        $stored,
        sprintf('%s keeps uuid, status, dependencies and permission order.', $configName),
      );
    }
  }

  /**
   * The exports keep the module and relationship dependencies they declare.
   */
  public function testRoleDependenciesMatchExport(): void {
    $courseRelationship = 'group.relationship_type.lms_course-3abb8caf7f7d526ecedee';
    $classRelationship = 'group.relationship_type.lms_class-3abb8caf7f7d526ecedeec';
    $expected = [
      'lms_course-teacher' => [
        'group_type' => 'lms_course',
        'relationship' => $courseRelationship,
        'global_role' => 'lms_teacher',
        'modules' => ['aculta_document_viewer_group', 'aculta_forum_lms'],
      ],
      'lms_course-member' => [
        'group_type' => 'lms_course',
        'relationship' => $courseRelationship,
        'global_role' => 'authenticated',
        'modules' => ['aculta_document_viewer_group', 'aculta_forum_lms'],
      ],
      'lms_course-course_creator' => [
        'group_type' => 'lms_course',
        'relationship' => $courseRelationship,
        'global_role' => NULL,
        'modules' => ['aculta_forum_lms'],
      ],
      'lms_class-member' => [
        'group_type' => 'lms_class',
        'relationship' => $classRelationship,
        'global_role' => 'authenticated',
        'modules' => ['aculta_forum_lms'],
      ],
    ];

    foreach ($expected as $id => $contract) {
      $role = GroupRole::load($id);
      self::assertInstanceOf(GroupRoleInterface::class, $role);
      $dependencies = $role->getDependencies();
      self::assertSame(
        $contract['global_role'],
        $role->get('global_role'),
        sprintf('%s keeps its global role.', $id),
      );
      self::assertContains('group.type.' . $contract['group_type'], $dependencies['config']);
      self::assertContains($contract['relationship'], $dependencies['config']);
      self::assertEqualsCanonicalizing($contract['modules'], $dependencies['module']);
      if ($contract['global_role'] !== NULL) {
        self::assertContains('user.role.' . $contract['global_role'], $dependencies['config']);
      }
      else {
        self::assertNotContains('user.role.authenticated', $dependencies['config']);
      }
    }
  }

  /**
   * Only educators hold the course permission the class mapping requires.
   */
  public function testRolePermissionsSeparateEducatorsFromStudents(): void {
    $teacher = GroupRole::load('lms_course-teacher');
    $creator = GroupRole::load('lms_course-course_creator');
    $courseMember = GroupRole::load('lms_course-member');
    $classMember = GroupRole::load('lms_class-member');
    foreach ([$teacher, $creator, $courseMember, $classMember] as $role) {
      self::assertInstanceOf(GroupRoleInterface::class, $role);
      self::assertFalse($role->isAdmin());
      self::assertTrue($role->hasPermission('view group_node:aculta_lms_topic entity'));
      self::assertFalse(
        $role->hasPermission('administer comments'),
        sprintf('%s never receives core comment administration.', $role->id()),
      );
    }

    self::assertTrue($teacher->hasPermission('grade students'));
    self::assertTrue($creator->hasPermission('grade students'));
    self::assertFalse($courseMember->hasPermission('grade students'));
    self::assertFalse($classMember->hasPermission('grade students'));

    self::assertTrue($courseMember->hasPermission('take course'));
    self::assertTrue($courseMember->hasPermission('leave group'));
    self::assertFalse($classMember->hasPermission('take course'));
    self::assertEqualsCanonicalizing(
      ['view group_node:aculta_lms_topic entity', 'view group'],
      $classMember->getPermissions(),
    );
  }

  /**
   * The class settings export keeps only the intended topic-view mapping.
   */
  public function testClassSettingsMatchExport(): void {
    $sync = $this->readSyncConfig('lms_classes.settings');
    self::assertSame(
      [
        'create_default_class' => FALSE,
        'class_name_pattern' => '',
        'class_permission_mappings' => [
          'view group_node:aculta_lms_topic entity' => 'grade students',
        ],
        'course_permission_mappings' => [],
      ],
      $sync,
    );
    self::assertEquals($sync, $this->config('lms_classes.settings')->getRawData());

    // The module install defaults differ only in the keys the export owns.
    $installPath = dirname(__DIR__, 7) . '/contrib/lms/modules/lms_classes/config/install/lms_classes.settings.yml';
    self::assertFileExists($installPath);
    $install = Yaml::parseFile($installPath);
    self::assertEqualsCanonicalizing(array_keys($sync), array_keys($install));
    self::assertTrue($install['create_default_class']);
    self::assertSame($sync['class_name_pattern'], $install['class_name_pattern']);
    self::assertSame([], $install['class_permission_mappings']);
    self::assertSame($sync['course_permission_mappings'], $install['course_permission_mappings']);
  }

  /**
   * The imported mapping grants class topic view only to course educators.
   */
  public function testClassTopicViewStaysWithCourseEducators(): void {
    $owner = $this->createUser('roles_owner');
    $educator = $this->createEducator('roles_educator');
    $student = $this->createUser('roles_student');
    $courseOnlyStudent = $this->createUser('roles_course_only_student');
    $outsider = $this->createEducator('roles_outsider');

    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Roles course',
      'uid' => $owner->id(),
    ]);
    $course->save();
    $course->addMember($educator);
    $course->addMember($student);
    $course->addMember($courseOnlyStudent);

    $class = Group::create([
      'type' => 'lms_class',
      'label' => 'Roles class',
      'uid' => $owner->id(),
    ]);
    $class->save();
    $course->addRelationship($class, 'lms_classes')->save();
    $class->addMember($student);

    self::assertTrue($course->hasPermission('grade students', $educator));
    self::assertFalse($course->hasPermission('grade students', $student));
    self::assertFalse($course->hasPermission('grade students', $courseOnlyStudent));
    self::assertFalse($course->hasPermission('grade students', $outsider));

    // The course Educator receives the class permission through the mapping.
    self::assertTrue($class->hasPermission('view group_node:aculta_lms_topic entity', $educator));
    // A class member receives it from the class role itself.
    self::assertTrue($class->hasPermission('view group_node:aculta_lms_topic entity', $student));
    // A course student without the course permission does not inherit it.
    self::assertFalse($class->hasPermission('view group_node:aculta_lms_topic entity', $courseOnlyStudent));
    self::assertFalse($class->hasPermission('view group_node:aculta_lms_topic entity', $outsider));
  }

  /**
   * Reads one configuration file from the site export.
   */
  private function readSyncConfig(string $configName): array {
    $path = dirname(__DIR__, 9) . '/config/sync/' . $configName . '.yml';
    self::assertFileExists($path);
    $data = Yaml::parseFile($path);
    self::assertIsArray($data);
    return $data;
  }

  /**
   * Imports one exported Group role through the Config Sync API.
   */
  private function importGroupRoleFromSync(string $configName): void {
    $data = $this->readSyncConfig($configName);
    $configFactory = $this->container->get('config.factory');
    $storage = $this->container->get('entity_type.manager')->getStorage('group_role');
    $storage->importCreate(
      $configName,
      $configFactory->getEditable($configName)->setData($data),
      $configFactory->getEditable($configName),
    );
    $role = GroupRole::load($data['id']);
    self::assertInstanceOf(GroupRoleInterface::class, $role);
  }

  /**
   * Creates an active account with the global LMS Educator role.
   */
  private function createEducator(string $name): User {
    $educator = $this->createUser($name);
    $educator->addRole('lms_teacher');
    $educator->save();
    return $educator;
  }

  /**
   * Creates an active account without the Educator role.
   */
  private function createUser(string $name): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    return $user;
  }

}
