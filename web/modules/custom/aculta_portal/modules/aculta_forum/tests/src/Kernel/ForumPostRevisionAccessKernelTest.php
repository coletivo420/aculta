<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Access\ForumPostModerationAccess;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
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
 * Proves revision history and comparison stay on the main purpose.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostRevisionAccessKernelTest extends KernelTestBase {

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
   * Access under test.
   */
  private ForumPostModerationAccess $access;

  /**
   * Purpose resolver.
   */
  private DomainPurposeManager $purpose;

  /**
   * Member account.
   */
  private User $member;

  /**
   * Revisionable post with two revisions.
   */
  private ForumPost $post;

  /**
   * First revision ID.
   */
  private int $firstRevision;

  /**
   * Second revision ID.
   */
  private int $secondRevision;

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
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico',
      'status' => 1,
      'uid' => $this->member->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();
    $this->post = ForumPost::create([
      'uid' => $this->member->id(),
      'topic' => $topic->id(),
      'body' => ['value' => 'Primeira versão', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $this->post->save();
    $this->firstRevision = (int) $this->post->getRevisionId();
    $this->post->setNewRevision(TRUE);
    $this->post->set('body', ['value' => 'Segunda versão', 'format' => 'plain_text']);
    $this->post->save();
    $this->secondRevision = (int) $this->post->getRevisionId();

    $this->purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    );
    $this->access = new ForumPostModerationAccess(
      $this->container->get('entity_type.manager'),
      $this->purpose,
    );
  }

  /**
   * History is allowed only on the main purpose with topic access.
   */
  public function testRevisionHistoryRequiresMainPurpose(): void {
    $main = $this->purpose->runInPurpose(
      'main',
      fn () => $this->access->revisionHistory($this->member, $this->post),
    );
    self::assertTrue($main->isAllowed());

    $forum = $this->purpose->runInPurpose(
      'forum',
      fn () => $this->access->revisionHistory($this->member, $this->post),
    );
    self::assertFalse($forum->isAllowed());
  }

  /**
   * Comparison accepts two distinct revisions of the same post.
   */
  public function testValidComparisonIsAllowed(): void {
    $access = $this->purpose->runInPurpose('main', fn () => $this->access->compareRevisions(
      $this->member,
      $this->post,
      (string) $this->firstRevision,
      (string) $this->secondRevision,
    ));
    self::assertTrue($access->isAllowed());
  }

  /**
   * Duplicate, unknown, or foreign revisions are refused.
   */
  public function testInvalidComparisonsAreRefused(): void {
    $same = $this->purpose->runInPurpose('main', fn () => $this->access->compareRevisions(
      $this->member,
      $this->post,
      (string) $this->firstRevision,
      (string) $this->firstRevision,
    ));
    self::assertFalse($same->isAllowed());

    $unknown = $this->purpose->runInPurpose('main', fn () => $this->access->compareRevisions(
      $this->member,
      $this->post,
      (string) $this->firstRevision,
      '999999',
    ));
    self::assertFalse($unknown->isAllowed());

    $other = ForumPost::create([
      'uid' => $this->member->id(),
      'topic' => $this->post->get('topic')->target_id,
      'body' => ['value' => 'Outro post', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $other->save();
    $foreign = $this->purpose->runInPurpose('main', fn () => $this->access->compareRevisions(
      $this->member,
      $this->post,
      (string) $this->firstRevision,
      (string) $other->getRevisionId(),
    ));
    self::assertFalse($foreign->isAllowed());
  }

}
