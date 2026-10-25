<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\aculta_forum_lms\Access\LmsForumModerationManager;
use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\aculta_forum_lms\Hook\LmsCommentModerationOperationsHooks;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
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
 * Proves who may publish and reject an LMS Comment.
 *
 * The matrix crosses the three actors of a course (Educator, student, and an
 * authenticated outsider that also holds the global Educator role) with the
 * two moderation decisions. Only the context Educator may change state.
 */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsCommentModerationMatrixKernelTest extends KernelTestBase {

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
   * The class Group that owns the discussion.
   */
  private Group $class;

  /**
   * The course Group that owns the class.
   */
  private Group $course;

  /**
   * The LMS topic host of the Comments.
   */
  private Node $topic;

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
    foreach ([
      'group.role.lms_course-teacher',
      'group.role.lms_course-member',
      'group.role.lms_course-course_creator',
      'group.role.lms_class-member',
    ] as $roleConfigName) {
      $this->createGroupRoleFromSync($roleConfigName);
    }
    Role::load('authenticated')
      ?->grantPermission('access content')
        ->grantPermission('post comments')
        ->save();

    $syncPath = dirname(__DIR__, 9) . '/config/sync/lms_classes.settings.yml';
    self::assertFileExists($syncPath);
    $syncConfig = Yaml::parseFile($syncPath);
    $this->config('lms_classes.settings')
      ->set('class_permission_mappings', $syncConfig['class_permission_mappings'])
      ->save();
  }

  /**
   * Only the context Educator publishes and rejects, in any state.
   */
  public function testPublicationAndRejectionMatrix(): void {
    $owner = $this->createUser('matrix_owner');
    $educator = $this->createEducator('matrix_educator');
    $student = $this->createUser('matrix_student');
    // The outsider also holds the global Educator role, proving the decision
    // depends on contextual membership and not on the global role alone.
    $outsider = $this->createEducator('matrix_outsider');

    $this->course = Group::create([
      'type' => 'lms_course',
      'label' => 'Matrix course',
      'uid' => $owner->id(),
    ]);
    $this->course->save();
    $this->course->addMember($educator);
    $this->course->addMember($student);

    $this->class = Group::create([
      'type' => 'lms_class',
      'label' => 'Matrix class',
      'uid' => $owner->id(),
    ]);
    $this->class->save();
    $this->course->addRelationship($this->class, 'lms_classes')->save();
    $this->class->addMember($student);

    $this->topic = Node::create([
      'type' => 'aculta_lms_topic',
      'title' => 'Matrix topic',
      'status' => 1,
      'uid' => $owner->id(),
      'field_aculta_discussion' => ['status' => CommentingStatus::Open->value],
    ]);
    $this->topic->save();
    $this->class->addRelationship($this->topic, 'group_node:aculta_lms_topic')->save();

    self::assertTrue($this->course->hasPermission('grade students', $educator));
    self::assertFalse($this->course->hasPermission('grade students', $student));
    self::assertFalse($this->course->hasPermission('grade students', $outsider));
    self::assertTrue($this->class->hasPermission('view group_node:aculta_lms_topic entity', $educator));

    $manager = new LmsForumModerationManager(
      $this->container->get('entity_type.manager'),
      new LmsDiscussionContextProvider($this->container->get('entity_type.manager')),
    );

    // Publication: a pending Comment becomes published only for the Educator.
    $pending = $this->createComment($student, 'Pending reply', FALSE);
    $this->assertDecisionMatrix($manager, $pending, 'approve', $educator, $student, $outsider);
    self::assertTrue(
      $manager->applyModerationDecision($this->class, $pending, 'approve', $educator),
      'The context Educator publishes the pending reply.',
    );
    $pending = $this->reloadComment($pending);
    self::assertTrue($pending->isPublished());
    self::assertSame(0, (int) $pending->get('field_aculta_rejected')->value);

    // Rejection: a published Comment is unpublished only for the Educator.
    $published = $this->createComment($student, 'Published reply', TRUE);
    $this->assertDecisionMatrix($manager, $published, 'reject', $educator, $student, $outsider);
    self::assertTrue(
      $manager->applyModerationDecision($this->class, $published, 'reject', $educator),
      'The context Educator rejects the published reply.',
    );
    $published = $this->reloadComment($published);
    self::assertFalse($published->isPublished());
    self::assertSame(1, (int) $published->get('field_aculta_rejected')->value);

    // The student and the outsider never changed any state.
    $untouchedPending = $this->createComment($student, 'Untouched pending', FALSE);
    self::assertFalse($manager->applyModerationDecision($this->class, $untouchedPending, 'approve', $student));
    self::assertFalse($manager->applyModerationDecision($this->class, $untouchedPending, 'approve', $outsider));
    $untouchedPending = $this->reloadComment($untouchedPending);
    self::assertFalse($untouchedPending->isPublished());
    self::assertSame(0, (int) $untouchedPending->get('field_aculta_rejected')->value);

    $untouchedPublished = $this->createComment($student, 'Untouched published', TRUE);
    self::assertFalse($manager->applyModerationDecision($this->class, $untouchedPublished, 'reject', $student));
    self::assertFalse($manager->applyModerationDecision($this->class, $untouchedPublished, 'reject', $outsider));
    $untouchedPublished = $this->reloadComment($untouchedPublished);
    self::assertTrue($untouchedPublished->isPublished());
    self::assertSame(0, (int) $untouchedPublished->get('field_aculta_rejected')->value);
  }

  /**
   * Asserts the two decisions and the operations per actor.
   */
  private function assertDecisionMatrix(
    LmsForumModerationManager $manager,
    Comment $comment,
    string $decision,
    User $educator,
    User $student,
    User $outsider,
  ): void {
    self::assertTrue(
      $manager->commentModerationAccess($this->class, $comment, $decision, $educator)->isAllowed(),
      sprintf('The Educator may %s the Comment.', $decision),
    );
    foreach ([$student, $outsider] as $denied) {
      self::assertTrue(
        $manager->commentModerationAccess($this->class, $comment, $decision, $denied)->isForbidden(),
        sprintf('%s may not %s the Comment.', $denied->getAccountName(), $decision),
      );
    }

    $educatorOperations = (new LmsCommentModerationOperationsHooks($manager, $educator))
      ->entityOperation($comment, new CacheableMetadata());
    self::assertContains('aculta_lms_' . $decision, array_keys($educatorOperations));
    foreach ([$student, $outsider] as $denied) {
      self::assertSame(
        [],
        (new LmsCommentModerationOperationsHooks($manager, $denied))
          ->entityOperation($comment, new CacheableMetadata()),
        sprintf('%s receives no moderation operation.', $denied->getAccountName()),
      );
    }
  }

  /**
   * Creates one discussion Comment with the requested publication state.
   */
  private function createComment(User $author, string $subject, bool $published): Comment {
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $this->topic->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'subject' => $subject,
      'comment_body' => ['value' => $subject, 'format' => 'plain_text'],
      'uid' => $author->id(),
      'status' => $published ? 1 : 0,
      'field_aculta_rejected' => 0,
    ]);
    $comment->save();
    return $comment;
  }

  /**
   * Reloads the Comment so assertions observe persisted state.
   */
  private function reloadComment(Comment $comment): Comment {
    $reloaded = $this->container->get('entity_type.manager')
      ->getStorage('comment')
      ->loadUnchanged($comment->id());
    self::assertInstanceOf(Comment::class, $reloaded);
    return $reloaded;
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

  /**
   * Imports one exported Group role from the site configuration.
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
