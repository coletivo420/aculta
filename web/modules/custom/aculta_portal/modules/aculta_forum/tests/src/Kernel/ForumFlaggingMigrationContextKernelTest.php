<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Migration\ForumFlaggingMigrationContext;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\flag\Entity\Flag;
use Drupal\flag\Entity\Flagging;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves the imported report flagging preserves and validates source identity.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumFlaggingMigrationContextKernelTest extends KernelTestBase {

  use CommentTestTrait;

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
    'comment',
    'domain',
    'flag',
    'migrate',
    'aculta_forum_post_test',
  ];

  /**
   * Author and reporter.
   */
  private User $author;

  /**
   * Public forum topic.
   */
  private Node $topic;

  /**
   * Comment that is the source of the report.
   */
  private CommentInterface $comment;

  /**
   * ForumPost already mapped from the source Comment.
   */
  private ForumPost $post;

  /**
   * Source flagging on the Comment.
   */
  private Flagging $sourceFlagging;

  /**
   * Migration lookup returning the mapped post for the source Comment.
   */
  private MigrateLookupInterface $lookup;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('flagging');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'comment', 'domain']);

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
    $this->addDefaultCommentField('node', 'aculta_forum_topic', 'field_aculta_discussion', 2, 'aculta_discussion_post');
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    Flag::create([
      'id' => 'sinalizar_comentario',
      'label' => 'Sinalizar comentário',
      'entity_type' => 'comment',
      'bundles' => ['aculta_discussion_post'],
      'flag_type' => 'entity:comment',
      'flag_short' => 'Sinalizar',
      'unflag_short' => 'Remover',
      'flag_long' => '',
      'unflag_long' => '',
    ])->save();
    Flag::create([
      'id' => 'sinalizar_post_forum',
      'label' => 'Sinalizar publicação do Fórum',
      'entity_type' => 'aculta_forum_post',
      'bundles' => ['aculta_forum_post'],
      'flag_type' => 'entity:aculta_forum_post',
      'flag_short' => 'Sinalizar',
      'unflag_short' => 'Remover',
      'flag_long' => '',
      'unflag_long' => '',
    ])->save();

    $this->author = User::create(['name' => 'autora', 'status' => 1]);
    $this->author->save();
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico público',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $this->topic->save();

    $this->comment = Comment::create([
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $this->topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $this->author->id(),
      'subject' => 'Resposta denunciada',
      'comment_body' => ['value' => 'Corpo', 'format' => 'plain_text'],
      'status' => CommentInterface::PUBLISHED,
    ]);
    $this->comment->save();

    $this->post = ForumPost::create([
      'uid' => $this->author->id(),
      'topic' => $this->topic->id(),
      'body' => ['value' => 'Corpo', 'format' => 'forum'],
      'status' => 1,
    ]);
    $this->post->save();

    $this->sourceFlagging = Flagging::create([
      'flag_id' => 'sinalizar_comentario',
      'entity_type' => 'comment',
      'entity_id' => $this->comment->id(),
      'uid' => $this->author->id(),
      'session_id' => 'session-abc',
      'global' => 0,
      'created' => 1700000000,
    ]);
    $this->sourceFlagging->save();

    $this->lookup = $this->createMock(MigrateLookupInterface::class);
    $this->lookup->method('lookup')
      ->with('aculta_forum_comment_posts', ['cid' => $this->comment->id()])
      ->willReturn([['id' => $this->post->id()]]);
  }

  /**
   * A matching destination flagging is saved with the source identity.
   */
  public function testMatchingFlaggingIsSaved(): void {
    $flagging = $this->destinationFlagging();
    $context = $this->context();
    $saved = $context->saveImportedFlagging(
      $flagging,
      (int) $this->sourceFlagging->id(),
      (int) $this->comment->id(),
      function () use ($flagging): array {
        $flagging->save();
        return [(int) $flagging->id()];
      },
    );

    self::assertGreaterThan(0, (int) reset($saved));
    self::assertFalse($context->authorizes($flagging));
    $stored = $this->container->get('entity_type.manager')
      ->getStorage('flagging')
      ->load((int) $flagging->id());
    self::assertInstanceOf(Flagging::class, $stored);
    self::assertSame('sinalizar_post_forum', $stored->getFlagId());
    self::assertSame((int) $this->post->id(), (int) $stored->getFlaggableId());
    self::assertSame((int) $this->author->id(), (int) $stored->getOwnerId());
    self::assertSame(1700000000, (int) $stored->getCreatedTime());
  }

  /**
   * A destination flagging with a different owner or timestamp is refused.
   */
  public function testIdentityMismatchIsRejected(): void {
    $other = User::create(['name' => 'outra', 'status' => 1]);
    $other->save();
    $this->assertRejected($this->destinationFlagging(['uid' => (int) $other->id()]), 'dono, escopo ou timestamp');
    $this->assertRejected($this->destinationFlagging(['created' => 1700000001]), 'dono, escopo ou timestamp');
  }

  /**
   * A source report outside the public Forum context is refused.
   */
  public function testSourceOutsideForumIsRejected(): void {
    $this->topic->set('field_domain_source', ['target_id' => 'aculta_org']);
    $this->topic->save();
    $this->assertRejected($this->destinationFlagging(), 'não pertence a um Comment migrável');
  }

  /**
   * A post mapped to another topic is refused.
   */
  public function testPostOutsideTheSourceTopicIsRejected(): void {
    $other_topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Outro tópico',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $other_topic->save();
    $this->post->set('topic', ['target_id' => $other_topic->id()]);
    $this->post->save();
    $this->assertRejected($this->destinationFlagging(), 'mesmo tópico público');
  }

  /**
   * A corrupt source row that would duplicate a target flagging is refused.
   */
  public function testDuplicateTargetFlaggingIsRejected(): void {
    $existing = $this->destinationFlagging();
    $existing->save();
    $this->assertRejected($this->destinationFlagging(), 'denúncia duplicada');
  }

  /**
   * Global scope, missing timestamp and unmapped posts are refused.
   */
  public function testInvalidScopeTimestampAndMappingAreRejected(): void {
    $this->assertRejected($this->destinationFlagging(['global' => 1]), 'tipo, dono, escopo ou timestamp');
    $this->assertRejected($this->destinationFlagging(['created' => 0]), 'tipo, dono, escopo ou timestamp');

    $lookup = $this->createMock(MigrateLookupInterface::class);
    $lookup->method('lookup')->willReturn([]);
    try {
      (new ForumFlaggingMigrationContext(
        $this->container->get('entity_type.manager'),
        new DomainPurposeManager(
          $this->container->get('entity_type.manager'),
          $this->container->get('domain.negotiator'),
          $this->container->get('config.factory'),
          new RequestStack(),
        ),
        $lookup,
      ))->saveImportedFlagging(
        $this->destinationFlagging(),
        (int) $this->sourceFlagging->id(),
        (int) $this->comment->id(),
        fn (): array => [],
      );
      self::fail('Denúncia sem post mapeado foi aceita.');
    }
    catch (MigrateException $exception) {
      self::assertStringContainsString('mesmo tópico público', $exception->getMessage());
    }
  }

  /**
   * A source report with another flag or anonymous without session is refused.
   */
  public function testInvalidSourceFlaggingIsRejected(): void {
    Flag::create([
      'id' => 'outra_flag',
      'label' => 'Outra',
      'entity_type' => 'comment',
      'bundles' => ['aculta_discussion_post'],
      'flag_type' => 'entity:comment',
      'flag_short' => 'Marcar',
      'unflag_short' => 'Remover',
      'flag_long' => '',
      'unflag_long' => '',
    ])->save();
    $other = Flagging::create([
      'flag_id' => 'outra_flag',
      'entity_type' => 'comment',
      'entity_id' => $this->comment->id(),
      'uid' => $this->author->id(),
      'session_id' => 'session-xyz',
      'global' => 0,
      'created' => 1700000000,
    ]);
    $other->save();
    $this->assertRejected($this->destinationFlagging(), 'não pertence a um Comment migrável', $other);

    $anonymous = Flagging::create([
      'flag_id' => 'sinalizar_comentario',
      'entity_type' => 'comment',
      'entity_id' => $this->comment->id(),
      'uid' => 0,
      'session_id' => '',
      'global' => 0,
      'created' => 1700000000,
    ]);
    $anonymous->save();
    $this->assertRejected(
      $this->destinationFlagging(['uid' => 0, 'session_id' => '']),
      'sessão identificável',
      $anonymous,
    );
  }

  /**
   * Asserts the context refuses a destination flagging.
   */
  private function assertRejected(Flagging $flagging, string $fragment, ?Flagging $source = NULL): void {
    $source ??= $this->sourceFlagging;
    try {
      $this->context()->saveImportedFlagging(
        $flagging,
        (int) $source->id(),
        (int) $this->comment->id(),
        fn (): array => [(int) $flagging->id()],
      );
      self::fail('A denúncia incompatível foi aceita pela migration.');
    }
    catch (MigrateException $exception) {
      self::assertStringContainsString($fragment, $exception->getMessage());
    }
  }

  /**
   * Builds the destination flagging from the source identity.
   *
   * @param array<string, mixed> $overrides
   *   Values that replace the matching defaults.
   */
  private function destinationFlagging(array $overrides = []): Flagging {
    return Flagging::create($overrides + [
      'flag_id' => 'sinalizar_post_forum',
      'entity_type' => 'aculta_forum_post',
      'entity_id' => $this->post->id(),
      'uid' => $this->author->id(),
      'session_id' => 'session-abc',
      'global' => 0,
      'created' => 1700000000,
    ]);
  }

  /**
   * Builds the context under test.
   */
  private function context(): ForumFlaggingMigrationContext {
    return new ForumFlaggingMigrationContext(
      $this->container->get('entity_type.manager'),
      new DomainPurposeManager(
        $this->container->get('entity_type.manager'),
        $this->container->get('domain.negotiator'),
        $this->container->get('config.factory'),
        new RequestStack(),
      ),
      $this->lookup,
    );
  }

}
