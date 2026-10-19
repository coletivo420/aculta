<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Service\ForumTopicMover;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies category movement through real Node, Taxonomy and Comment storage.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumTopicMoverKernelTest extends KernelTestBase {

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
    'node',
    'taxonomy',
    'comment',
    'path',
    'path_alias',
    'options',
    'domain',
  ];

  /**
   * The topic mover under test. */
  private ForumTopicMover $mover;

  /**
   * Account carrying the explicit topic-move permission. */
  private AccountInterface $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'filter', 'node', 'taxonomy', 'comment', 'domain', 'path', 'path_alias']);

    Domain::create([
      'id' => 'forum_aculta_org',
      'hostname' => 'forum.example.test',
      'name' => 'Forum test domain',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
    ])->save();
    Vocabulary::create(['vid' => 'aculta_forum_categories', 'name' => 'Forum categories'])->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic', 'new_revision' => TRUE])->save();

    $this->addField('entity_reference', 'field_domain_source', ['target_type' => 'domain'], 'node', 'aculta_forum_topic');
    $this->addField('string', 'field_autor_legado', [], 'node', 'aculta_forum_topic');
    $this->addField('entity_reference', 'field_aculta_categories', [
      'target_type' => 'taxonomy_term',
      'handler' => 'default:taxonomy_term',
      'handler_settings' => ['target_bundles' => ['aculta_forum_categories' => 'aculta_forum_categories']],
    ], 'node', 'aculta_forum_topic');
    $this->addDefaultCommentField('node', 'aculta_forum_topic', 'field_aculta_discussion', CommentingStatus::Open, 'aculta_discussion_post');

    $this->account = $this->createMock(AccountInterface::class);
    $this->account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, [
        'access content',
        'move aculta forum topics',
        'administer nodes',
      ], TRUE),
    );
    $this->account->method('id')->willReturn(42);
    $this->account->method('isAnonymous')->willReturn(FALSE);
    $this->account->method('isAuthenticated')->willReturn(TRUE);
    $manager = $this->container->get('entity_type.manager');
    $purposes = new DomainPurposeManager(
      $manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $this->mover = new ForumTopicMover(
      $manager,
      $purposes,
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Moving a topic keeps its identity, alias, Comment and authoring data.
   */
  public function testMovePreservesTopicIdentityAndDiscussion(): void {
    $source = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Source']);
    $source->save();
    $destination = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Destination']);
    $destination->save();
    $node = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Movable test topic',
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_aculta_categories' => ['target_id' => $source->id()],
      'field_aculta_discussion' => ['status' => CommentingStatus::Open->value],
      'field_autor_legado' => [],
      'path' => ['alias' => '/topico-movivel-teste'],
    ]);
    $node->save();
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'subject' => 'Reply preserved',
      'comment_body' => ['value' => 'Reply body', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $comment->save();
    $nid = (int) $node->id();
    $uuid = $node->uuid();
    $original_vid = (int) $node->getRevisionId();
    $cid = (int) $comment->id();

    $access = $this->mover->access($node, $this->account);
    self::assertTrue($access->isAllowed());

    $moved = $this->mover->move($nid, (int) $destination->id(), $this->account);

    self::assertSame($nid, (int) $moved->id());
    self::assertSame($uuid, $moved->uuid());
    self::assertNotSame($original_vid, (int) $moved->getRevisionId());
    self::assertSame((int) $destination->id(), (int) $moved->get('field_aculta_categories')->target_id);
    self::assertSame('/topico-movivel-teste', $moved->get('path')->alias);
    $reloaded_comment = Comment::load($cid);
    self::assertNotNull($reloaded_comment);
    self::assertSame($nid, (int) $reloaded_comment->getCommentedEntityId());
    self::assertSame('Reply body', $reloaded_comment->get('comment_body')->value);
  }

  /**
   * Historical topics cannot be moved even with the operation permission.
   */
  public function testHistoricalTopicCannotBeMoved(): void {
    $category = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Historic']);
    $category->save();
    $node = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Historical test topic',
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_aculta_categories' => ['target_id' => $category->id()],
      'field_autor_legado' => ['value' => 'historical-author-reference'],
    ]);
    $node->save();
    $destination = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Another']);
    $destination->save();

    $this->expectException(\InvalidArgumentException::class);
    $this->mover->move((int) $node->id(), (int) $destination->id(), $this->account);
  }

  /**
   * Creates a field storage and bundle instance for the test topic.
   */
  private function addField(string $type, string $name, array $settings, string $entityType, string $bundle): void {
    FieldStorageConfig::create([
      'entity_type' => $entityType,
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
    ])->save();
    FieldConfig::create([
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

}
