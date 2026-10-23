<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies the production Forum provider through Index backfill and CRUD.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexForumTopicProviderKernelTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'comment',
    'aculta_index',
    'aculta_index_forum_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
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
    $this->installConfig(['node', 'user', 'system', 'comment']);
    aculta_index_install();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
    $this->addDefaultCommentField(
      'node',
      'aculta_forum_topic',
      'field_aculta_discussion',
      CommentingStatus::Open,
      'aculta_discussion_post',
    );
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
  }

  /**
   * Backfills a Forum topic, updates its projection, then deletes it.
   */
  public function testForumTopicsAndCommentsFollowEntityCrud(): void {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico inicial',
      'status' => TRUE,
    ]);
    $topic->save();
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => (int) $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'uid' => 0,
      'subject' => 'Resposta inicial',
      'comment_body' => ['value' => 'Texto que não deve ser projetado', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $comment->save();
    $rejectedComment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => (int) $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'uid' => 0,
      'subject' => 'Resposta rejeitada',
      'field_aculta_rejected' => TRUE,
      'status' => 1,
    ]);
    $rejectedComment->save();

    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('forum', $registry->findProviderIdForBundle('node', 'aculta_forum_topic'));
    self::assertSame('forum', $registry->get('forum')->getAccessPurpose($topic));

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The Forum provider backfill should complete.');
    $this->processForumWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    self::assertSame('Tópico inicial', $this->entryLabel($generationId, (string) $topic->id()));
    self::assertSame('Resposta inicial', $this->entryLabel($generationId, (string) $comment->id(), 'comment'));
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('entity_type', 'comment')
      ->condition('entity_id', (string) $rejectedComment->id())
      ->countQuery()
      ->execute()
      ->fetchField());
    self::assertSame(1, (int) $this->container->get('database')->select('aculta_index_relation', 'relation')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('source_entity_type', 'comment')
      ->condition('source_entity_id', (string) $comment->id())
      ->condition('relation_type', 'forum_topic')
      ->condition('target_entity_id', (string) $topic->id())
      ->countQuery()
      ->execute()
      ->fetchField());

    $comment->setSubject('Resposta atualizada');
    $comment->save();
    $this->processForumWork();
    self::assertSame('Resposta atualizada', $this->entryLabel($generationId, (string) $comment->id(), 'comment'));

    $comment->delete();
    $this->processForumWork();
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('entity_type', 'comment')
      ->condition('entity_id', (string) $comment->id())
      ->countQuery()
      ->execute()
      ->fetchField());
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_relation', 'relation')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('source_entity_type', 'comment')
      ->condition('source_entity_id', (string) $comment->id())
      ->countQuery()
      ->execute()
      ->fetchField());

    $topic->setTitle('Tópico atualizado');
    $topic->save();
    $this->processForumWork();
    self::assertSame('Tópico atualizado', $this->entryLabel($generationId, (string) $topic->id()));

    $topic->delete();
    $this->processForumWork();
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('entity_type', 'node')
      ->condition('entity_id', (string) $topic->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Projects ForumPost revisions and named topic/parent relationships.
   */
  public function testRevisionableForumPostsFollowEntityCrud(): void {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico das respostas',
      'status' => TRUE,
    ]);
    $topic->save();
    $root = ForumPost::create([
      'uid' => 0,
      'topic' => $topic->id(),
      'body' => ['value' => 'Texto privado do post', 'format' => 'plain_text'],
      'subject' => 'Resposta raiz',
      'status' => TRUE,
    ]);
    $root->save();
    $reply = ForumPost::create([
      'uid' => 0,
      'topic' => $topic->id(),
      'parent' => $root->id(),
      'body' => ['value' => 'Texto privado da resposta', 'format' => 'plain_text'],
      'subject' => 'Resposta filha',
      'status' => TRUE,
    ]);
    $reply->save();

    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('forum', $registry->findProviderIdForBundle('aculta_forum_post', 'aculta_forum_post'));
    self::assertSame('forum', $registry->get('forum')->getAccessPurpose($reply));

    $generationId = $this->buildAndActivateGeneration();
    self::assertSame('Resposta raiz', $this->entryLabel($generationId, (string) $root->id(), 'aculta_forum_post'));
    self::assertSame('Resposta filha', $this->entryLabel($generationId, (string) $reply->id(), 'aculta_forum_post'));
    self::assertSame(1, $this->relationCount($generationId, (string) $reply->id(), 'forum_topic', 'node', (string) $topic->id()));
    self::assertSame(1, $this->relationCount($generationId, (string) $reply->id(), 'forum_parent_post', 'aculta_forum_post', (string) $root->id()));

    $previousRevisionId = $reply->getRevisionId();
    $reply->setNewRevision(TRUE);
    $reply->set('subject', 'Resposta filha revisada');
    $reply->save();
    $previousRevision = $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post')
      ->loadRevision($previousRevisionId);
    self::assertFalse($previousRevision->isDefaultRevision());
    self::assertNull($registry->get('forum')->project($previousRevision));
    $this->processForumWork();
    self::assertSame('Resposta filha revisada', $this->entryLabel($generationId, (string) $reply->id(), 'aculta_forum_post'));

    $reply->delete();
    $this->processForumWork();
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('entity_type', 'aculta_forum_post')
      ->condition('entity_id', (string) $reply->id())
      ->countQuery()
      ->execute()
      ->fetchField());
    self::assertSame(0, $this->relationCount($generationId, (string) $reply->id(), 'forum_topic'));
    self::assertSame(0, $this->relationCount($generationId, (string) $reply->id(), 'forum_parent_post'));
  }

  /**
   * Builds, drains and activates one complete Index generation.
   */
  private function buildAndActivateGeneration(): string {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The Forum provider backfill should complete.');
    $this->processForumWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
    return $generationId;
  }

  /**
   * Counts one named outgoing relation for a Forum source identity.
   */
  private function relationCount(
    string $generationId,
    string $sourceEntityId,
    string $relationType,
    ?string $targetEntityType = NULL,
    ?string $targetEntityId = NULL,
  ): int {
    $query = $this->container->get('database')->select('aculta_index_relation', 'relation')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('source_entity_id', $sourceEntityId)
      ->condition('relation_type', $relationType);
    if ($targetEntityType !== NULL) {
      $query->condition('target_entity_type', $targetEntityType);
    }
    if ($targetEntityId !== NULL) {
      $query->condition('target_entity_id', $targetEntityId);
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Processes queued work belonging to the Forum provider.
   */
  private function processForumWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', 'forum')
      ->condition('status', 'queued')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($ids as $id) {
      $worker->processItem(['work_id' => (int) $id]);
    }
  }

  /**
   * Returns the label of one Forum topic entry in a generation.
   */
  private function entryLabel(string $generationId, string $entityId, string $entityType = 'node'): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'forum')
      ->condition('entity_type', $entityType)
      ->condition('entity_id', $entityId)
      ->execute()
      ->fetchField();
  }

}
