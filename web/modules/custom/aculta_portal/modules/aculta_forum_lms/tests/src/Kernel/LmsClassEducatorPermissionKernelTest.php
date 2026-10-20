<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\aculta_forum_lms\Hook\LmsDiscussionModerationHooks;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
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
    'comment',
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
    self::assertSame(
      'aculta_discussion_post',
      FieldConfig::load('node.aculta_lms_topic.field_aculta_discussion')?->getSetting('comment_type'),
    );
    $groupRelationshipStorage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
    self::assertNotNull($groupRelationshipStorage->load('lms_course-3abb8caf7f7d526ecedee'));
    self::assertNotNull($groupRelationshipStorage->load('lms_class-3abb8caf7f7d526ecedeec'));
    self::assertSame(
      'comment_default',
      $this->config('core.entity_view_display.node.aculta_lms_topic.full')
        ->get('content.field_aculta_discussion.type'),
    );
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
    foreach ([
      'group.role.lms_course-teacher',
      'group.role.lms_course-member',
      'group.role.lms_course-course_creator',
      'group.role.lms_class-member',
    ] as $roleConfigName) {
      $this->createGroupRoleFromSync($roleConfigName);
    }

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
    Role::load('authenticated')
      ?->grantPermission('access content')
        ->grantPermission('post comments')
        ->save();
    $outsider = User::create([
      'name' => 'lms_class_outsider',
      'mail' => 'lms-class-outsider@example.invalid',
      'status' => 1,
    ]);
    $outsider->save();
    $outsider->addRole('lms_teacher');
    $outsider->save();
    $classOwner = User::create([
      'name' => 'lms_class_owner',
      'mail' => 'lms-class-owner@example.invalid',
      'status' => 1,
    ]);
    $classOwner->save();

    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Educator permission course',
      'uid' => $classOwner->id(),
    ]);
    $course->save();
    // The course role is assigned through the existing global-role mapping.
    $course->addMember($educator);

    $class = Group::create([
      'type' => 'lms_class',
      'label' => 'Course class',
      // The Educator is not the class owner or a direct class member. Access
      // must come from their course membership and contextual permissions.
      'uid' => $classOwner->id(),
    ]);
    $class->save();
    $course->addRelationship($class, 'lms_classes')->save();
    $student = User::create([
      'name' => 'lms_class_student',
      'mail' => 'lms-class-student@example.invalid',
      'status' => 1,
    ]);
    $student->save();
    $course->addMember($student);
    $class->addMember($student);

    $topic = $this->container->get('entity_type.manager')->getStorage('node')->create([
      'type' => 'aculta_lms_topic',
      'title' => 'Private class topic',
      'status' => 1,
      'uid' => $educator->id(),
      'field_aculta_discussion' => ['status' => CommentingStatus::Open->value],
    ]);
    $topic->save();
    $class->addRelationship($topic, 'group_node:aculta_lms_topic')->save();

    self::assertTrue($course->hasPermission('grade students', $educator));
    self::assertTrue($course->hasPermission('view group_node:aculta_lms_topic entity', $educator));
    self::assertTrue($course->hasPermission('view group_node:aculta_lms_topic entity', $student));
    self::assertTrue($class->hasPermission('view group_node:aculta_lms_topic entity', $educator));
    self::assertTrue($class->hasPermission('view group_node:aculta_lms_topic entity', $student));
    self::assertTrue($class->access('view', $student));
    self::assertTrue($class->access('view', $educator));
    self::assertTrue($topic->access('view', $educator));
    self::assertTrue($topic->access('view', $student));
    self::assertFalse($class->hasPermission('view group_node:aculta_lms_topic entity', $outsider));
    self::assertFalse($outsider->hasPermission('view group_node:aculta_lms_topic entity'));

    $provider = new LmsDiscussionContextProvider($this->container->get('entity_type.manager'));
    $educatorContext = $provider->getContext($topic, $educator);
    $studentContext = $provider->getContext($topic, $student);
    $outsiderContext = $provider->getContext($topic, $outsider);
    self::assertTrue($educatorContext->getReadAccess()->isAllowed());
    self::assertTrue($educatorContext->getModerationAccess()->isAllowed());
    self::assertTrue($studentContext->getReadAccess()->isAllowed());
    self::assertTrue($studentContext->getWriteAccess()->isAllowed());
    self::assertTrue($studentContext->getModerationAccess()->isForbidden());
    self::assertTrue($outsiderContext->getReadAccess()->isForbidden());
    self::assertContains('user', $studentContext->getCacheContexts());
    self::assertContains('user.permissions', $studentContext->getCacheContexts());
    self::assertContains('user.group_permissions', $educatorContext->getCacheContexts());
    self::assertContains('node:' . $topic->id(), $studentContext->getCacheTags());

    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'subject' => 'Class reply',
      'comment_body' => ['value' => 'Student reply', 'format' => 'plain_text'],
      'uid' => $student->id(),
      'status' => 1,
    ]);
    $comment->save();
    self::assertSame($topic->id(), $comment->getCommentedEntityId());
    self::assertSame('Student reply', $comment->get('comment_body')->value);

    $resolver = new DiscussionContextResolver([$provider]);
    $moderationHooks = new LmsDiscussionModerationHooks($resolver);
    $rejectedItems = $comment->get('field_aculta_rejected');
    $rejectedDefinition = $rejectedItems->getFieldDefinition();
    self::assertTrue($moderationHooks->entityFieldAccess('edit', $rejectedDefinition, $educator, $rejectedItems)->isAllowed());
    self::assertTrue($moderationHooks->entityFieldAccess('edit', $rejectedDefinition, $student, $rejectedItems)->isForbidden());

    $topic->set('field_aculta_discussion', ['status' => CommentingStatus::Closed->value])->save();
    self::assertTrue($provider->getContext($topic, $student)->getWriteAccess()->isForbidden());
  }

  /**
   * Creates one Group role from the current Config Sync definition.
   */
  private function createGroupRoleFromSync(string $configName): void {
    $configPath = dirname(__DIR__, 9) . '/config/sync/' . $configName . '.yml';
    self::assertFileExists($configPath);
    $config = Yaml::parseFile($configPath);
    $role = array_intersect_key($config, array_flip([
      'id',
      'label',
      'weight',
      'admin',
      'scope',
      'global_role',
      'group_type',
      'permissions',
    ]));
    GroupRole::create($role)->save();
  }

}
