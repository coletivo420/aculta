<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\aculta_forum\Plugin\migrate\source\ForumComment;
use Drupal\aculta_forum\Plugin\migrate\source\ForumCommentFlagging;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\flag\Entity\Flag;
use Drupal\flag\Entity\Flagging;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves the migration source yields one eligible row per Forum Comment.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumCommentMigrationSourceKernelTest extends KernelTestBase {

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
    'language',
    'domain',
    'flag',
  ];

  /**
   * Author of the fixture content.
   */
  private User $author;

  /**
   * Public Forum topic in the canonical forum Domain.
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
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installEntitySchema('flagging');
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'comment', 'domain']);

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Página'])->save();
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
    $this->addDefaultCommentField('node', 'page', 'comment', 2, 'comment');
    FieldStorageConfig::create([
      'entity_type' => 'comment',
      'field_name' => 'field_autor_legado',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'user'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'comment',
      'bundle' => 'aculta_discussion_post',
      'field_name' => 'field_autor_legado',
      'label' => 'Autor legado',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'comment',
      'field_name' => 'field_aculta_published_at',
      'type' => 'timestamp',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'comment',
      'bundle' => 'aculta_discussion_post',
      'field_name' => 'field_aculta_published_at',
      'label' => 'Publicado em',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'comment',
      'field_name' => 'field_aculta_rejected',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'comment',
      'bundle' => 'aculta_discussion_post',
      'field_name' => 'field_aculta_rejected',
      'label' => 'Rejeitado',
    ])->save();
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
    $this->author = User::create(['name' => 'autora', 'status' => 1]);
    $this->author->save();
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico do Forum',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $this->topic->save();
  }

  /**
   * Eligible comments become one row each with their hierarchy metadata.
   */
  public function testSourceYieldsOneRowPerEligibleComment(): void {
    $root = $this->comment($this->topic, 'Resposta raiz', [
      'field_aculta_published_at' => 1700000000,
    ]);
    $reply = $this->comment($this->topic, 'Resposta filha', [], $root);

    $rows = $this->sourceRows();

    self::assertCount(2, $rows);
    $by_id = [];
    foreach ($rows as $row) {
      $by_id[$row['cid']] = $row;
    }
    $root_row = $by_id[(int) $root->id()];
    self::assertSame((int) $this->topic->id(), $root_row['topic_id']);
    self::assertSame((int) $this->author->id(), $root_row['uid']);
    self::assertNull($root_row['parent_cid']);
    self::assertSame(1700000000, $root_row['first_published']);
    self::assertSame(1, $root_row['published']);
    self::assertSame(0, $root_row['rejected']);
    self::assertCount(1, $root_row['translations']);
    $translation = reset($root_row['translations']);
    self::assertSame('Resposta raiz', $translation['subject']);
    self::assertSame('plain_text', $translation['body_format']);

    $reply_row = $by_id[(int) $reply->id()];
    self::assertSame((int) $root->id(), $reply_row['parent_cid']);
  }

  /**
   * Comments outside the forum Domain or context are excluded.
   */
  public function testSourceSkipsCommentsOutsideThePublicForum(): void {
    $other_topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico fora do Domain',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'aculta_org'],
    ]);
    $other_topic->save();
    $this->comment($other_topic, 'Fora do Domain');

    $page = Node::create([
      'type' => 'page',
      'title' => 'Página',
      'status' => 1,
      'uid' => $this->author->id(),
    ]);
    $page->save();
    Comment::create([
      'comment_type' => 'comment',
      'entity_type' => 'node',
      'entity_id' => $page->id(),
      'field_name' => 'comment',
      'uid' => $this->author->id(),
      'subject' => 'Comentário comum',
      'comment_body' => ['value' => 'Fora do Fórum', 'format' => 'plain_text'],
      'status' => CommentInterface::PUBLISHED,
    ])->save();

    self::assertSame([], $this->sourceRows());
  }

  /**
   * Keyset pagination returns every Comment once, in CID order, across batches.
   */
  public function testKeysetPaginationReturnsEveryCommentOnce(): void {
    $created = [];
    for ($index = 0; $index < 5; $index++) {
      $created[] = (int) $this->comment($this->topic, 'Resposta ' . $index)->id();
    }
    $rows = $this->sourceRows(2);
    self::assertCount(5, $rows);
    self::assertSame($created, array_column($rows, 'cid'));
  }

  /**
   * The flagging source yields only reports on eligible public Comments.
   */
  public function testFlaggingSourceYieldsOnlyEligibleComments(): void {
    $eligible = $this->comment($this->topic, 'Resposta denunciada');
    $other_topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Outro Domain',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'aculta_org'],
    ]);
    $other_topic->save();
    $outside = $this->comment($other_topic, 'Fora do Domain');

    $eligible_flag = $this->flagging($eligible, 'session-abc', 0);
    $this->flagging($outside, 'session-def', 1);

    $rows = $this->flaggingSourceRows();
    self::assertCount(1, $rows);
    self::assertSame((int) $eligible_flag->id(), $rows[0]['fid']);
    self::assertSame((int) $eligible->id(), $rows[0]['cid']);
    self::assertSame((int) $this->author->id(), $rows[0]['uid']);
    self::assertSame('session-abc', $rows[0]['session_id']);
    self::assertSame(0, $rows[0]['global']);
  }

  /**
   * All translations of one Comment stay in a single source row.
   */
  public function testSourceGroupsTranslationsInOneRow(): void {
    ConfigurableLanguage::createFromLangcode('pt-br')->save();
    CommentType::load('aculta_discussion_post')
      ->set('translatable', TRUE)
      ->save();
    $comment = $this->comment($this->topic, 'Original');
    $comment->addTranslation('pt-br', [
      'subject' => 'Tradução',
      'comment_body' => ['value' => 'Corpo traduzido', 'format' => 'plain_text'],
    ])->save();

    $rows = $this->sourceRows();
    self::assertCount(1, $rows);
    self::assertSame((int) $comment->id(), $rows[0]['cid']);
    self::assertCount(2, $rows[0]['translations']);
    self::assertSame('Tradução', $rows[0]['translations']['pt-br']['subject']);
  }

  /**
   * Historical authorship and rejection are preserved in the source row.
   */
  public function testSourceKeepsLegacyAuthorAndRejectionMarkers(): void {
    $comment = $this->comment($this->topic, 'Resposta histórica', [
      'field_autor_legado' => ['target_id' => $this->author->id()],
      'field_aculta_rejected' => 1,
    ]);

    $rows = $this->sourceRows();
    self::assertCount(1, $rows);
    self::assertSame((int) $comment->id(), $rows[0]['cid']);
    self::assertSame((int) $this->author->id(), $rows[0]['legacy_author_id']);
    self::assertSame(1, $rows[0]['rejected']);
  }

  /**
   * Instantiates the source plugin and collects its iterator.
   *
   * @return array<int, array<string, mixed>>
   *   Source rows keyed by position.
   */
  private function sourceRows(int $batch_size = 50): array {
    $source = new ForumComment(
      ['comment_batch_size' => $batch_size],
      'aculta_forum_comment',
      [],
      $this->createMock(MigrationInterface::class),
      $this->container->get('entity_type.manager'),
      new DomainPurposeManager(
        $this->container->get('entity_type.manager'),
        $this->container->get('domain.negotiator'),
        $this->container->get('config.factory'),
        new RequestStack(),
      ),
    );
    $method = new \ReflectionMethod($source, 'initializeIterator');
    $method->setAccessible(TRUE);
    return iterator_to_array($method->invoke($source), FALSE);
  }

  /**
   * Instantiates the flagging source and collects its iterator.
   *
   * @return array<int, array<string, mixed>>
   *   Flagging source rows.
   */
  private function flaggingSourceRows(): array {
    $source = new ForumCommentFlagging(
      [],
      'aculta_forum_comment_flagging',
      [],
      $this->createMock(MigrationInterface::class),
      $this->container->get('entity_type.manager'),
      new DomainPurposeManager(
        $this->container->get('entity_type.manager'),
        $this->container->get('domain.negotiator'),
        $this->container->get('config.factory'),
        new RequestStack(),
      ),
    );
    $method = new \ReflectionMethod($source, 'initializeIterator');
    $method->setAccessible(TRUE);
    return iterator_to_array($method->invoke($source), FALSE);
  }

  /**
   * Creates a report flagging on a comment.
   */
  private function flagging(CommentInterface $comment, string $session_id, int $global): Flagging {
    $flagging = Flagging::create([
      'flag_id' => 'sinalizar_comentario',
      'entity_type' => 'comment',
      'entity_id' => $comment->id(),
      'uid' => $this->author->id(),
      'session_id' => $session_id,
      'global' => $global,
    ]);
    $flagging->save();
    return $flagging;
  }

  /**
   * Creates a Forum comment with optional field values.
   *
   * @param \Drupal\node\Entity\Node $topic
   *   Topic that hosts the comment.
   * @param string $subject
   *   Comment subject.
   * @param array<string, mixed> $fields
   *   Extra field values for the comment.
   * @param \Drupal\comment\CommentInterface|null $parent
   *   Parent comment for a reply.
   */
  private function comment(Node $topic, string $subject, array $fields = [], ?CommentInterface $parent = NULL): CommentInterface {
    $values = [
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $this->author->id(),
      'subject' => $subject,
      'comment_body' => ['value' => 'Corpo de ' . $subject, 'format' => 'plain_text'],
      'status' => CommentInterface::PUBLISHED,
    ];
    if ($parent instanceof CommentInterface) {
      $values['pid'] = $parent->id();
    }
    $comment = Comment::create($values + $fields);
    $comment->save();
    return $comment;
  }

}
