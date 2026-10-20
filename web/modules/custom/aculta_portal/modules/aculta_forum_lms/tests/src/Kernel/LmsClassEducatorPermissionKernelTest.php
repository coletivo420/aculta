<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\aculta_forum_lms\Hook\LmsDiscussionModerationHooks;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
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

  use CommentTestTrait;

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

    NodeType::create([
      'type' => 'aculta_lms_topic',
      'name' => 'LMS discussion topic',
    ])->save();
    $this->addDefaultCommentField(
      'node',
      'aculta_lms_topic',
      'field_aculta_discussion',
      CommentingStatus::Open,
      'aculta_discussion_post',
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
    GroupRole::create([
      'id' => 'lms_class-test_member',
      'label' => 'Class test member',
      'group_type' => 'lms_class',
      'scope' => 'individual',
      'permissions' => [
        'view group',
        'view group_node:aculta_lms_topic entity',
      ],
    ])->save();
    $student = User::create([
      'name' => 'lms_class_student',
      'mail' => 'lms-class-student@example.invalid',
      'status' => 1,
    ]);
    $student->save();
    $class->addMember($student, ['group_roles' => ['lms_class-test_member']]);

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
    self::assertTrue($class->hasPermission('view group_node:aculta_lms_topic entity', $educator));
    self::assertTrue($class->hasPermission('view group_node:aculta_lms_topic entity', $student));
    self::assertTrue($class->access('view', $student));
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

}
