<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\CommentType;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
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
 * Proves the LMS discussion context fails closed outside its exact Group.
 *
 * The provider is instantiated directly because enabling the adapter module
 * pulls its full runtime dependency chain into the kernel.
 */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsDiscussionContextIsolationKernelTest extends KernelTestBase {

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
    $this->container->get('config.installer')
      ->installDefaultConfig('module', 'aculta_forum_lms');
    self::assertInstanceOf(NodeType::class, NodeType::load('aculta_lms_topic'));

    NodeType::create([
      'type' => 'aculta_isolation_post',
      'name' => 'Unrelated content',
    ])->save();

    // A container Group outside the LMS bundles, used to prove the provider
    // does not trust an arbitrary group_node relationship.
    GroupType::create([
      'id' => 'aculta_isolation_group',
      'label' => 'Isolation container',
      'creator_membership' => FALSE,
      'creator_wizard' => FALSE,
    ])->save();

    $courseType = GroupType::load('lms_course');
    $classType = GroupType::load('lms_class');
    $containerType = GroupType::load('aculta_isolation_group');
    self::assertInstanceOf(GroupType::class, $courseType);
    self::assertInstanceOf(GroupType::class, $classType);
    self::assertInstanceOf(GroupType::class, $containerType);

    $relationshipTypeStorage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationshipTypeStorage);
    foreach ([
      [$courseType, 'lms_classes'],
      [$courseType, 'group_node:aculta_lms_topic'],
      [$classType, 'group_node:aculta_lms_topic'],
      [$containerType, 'group_node:aculta_lms_topic'],
      [$containerType, 'group_membership'],
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
   * Only persisted LMS topic nodes are accepted, with a stable failure.
   */
  public function testProviderRejectsUnsupportedAndUnsavedEntities(): void {
    $account = $this->createUser('isolation_unsupported_account');

    $otherNode = Node::create([
      'type' => 'aculta_isolation_post',
      'title' => 'Unrelated content',
      'status' => 1,
      'uid' => $account->id(),
    ]);
    $otherNode->save();
    self::assertFalse($this->provider->supports($otherNode));
    $this->assertContextRejected(
      $otherNode,
      $account,
      'LMS discussion context requires a persisted aculta_lms_topic node with a UUID.',
    );

    $newTopic = Node::create([
      'type' => 'aculta_lms_topic',
      'title' => 'Unsaved topic',
      'uid' => $account->id(),
    ]);
    self::assertTrue($this->provider->supports($newTopic));
    $this->assertContextRejected(
      $newTopic,
      $account,
      'LMS discussion context requires a persisted aculta_lms_topic node with a UUID.',
    );

    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Isolation course',
      'uid' => $account->id(),
    ]);
    $course->save();
    self::assertFalse($this->provider->supports($course));
  }

  /**
   * A topic without exactly one course relationship is never public.
   */
  public function testTopicWithoutUniqueCourseRelationshipIsForbidden(): void {
    $owner = $this->createUser('isolation_owner');
    $member = $this->createUser('isolation_member');
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Isolation course',
      'uid' => $owner->id(),
    ]);
    $course->save();
    $course->addMember($member);
    $secondCourse = Group::create([
      'type' => 'lms_course',
      'label' => 'Second isolation course',
      'uid' => $owner->id(),
    ]);
    $secondCourse->save();
    $secondCourse->addMember($member);

    $topic = $this->createTopic($owner, 'Ungrouped topic');

    self::assertTrue(
      $this->provider->getGroupRelationshipAccess($topic)->isForbidden(),
      'A persisted topic without a Group relationship is forbidden.',
    );
    $this->assertContextForbidden($topic, $member);

    $course->addRelationship($topic, 'group_node:aculta_lms_topic')->save();
    self::assertTrue($this->provider->getGroupRelationshipAccess($topic)->isNeutral());
    $this->assertContextAllowed($topic, $member);

    // A second relationship makes the context ambiguous, so it fails closed.
    $secondCourse->addRelationship($topic, 'group_node:aculta_lms_topic')->save();
    $topic = Node::load($topic->id());
    self::assertCount(
      2,
      $this->container->get('entity_type.manager')
        ->getStorage('group_relationship')
        ->loadByEntity($topic, 'group_node:aculta_lms_topic'),
    );
    self::assertTrue($this->provider->getGroupRelationshipAccess($topic)->isForbidden());
    $this->assertContextForbidden($topic, $member);
  }

  /**
   * A topic linked to a non-LMS Group bundle is not an LMS discussion.
   */
  public function testTopicInUnsupportedGroupBundleIsForbidden(): void {
    $owner = $this->createUser('isolation_container_owner');
    $member = $this->createUser('isolation_container_member');
    $container = Group::create([
      'type' => 'aculta_isolation_group',
      'label' => 'Unsupported container',
      'uid' => $owner->id(),
    ]);
    $container->save();
    $container->addMember($member);

    $topic = $this->createTopic($owner, 'Topic in unsupported group');
    $container->addRelationship($topic, 'group_node:aculta_lms_topic')->save();

    self::assertTrue($this->provider->getGroupRelationshipAccess($topic)->isForbidden());
    $this->assertContextForbidden($topic, $member);
  }

  /**
   * A class topic needs exactly one parent course before it is readable.
   */
  public function testClassTopicWithoutUniqueParentCourseIsForbidden(): void {
    $owner = $this->createUser('isolation_class_owner');
    $member = $this->createUser('isolation_class_member');
    $class = Group::create([
      'type' => 'lms_class',
      'label' => 'Orphan class',
      'uid' => $owner->id(),
    ]);
    $class->save();
    $class->addMember($member);

    $topic = $this->createTopic($owner, 'Topic in orphan class');
    $class->addRelationship($topic, 'group_node:aculta_lms_topic')->save();

    self::assertTrue(
      $this->provider->getGroupRelationshipAccess($topic)->isForbidden(),
      'A class without a parent course cannot expose its topics.',
    );
    $this->assertContextForbidden($topic, $member);

    $firstCourse = Group::create([
      'type' => 'lms_course',
      'label' => 'First parent course',
      'uid' => $owner->id(),
    ]);
    $firstCourse->save();
    $firstCourse->addRelationship($class, 'lms_classes')->save();
    self::assertFalse($this->provider->getGroupRelationshipAccess($topic)->isForbidden());
    $this->assertContextAllowed($topic, $member);

    // Two parent courses also make the class chain ambiguous.
    $secondCourse = Group::create([
      'type' => 'lms_course',
      'label' => 'Second parent course',
      'uid' => $owner->id(),
    ]);
    $secondCourse->save();
    $secondCourse->addRelationship($class, 'lms_classes')->save();
    self::assertTrue($this->provider->getGroupRelationshipAccess($topic)->isForbidden());
    $this->assertContextForbidden($topic, $member);
  }

  /**
   * A course member reads and replies, while a non-member sees nothing.
   */
  public function testCourseMemberContextAndPolicies(): void {
    $owner = $this->createUser('isolation_course_owner');
    $member = $this->createUser('isolation_course_member');
    $outsider = $this->createUser('isolation_course_outsider');
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Context course',
      'uid' => $owner->id(),
    ]);
    $course->save();
    $course->addMember($member);

    $topic = $this->createTopic($owner, 'Open course topic');
    $course->addRelationship($topic, 'group_node:aculta_lms_topic')->save();

    $memberContext = $this->provider->getContext($topic, $member);
    self::assertSame('lms', $memberContext->getType());
    self::assertSame('lms:node:' . $topic->uuid(), $memberContext->getStableId());
    self::assertSame($topic->id(), $memberContext->getHostEntity()->id());
    self::assertSame('group_members_only', $memberContext->getVisibilityPolicy());
    self::assertSame('private_search_only', $memberContext->getDiscoveryPolicy());
    self::assertSame('membership_revalidated', $memberContext->getNotificationPolicy());
    self::assertTrue($memberContext->getReadAccess()->isAllowed());
    self::assertTrue($memberContext->getWriteAccess()->isAllowed());
    self::assertTrue($memberContext->getModerationAccess()->isForbidden());
    self::assertSame(
      $topic->toUrl('canonical')->toString(),
      $memberContext->getCanonicalUrl()->toString(),
    );

    $outsiderContext = $this->provider->getContext($topic, $outsider);
    self::assertTrue($outsiderContext->getReadAccess()->isForbidden());
    self::assertTrue($outsiderContext->getWriteAccess()->isForbidden());
    self::assertTrue($outsiderContext->getModerationAccess()->isForbidden());

    $closedTopic = $this->createTopic($owner, 'Closed course topic', CommentingStatus::Closed->value);
    $course->addRelationship($closedTopic, 'group_node:aculta_lms_topic')->save();
    $closedContext = $this->provider->getContext($closedTopic, $member);
    self::assertTrue($closedContext->getReadAccess()->isAllowed());
    self::assertTrue(
      $closedContext->getWriteAccess()->isForbidden(),
      'A closed discussion remains readable but no longer accepts replies.',
    );
    self::assertTrue($this->provider->getContext($closedTopic, $outsider)->getReadAccess()->isForbidden());
  }

  /**
   * A class member is confined to their own class, not the whole course.
   */
  public function testClassMemberIsolatedFromOtherClasses(): void {
    $owner = $this->createUser('isolation_classes_owner');
    $member = $this->createUser('isolation_classes_member');
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Classes course',
      'uid' => $owner->id(),
    ]);
    $course->save();
    $firstClass = Group::create([
      'type' => 'lms_class',
      'label' => 'First class',
      'uid' => $owner->id(),
    ]);
    $firstClass->save();
    $course->addRelationship($firstClass, 'lms_classes')->save();
    $secondClass = Group::create([
      'type' => 'lms_class',
      'label' => 'Second class',
      'uid' => $owner->id(),
    ]);
    $secondClass->save();
    $course->addRelationship($secondClass, 'lms_classes')->save();
    $firstClass->addMember($member);

    $firstTopic = $this->createTopic($owner, 'First class topic');
    $firstClass->addRelationship($firstTopic, 'group_node:aculta_lms_topic')->save();
    $secondTopic = $this->createTopic($owner, 'Second class topic');
    $secondClass->addRelationship($secondTopic, 'group_node:aculta_lms_topic')->save();

    $firstContext = $this->provider->getContext($firstTopic, $member);
    self::assertTrue($firstContext->getReadAccess()->isAllowed());
    self::assertTrue($firstContext->getWriteAccess()->isAllowed());
    self::assertTrue($firstContext->getModerationAccess()->isForbidden());

    $secondContext = $this->provider->getContext($secondTopic, $member);
    self::assertTrue(
      $secondContext->getReadAccess()->isForbidden(),
      'Membership in one class does not expose another class of the same course.',
    );
    self::assertTrue($secondContext->getWriteAccess()->isForbidden());
  }

  /**
   * Creates a persisted discussion topic with the requested comment status.
   */
  private function createTopic(User $owner, string $title, int $commentStatus = CommentingStatus::Open->value): Node {
    $topic = Node::create([
      'type' => 'aculta_lms_topic',
      'title' => $title,
      'status' => 1,
      'uid' => $owner->id(),
      'field_aculta_discussion' => ['status' => $commentStatus],
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * Asserts that context resolution throws the stable isolation failure.
   */
  private function assertContextRejected(EntityInterface $entity, AccountInterface $account, string $message): void {
    try {
      $this->provider->getContext($entity, $account);
      self::fail('Context resolution should reject the entity.');
    }
    catch (\LogicException $exception) {
      self::assertSame($message, $exception->getMessage());
    }
  }

  /**
   * Asserts read, write, and moderation are all forbidden.
   */
  private function assertContextForbidden(EntityInterface $topic, AccountInterface $account): void {
    $context = $this->provider->getContext($topic, $account);
    self::assertTrue($context->getReadAccess()->isForbidden(), 'Read access is forbidden.');
    self::assertTrue($context->getWriteAccess()->isForbidden(), 'Write access is forbidden.');
    self::assertTrue($context->getModerationAccess()->isForbidden(), 'Moderation access is forbidden.');
  }

  /**
   * Asserts the member context is readable and replyable, never moderated.
   */
  private function assertContextAllowed(EntityInterface $topic, AccountInterface $account): void {
    $context = $this->provider->getContext($topic, $account);
    self::assertTrue($context->getReadAccess()->isAllowed(), 'Read access is allowed.');
    self::assertTrue($context->getWriteAccess()->isAllowed(), 'Write access is allowed.');
    self::assertTrue($context->getModerationAccess()->isForbidden(), 'Moderation access stays forbidden.');
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
