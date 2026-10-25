<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Proves the closed discussion field blocks and releases a student write.
 *
 * Dev.98 asked for closing "the field" to block a new student write. The
 * field that gates writes is the Comment status stored in the node's
 * field_aculta_discussion instance (CommentingStatus::Open is the only value
 * that allows writing); field_aculta_rejected is a boolean Comment marker
 * used by moderation and only gates access to itself. This test exercises the
 * real gate: the same class topic is closed and reopened between writes, and
 * the marker is shown not to influence the gate.
 *
 * The provider is instantiated directly because enabling the adapter module
 * pulls its full runtime dependency chain into the kernel.
 */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsClosedDiscussionFieldKernelTest extends KernelTestBase {

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
    'aculta_forum_lms_test',
  ];

  /**
   * The provider under test.
   */
  private LmsDiscussionContextProvider $provider;

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

    // The rejection marker exists in the same Comment bundle, but it is a
    // moderation annotation and never the topic's write gate.
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

    $this->container->get('config.installer')
      ->installDefaultConfig('module', 'aculta_forum_lms');
    self::assertInstanceOf(NodeType::class, NodeType::load('aculta_lms_topic'));

    $relationshipTypeStorage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationshipTypeStorage);
    foreach ([
      ['lms_course', 'lms_classes'],
      ['lms_course', 'group_node:aculta_lms_topic'],
      ['lms_class', 'group_node:aculta_lms_topic'],
    ] as [$groupTypeId, $pluginId]) {
      $groupType = $this->container->get('entity_type.manager')
        ->getStorage('group_type')
        ->load($groupTypeId);
      self::assertNotNull($groupType);
      $relationshipType = $relationshipTypeStorage->createFromPlugin($groupType, $pluginId);
      if ($relationshipTypeStorage->load($relationshipType->id()) === NULL) {
        $relationshipTypeStorage->save($relationshipType);
      }
    }

    foreach ([
      'group.role.lms_course-member',
      'group.role.lms_class-member',
    ] as $roleConfigName) {
      $this->createGroupRoleFromSync($roleConfigName);
    }

    Role::load('authenticated')
      ?->grantPermission('access content')
        ->grantPermission('post comments')
        ->save();

    $this->provider = new LmsDiscussionContextProvider(
      $this->container->get('entity_type.manager'),
    );
  }

  /**
   * Closing the discussion field blocks a class student until it reopens.
   */
  public function testClosingAndReopeningTheDiscussionField(): void {
    $owner = $this->createUser('closed_field_owner');
    $student = $this->createUser('closed_field_student');
    $outsider = $this->createUser('closed_field_outsider');
    [$topic] = $this->createClassTopic($owner, $student);

    $openContext = $this->provider->getContext($topic, $student);
    self::assertTrue($openContext->getReadAccess()->isAllowed());
    self::assertTrue(
      $openContext->getWriteAccess()->isAllowed(),
      'An open discussion accepts a new student write.',
    );
    self::assertTrue($openContext->getModerationAccess()->isForbidden());

    $outsiderContext = $this->provider->getContext($topic, $outsider);
    self::assertTrue($outsiderContext->getReadAccess()->isForbidden());
    self::assertTrue($outsiderContext->getWriteAccess()->isForbidden());

    $this->setDiscussionStatus($topic, CommentingStatus::Closed);
    self::assertSame(
      CommentingStatus::Closed->value,
      (int) $this->reload($topic)->get('field_aculta_discussion')->getValue()[0]['status'],
      'The closed status is persisted on the topic.',
    );

    $closedContext = $this->provider->getContext($this->reload($topic), $student);
    self::assertTrue(
      $closedContext->getReadAccess()->isAllowed(),
      'Closing the field blocks new writes without hiding the discussion.',
    );
    self::assertTrue(
      $closedContext->getWriteAccess()->isForbidden(),
      'Closing the discussion field blocks a new student write.',
    );
    self::assertTrue(
      $closedContext->getModerationAccess()->isForbidden(),
      'Closing the field does not grant moderation to the student.',
    );

    // The denial must invalidate when the field changes, otherwise a cached
    // "allowed" result could survive the close.
    self::assertContains(
      'node:' . $topic->id(),
      $closedContext->getWriteAccess()->getCacheTags(),
    );
    self::assertContains(
      'user.permissions',
      $closedContext->getWriteAccess()->getCacheContexts(),
    );

    $this->setDiscussionStatus($topic, CommentingStatus::Open);
    self::assertSame(
      CommentingStatus::Open->value,
      (int) $this->reload($topic)->get('field_aculta_discussion')->getValue()[0]['status'],
    );
    $reopenedContext = $this->provider->getContext($this->reload($topic), $student);
    self::assertTrue($reopenedContext->getReadAccess()->isAllowed());
    self::assertTrue(
      $reopenedContext->getWriteAccess()->isAllowed(),
      'Reopening the discussion field releases the student write again.',
    );
    self::assertTrue(
      $this->provider->getContext($this->reload($topic), $outsider)
        ->getWriteAccess()
        ->isForbidden(),
      'Reopening never grants access to a non-member.',
    );
  }

  /**
   * The rejection marker annotates a Comment and does not close the topic.
   */
  public function testRejectionMarkerDoesNotGateNewWrites(): void {
    $owner = $this->createUser('rejected_marker_owner');
    $student = $this->createUser('rejected_marker_student');
    [$topic] = $this->createClassTopic($owner, $student);
    $topicId = (int) $topic->id();

    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $topicId,
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'subject' => 'Rejected comment',
      'comment_body' => ['value' => 'Rejected comment', 'format' => 'plain_text'],
      'uid' => $owner->id(),
      'status' => 1,
      'field_aculta_rejected' => 1,
    ]);
    $comment->save();
    self::assertTrue((bool) $this->reload($comment)->get('field_aculta_rejected')->value);

    self::assertTrue(
      $this->provider->getContext($this->reload($topic), $student)
        ->getWriteAccess()
        ->isAllowed(),
      'Rejecting one Comment does not close the discussion for new writes.',
    );

    $this->setDiscussionStatus($topic, CommentingStatus::Closed);
    self::assertTrue(
      $this->provider->getContext($this->reload($topic), $student)
        ->getWriteAccess()
        ->isForbidden(),
      'The discussion field status, not the rejection marker, decides the write.',
    );
  }

  /**
   * Creates a course, a class inside it and an open topic in the class.
   *
   * @return array{\Drupal\node\Entity\Node}
   *   The persisted topic in the first position.
   */
  private function createClassTopic(User $owner, User $student): array {
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Closed field course',
      'uid' => $owner->id(),
    ]);
    $course->save();
    $class = Group::create([
      'type' => 'lms_class',
      'label' => 'Closed field class',
      'uid' => $owner->id(),
    ]);
    $class->save();
    $course->addRelationship($class, 'lms_classes')->save();
    $class->addMember($student);

    $topic = Node::create([
      'type' => 'aculta_lms_topic',
      'title' => 'Closed field topic',
      'status' => 1,
      'uid' => $owner->id(),
      'field_aculta_discussion' => ['status' => CommentingStatus::Open->value],
    ]);
    $topic->save();
    $class->addRelationship($topic, 'group_node:aculta_lms_topic')->save();

    return [$topic];
  }

  /**
   * Persists a new discussion status and reloads the topic.
   */
  private function setDiscussionStatus(Node $topic, CommentingStatus $status): void {
    $topic->set('field_aculta_discussion', ['status' => $status->value])->save();
  }

  /**
   * Reloads an entity so every assertion observes persisted state.
   */
  private function reload(Node|Comment $entity): Node|Comment {
    $reloaded = $this->container->get('entity_type.manager')
      ->getStorage($entity->getEntityTypeId())
      ->load($entity->id());
    self::assertInstanceOf($entity::class, $reloaded);
    return $reloaded;
  }

  /**
   * Creates a user account with the given name.
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

  /**
   * Imports an exported Group role from the site configuration.
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
