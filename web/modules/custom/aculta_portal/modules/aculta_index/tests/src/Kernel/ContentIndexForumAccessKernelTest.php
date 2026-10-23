<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Hook\RestrictedForumAccessHooks;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\domain\DomainInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Exercises Index access against the real Forum grants and Domain policies.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexForumAccessKernelTest extends KernelTestBase {

  use CommentTestTrait;

  private const GROUP_TYPE = 'associado_forum';

  /**
   * {@inheritdoc} */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->willReturnCallback(static fn (string $id): ?Domain => Domain::load($id));
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($domainStorage);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturnCallback(
      static fn (bool $reset = FALSE): ?DomainInterface => \Drupal::service('domain.negotiator')->getActiveDomain($reset),
    );
    $negotiator->method('setActiveDomain')->willReturnCallback(
      static function (DomainInterface $domain): void {
        \Drupal::service('domain.negotiator')->setActiveDomain($domain);
      },
    );
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $entityTypeManager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'comment',
    'entity',
    'flexible_permissions',
    'options',
    'group',
    'gnode',
    'domain',
    'aculta_index',
    'aculta_index_forum_test',
    'aculta_index_forum_access_test',
  ];

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('domain');
    $this->installEntitySchema('aculta_associado');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['system', 'user', 'node', 'comment', 'group', 'domain']);
    aculta_index_install();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
    $this->addDefaultCommentField(
      'node',
      'aculta_forum_topic',
      'field_aculta_discussion',
      CommentingStatus::Open,
      'aculta_discussion_post',
    );
    $this->installDomainField();
    $this->createDomains();
    $this->createRestrictedGroupType();
    $this->container->get('config.factory')->getEditable('aculta_forum.settings')
      ->set('restricted_group_type', self::GROUP_TYPE)
      ->save();
    $authenticated = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->grantPermission('access comments')->save();
  }

  /**
   * Revalidates topics and posts with real Group membership in Forum Domain. */
  public function testIndexFilterUsesForumDomainAndRestrictedGroupAccess(): void {
    $member = $this->createUser('forum-member');
    $this->setAssociateActive($member);
    $outsider = $this->createUser('forum-outsider');
    $group = $this->container->get('entity_type.manager')->getStorage('group')->create([
      'type' => self::GROUP_TYPE,
      'label' => 'Restricted forum group',
      'uid' => $member->id(),
    ]);
    $group->save();
    $group->addMember($member);

    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Restricted topic',
      'status' => TRUE,
      'uid' => $member->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();
    $group->addRelationship($topic, 'group_node:aculta_forum_topic')->save();
    // Re-save after creating the Group relationship so Core writes the
    // production node_access_records grant for this topic.
    $topic->save();
    $forumAccessHooks = $this->container->get(RestrictedForumAccessHooks::class);
    self::assertSame(
      [RestrictedForumAccessHooks::REALM => [(int) $group->id()]],
      $forumAccessHooks->nodeGrants($member, 'view'),
      'The production grant hook derives view grants from the real Group membership.',
    );
    self::assertSame([[
      'realm' => RestrictedForumAccessHooks::REALM,
      'gid' => (int) $group->id(),
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ],
    ], $forumAccessHooks->nodeAccessRecords($topic));

    $post = ForumPost::create([
      'uid' => $member->id(),
      'topic' => $topic->id(),
      'subject' => 'Restricted reply',
      'body' => ['value' => 'Not projected', 'format' => 'plain_text'],
      'status' => TRUE,
    ]);
    $post->save();

    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => (int) $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'uid' => $member->id(),
      'subject' => 'Legacy reply',
      'comment_body' => ['value' => 'Not projected', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $comment->save();

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);

    $filter = new ContentIndexAccessFilter(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_index.provider_registry'),
      $this->container->get('aculta_portal.domain_purpose'),
    );
    $topicCandidate = [[
      'entity_type' => 'node',
      'entity_id' => (string) $topic->id(),
      'langcode' => $topic->language()->getId(),
    ],
    ];
    $postCandidate = [[
      'entity_type' => 'aculta_forum_post',
      'entity_id' => (string) $post->id(),
      'langcode' => $post->language()->getId(),
    ],
    ];
    $commentCandidate = [[
      'entity_type' => 'comment',
      'entity_id' => (string) $comment->id(),
      'langcode' => $comment->language()->getId(),
    ],
    ];

    self::assertCount(1, $filter->filter($topicCandidate, $member)->entities);
    self::assertCount(1, $filter->filter($postCandidate, $member)->entities);
    self::assertCount(1, $filter->filter($commentCandidate, $member)->entities);
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    self::assertCount(0, $filter->filter($topicCandidate, $outsider)->entities);
    self::assertCount(0, $filter->filter($postCandidate, $outsider)->entities);
    self::assertCount(0, $filter->filter($commentCandidate, $outsider)->entities);
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
  }

  /**
   * Installs the Forum topic's Domain reference field. */
  private function installDomainField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => 'field_domain_source',
      'label' => 'Domain',
      'settings' => ['handler' => 'default:domain', 'handler_settings' => []],
    ])->save();
  }

  /**
   * Creates main and canonical Forum Domains. */
  private function createDomains(): void {
    foreach (['aculta_org' => 'main', 'forum_aculta_org' => 'forum'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
        'weight' => 0,
      ])->save();
    }
  }

  /**
   * Creates the restricted Forum Group type and topic relationship plugin. */
  private function createRestrictedGroupType(): void {
    $groupTypes = $this->container->get('entity_type.manager')->getStorage('group_type');
    $groupTypes->create([
      'id' => self::GROUP_TYPE,
      'label' => 'Restricted forum',
      'creator_membership' => FALSE,
      'creator_wizard' => FALSE,
    ])->save();
    $relationshipTypes = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationshipTypes);
    $relationshipTypes->save($relationshipTypes->createFromPlugin(
      $groupTypes->load(self::GROUP_TYPE),
      'group_node:aculta_forum_topic',
    ));
  }

  /**
   * Creates an authenticated test account. */
  private function createUser(string $name): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => 1,
      'roles' => ['authenticated'],
    ]);
    $user->save();
    return $user;
  }

  /**
   * Stores the production associate marker as active. */
  private function setAssociateActive(User $user): void {
    $this->container->get('entity_type.manager')->getStorage('aculta_associado')->create([
      'uid' => $user->id(),
      'situacao' => 'ativo',
    ])->save();
  }

}
