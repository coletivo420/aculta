<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Plugin\migrate\destination\ForumCommentPost;
use Drupal\aculta_forum\Plugin\migrate\source\ForumComment;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

/**
 * Runs the Comment-to-ForumPost migration with a persistent Migrate map.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumCommentMigrationBatchKernelTest extends KernelTestBase {

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
    'history',
    'language',
    'domain',
    'migrate',
    'workflows',
    'content_moderation',
    'aculta_forum_post_test',
  ];

  /**
   * Author of the fixture content.
   */
  private User $author;

  /**
   * Messages collected from the last migration run.
   *
   * @var string[]
   */
  private array $migrationMessages = [];

  /**
   * Public Forum topic.
   */
  private Node $topic;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('config.storage')->write('workflows.workflow.aculta_forum_post', [
      'langcode' => 'pt-br',
      'status' => TRUE,
      'id' => 'aculta_forum_post',
      'label' => 'Forum post migration batch workflow',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
          'review' => ['label' => 'Review', 'weight' => 1, 'published' => FALSE, 'default_revision' => FALSE],
          'published' => ['label' => 'Published', 'weight' => 2, 'published' => TRUE, 'default_revision' => TRUE],
          'rejected' => ['label' => 'Rejected', 'weight' => 3, 'published' => FALSE, 'default_revision' => TRUE],
        ],
        'transitions' => [
          'approve' => ['label' => 'Approve', 'from' => ['review'], 'to' => 'published', 'weight' => 0],
          'edit_published' => ['label' => 'Edit published', 'from' => ['published'], 'to' => 'published', 'weight' => 1],
          'reject' => ['label' => 'Reject', 'from' => ['review'], 'to' => 'rejected', 'weight' => 2],
        ],
        'entity_types' => ['aculta_forum_post' => ['aculta_forum_post']],
        'default_moderation_state' => 'draft',
      ],
    ]);
    $this->container->get('config.factory')->reset('workflows.workflow.aculta_forum_post');
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('history', ['history']);
    $this->installConfig([
      'system', 'user', 'field', 'filter', 'text', 'node', 'comment',
      'history', 'language', 'domain', 'migrate', 'workflows', 'content_moderation',
    ]);
    ConfigurableLanguage::createFromLangcode('pt-br')->save();

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
    $this->addDefaultCommentField('node', 'aculta_forum_topic', 'field_aculta_discussion', CommentingStatus::Open, 'aculta_discussion_post');
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
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->author = User::create(['name' => 'autora', 'status' => 1]);
    $this->author->save();
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico da migração',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $this->topic->save();
  }

  /**
   * The persistent map makes reruns idempotent and continues new rows.
   */
  public function testBatchImportIsIdempotentAndContinues(): void {
    $root = $this->comment('Raiz');
    $reply = $this->comment('Resposta', $root);

    $result = $this->runMigration();
    self::assertSame(MigrationInterface::RESULT_COMPLETED, $result, implode("\n", $this->migrationMessages));
    self::assertSame(2, $this->postCount());
    $map = $this->migration()->getIdMap();
    self::assertNotNull($map->lookupDestinationIds(['cid' => (int) $root->id()]));
    self::assertNotNull($map->lookupDestinationIds(['cid' => (int) $reply->id()]));

    // A replay imports nothing and keeps the same posts.
    self::assertSame(MigrationInterface::RESULT_COMPLETED, $this->runMigration());
    self::assertSame(2, $this->postCount());

    // A new Comment is imported on the next run, in the same map.
    $late = $this->comment('Resposta posterior', $root);
    self::assertSame(MigrationInterface::RESULT_COMPLETED, $this->runMigration());
    self::assertSame(3, $this->postCount());
    self::assertNotNull($map->lookupDestinationIds(['cid' => (int) $late->id()]));
  }

  /**
   * The parent migration resolves both Comment IDs through the persistent map.
   */
  public function testParentMigrationResolvesCommentMap(): void {
    $production = Yaml::parseFile(dirname(__DIR__, 3) . '/migrations/aculta_forum_comment_parents.yml');
    $fixture = Yaml::parseFile(dirname(__DIR__, 2) . '/modules/aculta_forum_post_test/migrations/aculta_forum_comment_parents.yml');
    self::assertSame($production['process'], $fixture['process']);
    self::assertSame($production['destination']['plugin'], $fixture['destination']['plugin']);
    self::assertSame($production['destination']['overwrite_properties'], $fixture['destination']['overwrite_properties']);
    self::assertSame($production['migration_dependencies'], $fixture['migration_dependencies']);

    $root = $this->comment('Raiz');
    $reply = $this->comment('Resposta', $root);
    self::assertSame(MigrationInterface::RESULT_COMPLETED, $this->runMigration());

    $migration = $this->container->get('plugin.manager.migration')
      ->createInstance('aculta_forum_comment_parents');
    $migration->set('source', [
      'plugin' => 'embedded_data',
      'data_rows' => [
        ['cid' => (int) $root->id(), 'parent_cid' => 0],
        ['cid' => (int) $reply->id(), 'parent_cid' => (int) $root->id()],
      ],
      'ids' => ['cid' => ['type' => 'integer']],
    ]);
    $message = new CollectingMigrateMessage();
    $result = (new MigrateExecutable($migration, $message))->import();
    self::assertSame(MigrationInterface::RESULT_COMPLETED, $result, implode("\n", $message->messages));

    $map = $this->migration()->getIdMap();
    $root_destination_ids = $map->lookupDestinationIds(['cid' => (int) $root->id()]);
    $reply_destination_ids = $map->lookupDestinationIds(['cid' => (int) $reply->id()]);
    $root_post_id = (int) $root_destination_ids[0][0];
    $reply_post_id = (int) $reply_destination_ids[0][0];
    $posts = $this->container->get('entity_type.manager')->getStorage('aculta_forum_post');
    $root_post = $posts->load($root_post_id);
    $reply_post = $posts->load($reply_post_id);

    self::assertNotNull($root_post);
    self::assertNotNull($reply_post);
    self::assertSame($root_post_id, (int) $reply_post->get('parent')->target_id);
    self::assertSame(2, $this->postCount());

    // Reverse dependency order: remove parent updates before deleting posts.
    self::assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration, $message))->rollback());
    $post_migration = $this->migration();
    self::assertSame(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($post_migration, $message))->rollback());
    self::assertSame(0, $this->postCount());
    self::assertSame(2, count($this->container->get('entity_type.manager')
      ->getStorage('comment')->loadMultiple([(int) $root->id(), (int) $reply->id()])));
    self::assertSame([], $migration->getIdMap()->lookupDestinationIds(['cid' => (int) $reply->id()]));
    self::assertSame([], $post_migration->getIdMap()->lookupDestinationIds(['cid' => (int) $root->id()]));
  }

  /**
   * Node-level read receipts survive the Comment-to-post migration unchanged.
   */
  public function testHistoryReceiptsArePreserved(): void {
    $this->container->get('database')->insert('history')
      ->fields(['uid' => (int) $this->author->id(), 'nid' => (int) $this->topic->id(), 'timestamp' => 1700000123])
      ->execute();
    $root = $this->comment('Raiz');
    $this->comment('Resposta', $root);

    self::assertSame(MigrationInterface::RESULT_COMPLETED, $this->runMigration(), implode("\n", $this->migrationMessages));

    $row = $this->container->get('database')->select('history', 'h')
      ->fields('h', ['uid', 'nid', 'timestamp'])
      ->condition('h.uid', (int) $this->author->id())
      ->condition('h.nid', (int) $this->topic->id())
      ->execute()
      ->fetchObject();
    self::assertNotNull($row);
    self::assertSame(1700000123, (int) $row->timestamp);
    self::assertSame(2, $this->postCount());
  }

  /**
   * A nested reply tree keeps content, order and parent links after migration.
   */
  public function testNestedTreeEquivalence(): void {
    $root = $this->comment('Raiz');
    $child = $this->comment('Filha', $root);
    $grandchild = $this->comment('Neta', $child);
    $comments = [
      (int) $root->id() => $root,
      (int) $child->id() => $child,
      (int) $grandchild->id() => $grandchild,
    ];

    self::assertSame(MigrationInterface::RESULT_COMPLETED, $this->runMigration(), implode("\n", $this->migrationMessages));
    $map = $this->migration()->getIdMap();
    $post_ids = [];
    foreach ($comments as $cid => $comment) {
      $destination = $map->lookupDestinationIds(['cid' => $cid]);
      self::assertNotEmpty($destination, 'Comment ' . $cid . ' precisa de destino.');
      $post_ids[$cid] = (int) $destination[0][0];
    }
    self::assertCount(3, array_unique($post_ids));

    $parent_migration = $this->container->get('plugin.manager.migration')
      ->createInstance('aculta_forum_comment_parents');
    $parent_migration->set('source', [
      'plugin' => 'embedded_data',
      'data_rows' => [
        ['cid' => (int) $root->id(), 'parent_cid' => 0],
        ['cid' => (int) $child->id(), 'parent_cid' => (int) $root->id()],
        ['cid' => (int) $grandchild->id(), 'parent_cid' => (int) $child->id()],
      ],
      'ids' => ['cid' => ['type' => 'integer']],
    ]);
    $message = new CollectingMigrateMessage();
    self::assertSame(
      MigrationInterface::RESULT_COMPLETED,
      (new MigrateExecutable($parent_migration, $message))->import(),
      implode("\n", $message->messages),
    );

    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_forum_post');
    $posts = [];
    foreach ($post_ids as $cid => $post_id) {
      $post = $storage->load($post_id);
      self::assertNotNull($post);
      $posts[$cid] = $post;
      $comment = $comments[$cid];
      self::assertSame((string) $comment->getSubject(), (string) $post->get('subject')->value);
      self::assertSame((string) $comment->get('comment_body')->value, (string) $post->get('body')->value);
      self::assertSame((int) $comment->getOwnerId(), (int) $post->getOwnerId());
      self::assertSame((int) $comment->id(), (int) $post->get('thread_position')->value);
      self::assertSame((int) $comment->getCreatedTime(), (int) $post->get('created')->value);
      self::assertSame((int) $comment->getChangedTime(), (int) $post->get('changed')->value);
      self::assertSame((int) $comment->isPublished(), (int) $post->isPublished());
      self::assertSame('published', $post->get('moderation_state')->value);
      self::assertSame(1700000000, (int) $post->get('first_published')->value);
    }

    self::assertEmpty($posts[(int) $root->id()]->get('parent')->target_id, 'A raiz não recebe pai: ' . var_export($posts[(int) $root->id()]->get('parent')->getValue(), TRUE) . ' ids=' . var_export($post_ids, TRUE));
    self::assertSame(
      $post_ids[(int) $root->id()],
      (int) $posts[(int) $child->id()]->get('parent')->target_id,
    );
    self::assertSame(
      $post_ids[(int) $child->id()],
      (int) $posts[(int) $grandchild->id()]->get('parent')->target_id,
    );

    // Replaying both migrations keeps the same destination posts.
    self::assertSame(MigrationInterface::RESULT_COMPLETED, $this->runMigration());
    self::assertSame(
      MigrationInterface::RESULT_COMPLETED,
      (new MigrateExecutable($parent_migration, $message))->import(),
      implode("\n", $message->messages),
    );
    self::assertSame(3, $this->postCount());
    foreach ($comments as $cid => $comment) {
      self::assertSame(
        $post_ids[$cid],
        (int) $this->migration()->getIdMap()->lookupDestinationIds(['cid' => $cid])[0][0],
      );
    }
  }

  /**
   * Rollback deletes imported ForumPosts but preserves legacy source Comments.
   */
  public function testMigrationRollbackPreservesCommentSources(): void {
    $root = $this->comment('Raiz');
    $reply = $this->comment('Resposta', $root);
    $migration = $this->migration();
    $message = new CollectingMigrateMessage();
    $executable = new MigrateExecutable($migration, $message);

    self::assertSame(MigrationInterface::RESULT_COMPLETED, $executable->import(), implode("\n", $message->messages));
    self::assertSame(2, $this->postCount());
    self::assertSame(MigrationInterface::RESULT_COMPLETED, $executable->rollback(), implode("\n", $message->messages));
    self::assertSame(0, $this->postCount());
    self::assertSame(2, count($this->container->get('entity_type.manager')
      ->getStorage('comment')->loadMultiple([(int) $root->id(), (int) $reply->id()])));
    self::assertSame([], $migration->getIdMap()->lookupDestinationIds(['cid' => (int) $root->id()]));
  }

  /**
   * Builds the production migration with a persistent SQL id map.
   */
  private function migration(): Migration {
    $migration = new Migration(
      [
        'source' => ['plugin' => 'aculta_forum_comment'],
        'destination' => ['plugin' => 'aculta_forum_comment_post'],
        'id_map' => ['plugin' => 'sql'],
        'migration_dependencies' => ['required' => [], 'optional' => []],
        'process' => [
          'uid' => 'uid',
          'langcode' => 'langcode',
          'topic/target_id' => 'topic_id',
          'thread_position' => 'cid',
          'created' => 'created',
          'changed' => 'changed',
          'revision_uid' => 'uid',
          'revision_timestamp' => 'changed',
          'first_published' => 'first_published',
          'legacy_author/target_id' => 'legacy_author_id',
        ],
      ],
      'aculta_forum_comment_posts',
      [],
      $this->container->get('plugin.manager.migration'),
      $this->container->get('plugin.manager.migrate.source'),
      $this->container->get('plugin.manager.migrate.process'),
      $this->container->get('plugin.manager.migrate.destination'),
      $this->container->get('plugin.manager.migrate.id_map'),
    );
    $source = new ForumComment(
      ['comment_batch_size' => 50],
      'aculta_forum_comment',
      ['requirements_met' => TRUE],
      $migration,
      $this->container->get('entity_type.manager'),
      new DomainPurposeManager(
        $this->container->get('entity_type.manager'),
        $this->container->get('domain.negotiator'),
        $this->container->get('config.factory'),
        new RequestStack(),
      ),
    );
    $destination = new ForumCommentPost(
      [],
      'aculta_forum_comment_post',
      ['requirements_met' => TRUE],
      $migration,
      $this->container->get('entity_type.manager')->getStorage('aculta_forum_post'),
      ['aculta_forum_post'],
      $this->container->get('entity_field.manager'),
      $this->container->get('plugin.manager.field.field_type'),
      $this->container->get('account_switcher'),
      $this->container->get('entity_type.bundle.info'),
      $this->container->get('entity_type.manager'),
    );
    foreach (['sourcePlugin' => $source, 'destinationPlugin' => $destination] as $property => $value) {
      $reflection = new \ReflectionProperty(Migration::class, $property);
      $reflection->setAccessible(TRUE);
      $reflection->setValue($migration, $value);
    }
    return $migration;
  }

  /**
   * Runs the prepared migration through MigrateExecutable.
   */
  private function runMigration(): int {
    $migration = $this->migration();
    $message = new CollectingMigrateMessage();
    $executable = new MigrateExecutable($migration, $message);
    $result = $executable->import();
    $this->migrationMessages = $message->messages;
    return $result;
  }

  /**
   * Counts stored ForumPosts.
   */
  private function postCount(): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post')->getQuery()->accessCheck(FALSE)->count()->execute();
  }

  /**
   * Creates a published Forum Comment.
   */
  private function comment(string $subject, ?CommentInterface $parent = NULL): CommentInterface {
    $values = [
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $this->topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $this->author->id(),
      'subject' => $subject,
      'comment_body' => ['value' => 'Corpo de ' . $subject, 'format' => 'forum'],
      'field_aculta_published_at' => 1700000000,
      'status' => CommentInterface::PUBLISHED,
    ];
    if ($parent instanceof CommentInterface) {
      $values['pid'] = $parent->id();
    }
    $comment = Comment::create($values);
    $comment->save();
    return $comment;
  }

}

/**
 * Collects migration messages for assertion failures.
 */
final class CollectingMigrateMessage implements MigrateMessageInterface {

  /**
   * Collected messages.
   *
   * @var string[]
   */
  public array $messages = [];

  /**
   * {@inheritdoc}
   */
  public function display($message, $type = 'status'): void {
    $this->messages[] = '[' . $type . '] ' . $message;
  }

}
