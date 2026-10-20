<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies real Group Node access for a private LMS topic.
 */
#[Group('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsGroupNodeAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'entity',
    'flexible_permissions',
    'options',
    'group',
    'gnode',
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
    $this->installConfig(['system', 'user', 'node', 'group']);

    NodeType::create([
      'type' => 'aculta_lms_topic',
      'name' => 'LMS discussion topic',
    ])->save();

    $group_type_storage = $this->container->get('entity_type.manager')->getStorage('group_type');
    $group_type_storage->create([
      'id' => 'lms_course',
      'label' => 'Course',
      'creator_membership' => FALSE,
      'creator_wizard' => FALSE,
    ])->save();

    $relationship_type_storage = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationship_type_storage);
    $course_type = $group_type_storage->load('lms_course');
    $relationship_type_storage->save($relationship_type_storage->createFromPlugin($course_type, 'group_node:aculta_lms_topic'));

    $this->container->get('entity_type.manager')->getStorage('group_role')->create([
      'id' => 'lms_course-test_member',
      'label' => 'Course test member',
      'group_type' => 'lms_course',
      'scope' => 'individual',
      'permissions' => [
        'view group',
        'view group_node:aculta_lms_topic entity',
      ],
    ])->save();
  }

  /**
   * Only a course member with the Group Node permission can view the topic.
   */
  public function testGroupMemberCanViewAndExternalAccountCannot(): void {
    $member = User::create([
      'name' => 'lms_member',
      'mail' => 'lms-member@example.invalid',
      'status' => 1,
    ]);
    $member->save();
    $outsider = User::create([
      'name' => 'lms_outsider',
      'mail' => 'lms-outsider@example.invalid',
      'status' => 1,
    ]);
    $outsider->save();

    $topic = $this->container->get('entity_type.manager')->getStorage('node')->create([
      'type' => 'aculta_lms_topic',
      'title' => 'Private course discussion',
      'status' => 1,
      'uid' => $member->id(),
    ]);
    $topic->save();

    $course = $this->container->get('entity_type.manager')->getStorage('group')->create([
      'type' => 'lms_course',
      'label' => 'Private course',
      'uid' => $member->id(),
    ]);
    $course->save();
    $course->addRelationship($topic, 'group_node:aculta_lms_topic')->save();
    $course->addMember($member, ['group_roles' => ['lms_course-test_member']]);

    self::assertTrue($topic->access('view', $member, TRUE)->isAllowed());
    self::assertTrue($topic->access('view', $outsider, TRUE)->isForbidden());
  }

}
