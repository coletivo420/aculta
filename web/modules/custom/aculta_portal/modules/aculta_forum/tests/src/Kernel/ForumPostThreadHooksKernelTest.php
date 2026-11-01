<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Context\Provider\PublicForumContextProvider;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Hook\ForumPostThreadHooks;
use Drupal\aculta_forum\Service\ForumPostThreadBuilder;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves revisionable posts are added to the full Forum topic view only.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostThreadHooksKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'domain',
    'aculta_forum_post_test',
  ];

  /**
   * Purpose resolver.
   */
  private DomainPurposeManager $purpose;

  /**
   * Member account.
   */
  private User $member;

  /**
   * Topic with a published reply.
   */
  private Node $topic;

  /**
   * Hook under test.
   */
  private ForumPostThreadHooks $hooks;

  /**
   * Thread builder used by the hook.
   */
  private ForumPostThreadBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'domain']);
    foreach ([
      'aculta_org' => 'main.example.test',
      'forum_aculta_org' => 'forum.example.test',
    ] as $id => $hostname) {
      Domain::create([
        'id' => $id,
        'hostname' => $hostname,
        'name' => $id,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
      ])->save();
    }
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
    Role::load(RoleInterface::AUTHENTICATED_ID)->grantPermission('access content')->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
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
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->member = User::create(['name' => 'membro', 'status' => 1]);
    $this->member->save();
    $this->container->get('current_user')->setAccount($this->member);
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico',
      'status' => 1,
      'uid' => $this->member->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $this->topic->save();
    $post = ForumPost::create([
      'uid' => $this->member->id(),
      'topic' => $this->topic->id(),
      'body' => ['value' => 'Corpo', 'format' => 'plain_text'],
      'status' => 1,
      'thread_position' => 10,
      'created' => 1700000000,
      'first_published' => 1700000000,
    ]);
    $post->save();

    $this->purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    );
    $builder = new ForumPostThreadBuilder(
      $this->container->get('entity_type.manager'),
      $this->member,
      $this->purpose,
      new DiscussionContextResolver([new PublicForumContextProvider($this->purpose)]),
      $this->container->get('pager.manager'),
    );
    $this->builder = $builder;
    $this->hooks = new ForumPostThreadHooks($builder);
  }

  /**
   * Full view inside the Forum purpose receives the revisionable posts.
   */
  public function testFullViewReceivesRevisionablePosts(): void {
    $direct = $this->purpose->runInPurpose('forum', fn () => $this->builder->build($this->topic));
    self::assertNotEmpty($direct, 'Builder returns the thread in the Forum purpose.');
    $build = [];
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $this->purpose->runInPurpose('forum', function () use (&$build, $display): void {
      $this->hooks->entityView($build, $this->topic, $display, 'full');
    });
    self::assertArrayHasKey('aculta_forum_revisionable_posts', $build);
  }

  /**
   * Other view modes and other purposes do not receive the posts.
   */
  public function testOtherModesAndPurposesAreIgnored(): void {
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $teaser = [];
    $this->purpose->runInPurpose('forum', function () use (&$teaser, $display): void {
      $this->hooks->entityView($teaser, $this->topic, $display, 'teaser');
    });
    self::assertArrayNotHasKey('aculta_forum_revisionable_posts', $teaser);

    $outside = [];
    $this->purpose->runInPurpose('main', function () use (&$outside, $display): void {
      $this->hooks->entityView($outside, $this->topic, $display, 'full');
    });
    self::assertArrayNotHasKey('aculta_forum_revisionable_posts', $outside);
  }

}
