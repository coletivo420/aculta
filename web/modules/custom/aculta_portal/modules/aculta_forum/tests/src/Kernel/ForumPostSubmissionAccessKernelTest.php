<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Access\ForumPostSubmissionAccess;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Context\Provider\PublicForumContextProvider;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves the public post submission form requires a writable Forum topic.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostSubmissionAccessKernelTest extends KernelTestBase {

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
   * Purpose resolver shared by the fixture.
   */
  private DomainPurposeManager $purpose;

  /**
   * Submission access under test.
   */
  private ForumPostSubmissionAccess $access;

  /**
   * Request stack that supplies the query arguments.
   */
  private RequestStack $requestStack;

  /**
   * Published public Forum topic.
   */
  private Node $topic;

  /**
   * Author of the fixture topic.
   */
  private User $author;

  /**
   * Member account used by the scenarios.
   */
  private User $member;

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
    Role::load(RoleInterface::AUTHENTICATED_ID)
      ->grantPermission('access content')
      ->grantPermission('create aculta forum posts')
      ->save();
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
    $author = User::create(['name' => 'autor-base', 'status' => 1]);
    $author->save();
    $this->author = $author;
    $this->member = User::create(['name' => 'membro', 'status' => 1]);
    $this->member->save();
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico público',
      'status' => 1,
      'uid' => $author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $this->topic->save();

    $this->purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    );
    $this->requestStack = new RequestStack();
    $this->access = new ForumPostSubmissionAccess(
      $this->container->get('entity_type.manager'),
      new DiscussionContextResolver([new PublicForumContextProvider($this->purpose)]),
      $this->requestStack,
    );
  }

  /**
   * Anonymous accounts and missing topics are refused.
   */
  public function testAnonymousAndMissingTopicAreRefused(): void {
    $this->pushRequest();
    $anonymous = $this->inForumPurpose(fn () => $this->access->access(new AnonymousUserSession()));
    self::assertTrue($anonymous->isForbidden());

    $this->pushRequest('');
    $missing = $this->inForumPurpose(fn () => $this->access->access($this->member));
    self::assertTrue($missing->isForbidden());
  }

  /**
   * Unknown and outside-purpose topics are refused.
   */
  public function testUnknownAndOutsidePurposeTopicsAreRefused(): void {
    $this->pushRequest('00000000-0000-0000-0000-000000000000');
    $unknown = $this->inForumPurpose(fn () => $this->access->access($this->member));
    self::assertTrue($unknown->isForbidden());

    $this->pushRequest($this->topic->uuid());
    $outside = $this->purpose->runInPurpose(
      'main',
      fn () => $this->access->access($this->member),
    );
    self::assertFalse($outside->isAllowed(), 'Fora do purpose Fórum o envio não é liberado.');
  }

  /**
   * An authenticated member with the publish permission may submit.
   */
  public function testMemberWithPermissionMaySubmit(): void {
    $this->pushRequest($this->topic->uuid());
    $member = $this->inForumPurpose(fn () => $this->access->access($this->member));
    self::assertTrue($member->isAllowed());
  }

  /**
   * A reply must point to a published post in the same topic.
   */
  public function testReplyRequiresPublishedParentInSameTopic(): void {
    $post = ForumPost::create([
      'uid' => (int) $this->author->id(),
      'topic' => $this->topic->id(),
      'body' => ['value' => 'Corpo', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $post->save();

    $this->pushRequest($this->topic->uuid(), $post->uuid());
    $ids = $this->container->get('entity_type.manager')->getStorage('aculta_forum_post')->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $post->uuid())
      ->execute();
    self::assertNotEmpty($ids, 'Parent post is accessible to the member.');
    $parent = $this->container->get('entity_type.manager')->getStorage('aculta_forum_post')->load(reset($ids));
    self::assertTrue($parent->isPublished(), 'Parent published.');
    self::assertTrue($parent->isDefaultRevision(), 'Parent is default revision.');
    self::assertSame((string) $this->topic->id(), (string) $parent->get('topic')->target_id, 'Parent topic matches.');
    $valid = $this->inForumPurpose(fn () => $this->access->access($this->member));
    self::assertTrue($valid->isAllowed(), 'Valid reply is allowed.');

    $this->pushRequest($this->topic->uuid(), '00000000-0000-0000-0000-000000000000');
    $invalid = $this->inForumPurpose(fn () => $this->access->access($this->member));
    self::assertTrue($invalid->isForbidden());
  }

  /**
   * Runs an access callback inside the Forum purpose.
   */
  private function inForumPurpose(callable $callback): AccessResultInterface {
    return $this->purpose->runInPurpose('forum', $callback);
  }

  /**
   * Pushes a fake request with the Form API query arguments.
   */
  private function pushRequest(string $topic_uuid = '', string $reply_uuid = ''): void {
    $this->requestStack = new RequestStack();
    $request = Request::create('/forum/publicacao', 'GET', array_filter([
      'topico' => $topic_uuid,
      'resposta' => $reply_uuid,
    ], static fn (string $value): bool => $value !== ''));
    $this->requestStack->push($request);
    $this->container->get('current_user')->setAccount($this->member);
    $this->access = new ForumPostSubmissionAccess(
      $this->container->get('entity_type.manager'),
      new DiscussionContextResolver([new PublicForumContextProvider($this->purpose)]),
      $this->requestStack,
    );
  }

}
