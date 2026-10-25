<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\PermissionScopeInterface;
use Drupal\aculta_lms\ClassRosterManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies educator-controlled roster visibility stays inside one class.
 */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class ClassRosterVisibilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'image', 'media', 'entity_usage', 'aculta_image',
    'field', 'filter', 'flexible_permissions', 'group', 'views', 'options',
    'text', 'lms', 'lms_classes', 'aculta_lms',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['group', 'lms', 'lms_classes']);
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

    $manager = new ClassRosterManager($this->container->get('entity_type.manager'), $this->container->get('string_translation'));
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
    $names = array_map(static fn (array $item): string => $item['#plain_text'], $visible['classes'][0]['members']['#items']);
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
