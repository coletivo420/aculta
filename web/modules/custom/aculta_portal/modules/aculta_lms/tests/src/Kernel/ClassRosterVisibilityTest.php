<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\aculta_lms\Hook\CourseClassRelationHooks;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\PermissionScopeInterface;
use Drupal\aculta_lms\ClassRosterManager;
use Drupal\aculta_lms\Access\ClassPermissionCalculator;
use Drupal\aculta_lms\Plugin\Group\Relation\CourseClassRelation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Verifies educator-controlled roster visibility stays inside one class.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class ClassRosterVisibilityTest extends KernelTestBase {

  use AcultaLmsDependencyHarnessTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'system', 'domain', 'user', 'file', 'image', 'media', 'entity_usage', 'aculta_image', 'aculta_file_uploader',
    'field', 'filter', 'flexible_permissions', 'group', 'views', 'options',
    'text',
    'editor',
    'ckeditor5',
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
    $this->prepareAcultaLmsGroupTypes();
    $this->container->get('module_installer')->install(['aculta_lms'], FALSE);
    $this->prepareAcultaLmsClassRelationship();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);

    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    FieldStorageConfig::create([
      'field_name' => 'field_roster_visible',
      'entity_type' => 'group',
      'type' => 'boolean',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_roster_visible',
      'entity_type' => 'group',
      'bundle' => 'lms_class',
      'label' => 'Show roster',
      'default_value' => [['value' => 0]],
    ])->save();
    GroupRole::create([
      'id' => 'lms_class-member',
      'label' => 'Estudante',
      'group_type' => 'lms_class',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'global_role' => 'authenticated',
    ])->save();
  }

  /**
   * The stable class relationship ID resolves to the ACULTA implementation.
   */
  public function testClassRelationshipUsesAcultaPlugin(): void {
    // This Kernel installs aculta_lms after container compilation, so assert
    // the hook provider directly; production discovers it during compilation.
    $permissions = (new CourseClassRelationHooks())->permissions();
    self::assertArrayHasKey('administer lms', $permissions);

    $manager = $this->container->get('group_relation_type.manager');
    $definition = $manager->getDefinition('lms_classes');
    self::assertSame(CourseClassRelation::class, $definition->getClass());

    $plugin = $manager->createInstance('lms_classes', ['group_type_id' => 'lms_course']);
    self::assertSame(0, $plugin->getGroupCardinality());
    self::assertSame(1, $plugin->getEntityCardinality());
  }

  /**
   * Course and class memberships inherit the configured Group permissions.
   */
  public function testClassMembershipPermissionsAreCalculatedByAculta(): void {
    $owner = $this->createUser('permission-owner');
    $course = Group::create(['type' => 'lms_course', 'label' => 'Curso', 'uid' => $owner->id()]);
    $course->save();
    $class = $this->createClass('Turma', $owner);
    $course->addRelationship($class, 'lms_classes')->save();

    $learner = $this->createUser('permission-learner');
    $class->addMember($learner, ['group_roles' => ['lms_class-member']]);
    $calculator = $this->container->get(ClassPermissionCalculator::class);
    self::assertInstanceOf(ClassPermissionCalculator::class, $calculator);
    $learnerPermissions = $calculator->calculatePermissions($learner, PermissionScopeInterface::INDIVIDUAL_ID);
    self::assertTrue($learnerPermissions->getItem(PermissionScopeInterface::INDIVIDUAL_ID, $course->id())->hasPermission('take course'));

    GroupRole::create([
      'id' => 'lms_course-permission_teacher',
      'label' => 'Educador de teste',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students'],
    ])->save();
    $educator = $this->createUser('permission-educator');
    $course->addMember($educator, ['group_roles' => ['lms_course-permission_teacher']]);
    $educatorPermissions = $calculator->calculatePermissions($educator, PermissionScopeInterface::INDIVIDUAL_ID);
    self::assertTrue($educatorPermissions->getItem(PermissionScopeInterface::INDIVIDUAL_ID, $class->id())->hasPermission('view group_node:aculta_lms_topic entity'));
  }

  /**
   * Disabled rosters remain private; enabled rosters contain only active peers.
   */
  public function testRosterIsOptInAndRestrictedToTheViewersClass(): void {
    $owner = $this->createUser('owner');
    $viewer = $this->createUser('viewer');
    $peer = $this->createUser('peer');
    $blocked = $this->createUser('blocked', FALSE);
    $otherStudent = $this->createUser('other-class-student');

    $course = Group::create(['type' => 'lms_course', 'label' => 'Curso', 'uid' => $owner->id()]);
    $course->save();
    $class = $this->createClass('Turma A', $owner);
    $otherClass = $this->createClass('Turma B', $owner);
    $course->addRelationship($class, 'lms_classes')->save();
    $course->addRelationship($otherClass, 'lms_classes')->save();
    $class->addMember($viewer, ['group_roles' => ['lms_class-member']]);
    $class->addMember($peer, ['group_roles' => ['lms_class-member']]);
    $class->addMember($blocked, ['group_roles' => ['lms_class-member']]);
    $otherClass->addMember($otherStudent, ['group_roles' => ['lms_class-member']]);
    $otherClass->set('field_roster_visible', TRUE)->save();

    $manager = new ClassRosterManager($this->container->get('entity_type.manager'), $this->container->get('string_translation'), $this->container->get('aculta_user.author_card_builder'));
    self::assertFalse((bool) $class->get('field_roster_visible')->value, 'A new class hides the roster by default.');
    $hidden = $manager->build($course, $viewer);
    self::assertArrayNotHasKey('classes', $hidden, 'Roster visibility defaults to hidden.');

    $class->set('field_roster_visible', TRUE)->save();
    $groupStorage = $this->container->get('entity_type.manager')->getStorage('group');
    $groupStorage->resetCache([$class->id(), $course->id()]);
    $this->container->get('entity_type.manager')->getStorage('group_relationship')->resetCache();
    $class = $groupStorage->load($class->id());
    $course = $groupStorage->load($course->id());
    self::assertTrue((bool) $class->get('field_roster_visible')->value);
    $classMemberships = $this->container->get('entity_type.manager')->getStorage('group_relationship')->loadByProperties([
      'gid' => $class->id(),
      'plugin_id' => 'group_membership',
    ]);
    $viewerMemberships = array_filter($classMemberships, static fn ($membership): bool => (string) $membership->getEntityId() === (string) $viewer->id());
    self::assertCount(1, $viewerMemberships);
    $viewerMembership = reset($viewerMemberships);
    self::assertContains('lms_class-member', array_column($viewerMembership->get('group_roles')->getValue(), 'target_id'));
    self::assertSame((string) $viewer->id(), (string) $viewerMembership->getEntity()->id());
    self::assertTrue($viewerMembership->getEntity()->isActive());
    $visible = $manager->build($course, $viewer);
    self::assertCount(1, $visible['classes'], 'A student sees only classes where they are enrolled.');
    self::assertSame('Turma A', $visible['classes'][0]['title']['#value']);
    $items = $visible['classes'][0]['members']['#items'];
    self::assertSame('aculta_user_author_card', $items[0]['#theme']);
    self::assertSame('profile_compact', $items[0]['#variant']);
    self::assertSame('sm', $items[0]['#size']);
    $names = array_map(static fn (array $item): string => (string) $item['#display_name'], $items);
    self::assertEqualsCanonicalizing(['peer', 'viewer'], $names);
    self::assertNotContains('blocked', $names);
    self::assertNotContains('other-class-student', $names);
    self::assertContains('group.classes:' . $course->id(), $visible['#cache']['tags']);
    self::assertContains('group_relationship_list:plugin:group_membership:group:' . $class->id(), $visible['#cache']['tags']);

    $educator = $this->createUser('educator');
    self::assertArrayNotHasKey('classes', $manager->build($course, $educator), 'A course educator without a student membership does not see the student roster.');
  }

  /**
   * Creates an active or blocked account for the fixture.
   */
  private function createUser(string $name, bool $active = TRUE): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => (int) $active,
    ]);
    $user->save();
    return $user;
  }

  /**
   * Creates a class group with its roster disabled.
   */
  private function createClass(string $label, User $owner): Group {
    $class = Group::create([
      'type' => 'lms_class',
      'label' => $label,
      'uid' => $owner->id(),
    ]);
    $class->save();
    return $class;
  }

}
