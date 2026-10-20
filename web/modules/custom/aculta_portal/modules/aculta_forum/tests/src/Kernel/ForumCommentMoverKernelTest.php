<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Form\ForumCommentMoveForm;
use Drupal\aculta_forum\Service\ForumCommentMover;
use Drupal\aculta_forum\Service\ForumDiscussionWriteGuard;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormState;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\file\Entity\File;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\flag\Entity\Flag;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Utility\Utility;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the integrity and access boundaries of public Forum comment moves.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumCommentMoverKernelTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * Modules required by the Kernel test fixture.
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
    'file',
    'flag',
    'path',
    'path_alias',
    'search_api',
    'search_api_test',
    'options',
    'aculta_forum_comment_move_test',
  ];

  /**
   * The service under test.
   *
   * @var \Drupal\aculta_forum\Service\ForumCommentMover
   */
  private ForumCommentMover $mover;

  /**
   * The account used for authorized move scenarios.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private AccountInterface $account;

  /**
   * Serializes Forum comment writes with tree moves.
   *
   * @var \Drupal\aculta_forum\Service\ForumDiscussionWriteGuard
   */
  private ForumDiscussionWriteGuard $writeGuard;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('file');
    $this->installEntitySchema('flagging');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig([
      'system',
      'filter',
      'node',
      'comment',
      'domain',
      'file',
      'flag',
      'path',
      'path_alias',
      'search_api',
      'user',
    ]);
    $this->container->set('lock', new DatabaseLockBackend($this->container->get('database')));
    $this->config('aculta_forum.settings')->set('comment_move_enabled', TRUE)->save();
    $this->container->get('state')->set('comment.maintain_entity_statistics', TRUE);

    Domain::create([
      'id' => 'forum_aculta_org',
      'hostname' => 'forum.example.test',
      'name' => 'Forum test domain',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
    ])->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
    $this->addField('entity_reference', 'field_domain_source', ['target_type' => 'domain']);
    $this->addField('string', 'field_autor_legado', []);
    $this->addDefaultCommentField(
      'node',
      'aculta_forum_topic',
      ForumCommentMover::FIELD_NAME,
      CommentingStatus::Open,
      ForumCommentMover::COMMENT_TYPE,
    );
    FieldStorageConfig::create([
      'entity_type' => 'comment',
      'field_name' => 'field_test_attachment',
      'type' => 'file',
      'settings' => ['uri_scheme' => 'private'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'comment',
      'bundle' => ForumCommentMover::COMMENT_TYPE,
      'field_name' => 'field_test_attachment',
      'label' => 'Test attachment',
    ])->save();

    $this->account = $this->createMock(AccountInterface::class);
    $this->account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, [
        'access content',
        'access comments',
        'administer comments',
        'move aculta forum comments',
      ], TRUE),
    );
    $this->account->method('id')->willReturn(42);
    $this->account->method('isAnonymous')->willReturn(FALSE);
    $this->account->method('isAuthenticated')->willReturn(TRUE);

    $manager = $this->container->get('entity_type.manager');
    $purpose = new DomainPurposeManager(
      $manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $this->writeGuard = new ForumDiscussionWriteGuard(
      $manager,
      $this->container->get('lock'),
      $this->container->get('database'),
    );
    $this->mover = new ForumCommentMover(
      $manager,
      $this->container->get('config.factory'),
      $this->writeGuard,
      $purpose,
      $this->container->get('lock'),
      $this->container->get('database'),
      $this->container->get('comment.statistics'),
      $this->container->get('cache_tags.invalidator'),
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Moving a deep subtree keeps identity, state and statistics consistent. */
  public function testMovePreservesDeepTreeAndRecalculatesBothHosts(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $root = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $child = $this->comment($source, $root, CommentInterface::PUBLISHED);
    $grandchild = $this->comment($source, $child, CommentInterface::PUBLISHED);
    $source_keeper = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $destination_existing = $this->comment($destination, NULL, CommentInterface::PUBLISHED);
    $identities = [];
    foreach ([$root, $child, $grandchild] as $comment) {
      $identities[(int) $comment->id()] = [
        'uuid' => $comment->uuid(),
        'created' => $comment->getCreatedTime(),
        'changed' => $comment->getChangedTime(),
        'subject' => $comment->getSubject(),
        'body' => $comment->get('comment_body')->value,
      ];
    }

    self::assertSame(3, $this->mover->subtreeCount($root));
    self::assertSame(4, $this->commentCount($source));
    self::assertSame(1, $this->commentCount($destination));
    self::assertSame(3, $this->mover->move((int) $root->id(), (int) $destination->id(), $this->account));

    $storage = $this->container->get('entity_type.manager')->getStorage('comment');
    $moved_root = $storage->load($root->id());
    $moved_child = $storage->load($child->id());
    $moved_grandchild = $storage->load($grandchild->id());
    self::assertSame((int) $destination->id(), (int) $moved_root->getCommentedEntityId());
    self::assertNull($moved_root->getParentComment());
    self::assertSame('02/', $moved_root->getThread());
    self::assertSame('02.00/', $moved_child->getThread());
    self::assertSame('02.00.00/', $moved_grandchild->getThread());
    self::assertSame((int) $moved_root->id(), (int) $moved_child->getParentComment()->id());
    self::assertSame((int) $moved_child->id(), (int) $moved_grandchild->getParentComment()->id());

    foreach ([$moved_root, $moved_child, $moved_grandchild] as $moved) {
      self::assertSame($identities[(int) $moved->id()]['uuid'], $moved->uuid());
      self::assertSame((string) $identities[(int) $moved->id()]['created'], (string) $moved->getCreatedTime());
      self::assertSame((string) $identities[(int) $moved->id()]['changed'], (string) $moved->getChangedTime());
      self::assertSame($identities[(int) $moved->id()]['subject'], $moved->getSubject());
      self::assertSame($identities[(int) $moved->id()]['body'], $moved->get('comment_body')->value);
      self::assertSame(CommentInterface::PUBLISHED, (int) $moved->get('status')->value);
    }
    self::assertSame((int) $source_keeper->id(), (int) Comment::load($source_keeper->id())->id());
    self::assertSame((int) $destination_existing->id(), (int) Comment::load($destination_existing->id())->id());
    self::assertSame(1, $this->commentCount($source));
    self::assertSame(4, $this->commentCount($destination));
  }

  /**
   * Pending comments stay pending and cannot escape the public Forum. */
  public function testMovePreservesPendingStateAndRejectsHistoricalTopics(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $pending = $this->comment($source, NULL, CommentInterface::NOT_PUBLISHED);
    $child = $this->comment($source, $pending, CommentInterface::NOT_PUBLISHED);

    $this->mover->move((int) $pending->id(), (int) $destination->id(), $this->account);
    self::assertSame(CommentInterface::NOT_PUBLISHED, (int) Comment::load($pending->id())->get('status')->value);
    self::assertSame(CommentInterface::NOT_PUBLISHED, (int) Comment::load($child->id())->get('status')->value);
    self::assertSame(0, $this->commentCount($source));
    self::assertSame(0, $this->commentCount($destination));

    $historical = $this->topic('Historical', 'legacy-author-ref');
    $historical_comment = $this->comment($historical, NULL, CommentInterface::PUBLISHED);
    self::assertTrue($this->mover->access($historical_comment, $this->account)->isForbidden());
    $this->expectException(\InvalidArgumentException::class);
    $this->mover->move((int) $historical_comment->id(), (int) $destination->id(), $this->account);
  }

  /**
   * Existing Flag records remain attached to the same Comment after a move.
   */
  public function testMovePreservesFlaggingOnCommentIdentity(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $flagger = User::create(['uid' => 42, 'name' => 'forum-flagger', 'status' => 1]);
    $flagger->save();
    $flag = Flag::create([
      'id' => 'forum_comment_test',
      'label' => 'Forum comment test',
      'entity_type' => 'comment',
      'bundles' => [ForumCommentMover::COMMENT_TYPE],
      'flag_type' => 'entity:comment',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
      'global' => FALSE,
    ]);
    $flag->save();
    $flagging = $this->container->get('flag')->flag($flag, $comment, $flagger);
    $flagging_id = (int) $flagging->id();

    self::assertSame(1, $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account));

    $stored_flagging = $this->container->get('entity_type.manager')->getStorage('flagging')->load($flagging_id);
    self::assertNotFalse($stored_flagging);
    self::assertSame('comment', $stored_flagging->getFlaggableType());
    self::assertSame((int) $comment->id(), (int) $stored_flagging->getFlaggableId());
    self::assertSame((int) $destination->id(), (int) Comment::load($comment->id())->getCommentedEntityId());
    self::assertSame($flagging_id, (int) $this->container->get('flag')->getFlagging($flag, Comment::load($comment->id()), $flagger)->id());
  }

  /**
   * File field usage remains keyed to the same Comment after a move.
   */
  public function testMovePreservesAttachmentFileUsage(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $file = File::create([
      'uid' => 42,
      'filename' => 'forum-attachment.txt',
      'uri' => 'private://forum-attachment.txt',
      'filemime' => 'text/plain',
      'status' => 1,
    ]);
    $file->save();
    $comment->set('field_test_attachment', ['target_id' => $file->id()])->save();
    $file_usage = $this->container->get('file.usage');
    self::assertSame('1', $file_usage->listUsage($file)['file']['comment'][$comment->id()]);

    $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account);

    $stored_file = File::load($file->id());
    $stored_comment = Comment::load($comment->id());
    self::assertSame((int) $file->id(), (int) $stored_comment->get('field_test_attachment')->target_id);
    self::assertSame('1', $file_usage->listUsage($stored_file)['file']['comment'][$comment->id()]);
    self::assertSame((int) $destination->id(), (int) $stored_comment->getCommentedEntityId());
  }

  /**
   * A moved Comment is queued for Search API reindexing under its same ID.
   */
  public function testMoveQueuesCommentForSearchApiReindexing(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    Server::create([
      'name' => 'Forum test server',
      'id' => 'forum_test_server',
      'backend' => 'search_api_test',
    ])->save();
    $index = Index::create([
      'name' => 'Forum comment test index',
      'id' => 'forum_comment_test_index',
      'status' => TRUE,
      'server' => 'forum_test_server',
      'datasource_settings' => ['entity:comment' => []],
      'tracker_settings' => ['default' => []],
      'options' => ['index_directly' => FALSE],
    ]);
    $index->save();
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $item_id = $comment->id() . ':' . $comment->language()->getId();
    $combined_item_id = Utility::createCombinedId('entity:comment', $item_id);
    $tracker = $index->getTrackerInstance();
    self::assertSame(1, $tracker->getTotalItemsCount('entity:comment'));
    self::assertSame([$combined_item_id], $tracker->getRemainingItems(-1, 'entity:comment'));
    $tracker->trackItemsIndexed([$combined_item_id]);
    self::assertSame(1, $tracker->getIndexedItemsCount('entity:comment'));
    self::assertSame(0, $tracker->getRemainingItemsCount('entity:comment'));

    $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account);

    self::assertSame(0, $tracker->getIndexedItemsCount('entity:comment'));
    self::assertSame(1, $tracker->getRemainingItemsCount('entity:comment'));
    self::assertSame([$combined_item_id], $tracker->getRemainingItems(-1, 'entity:comment'));
  }

  /**
   * The operation stays unavailable while its runtime setting is disabled.
   */
  public function testMoveIsUnavailableWhenFeatureFlagIsDisabled(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $this->config('aculta_forum.settings')->set('comment_move_enabled', FALSE)->save();

    self::assertTrue($this->mover->access($comment, $this->account)->isForbidden());
    $this->expectException(\InvalidArgumentException::class);
    $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account);
  }

  /**
   * Form API submission moves only after the explicit confirmation.
   */
  public function testConfirmedFormApiSubmissionMovesTheComment(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $form_state = new FormState();
    $form_state->setValues([
      'destination' => (string) $destination->id(),
      'confirm_move' => 1,
    ]);
    $form = new ForumCommentMoveForm($this->mover, $this->account);

    $this->container->get('form_builder')->submitForm($form, $form_state, $comment);

    self::assertSame([], $form_state->getErrors());
    self::assertSame((int) $destination->id(), (int) Comment::load($comment->id())->getCommentedEntityId());
  }

  /**
   * Form API rejects an unconfirmed move without changing the comment host.
   */
  public function testFormApiRejectsMoveWithoutConfirmation(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $form_state = new FormState();
    $form_state->setValues([
      'destination' => (string) $destination->id(),
    ]);
    $form = new ForumCommentMoveForm($this->mover, $this->account);

    $this->container->get('form_builder')->submitForm($form, $form_state, $comment);

    self::assertNotSame([], $form_state->getErrors());
    self::assertSame((int) $source->id(), (int) Comment::load($comment->id())->getCommentedEntityId());
  }

  /**
   * A stale reply form cannot recreate a parent link across topics. */
  public function testStaleReplyWriteIsRejectedAfterItsParentMoves(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $root = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $reply = $this->comment($source, $root, CommentInterface::PUBLISHED);
    // Keep an isolated copy: entity storage may return the same instance to
    // the mover later in this request.
    $stale_reply = clone $reply;

    $this->mover->move((int) $root->id(), (int) $destination->id(), $this->account);

    $this->expectException(\InvalidArgumentException::class);
    $this->writeGuard->beforeCommentSave($stale_reply);
  }

  /**
   * A stale delete form cannot remove a comment after it was moved. */
  public function testStaleCommentDeleteIsRejectedAfterMove(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $stale_comment = Comment::load($comment->id());

    $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account);

    $this->expectException(\InvalidArgumentException::class);
    $this->writeGuard->beforeCommentDelete($stale_comment);
  }

  /**
   * An independent backend is blocked until the root transaction completes.
   */
  public function testCommentWriteLockBlocksIndependentBackendUntilRootTransactionEnds(): void {
    $topic = $this->topic('Topic');
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => (int) $topic->id(),
      'field_name' => ForumCommentMover::FIELD_NAME,
      'comment_type' => ForumCommentMover::COMMENT_TYPE,
      'uid' => 42,
      'subject' => 'Reply subject',
      'comment_body' => ['value' => 'Reply body', 'format' => 'plain_text'],
      'status' => CommentInterface::PUBLISHED,
    ]);
    $lock_name = 'aculta_forum:topic-write:' . $topic->id();
    self::assertInstanceOf(DatabaseLockBackend::class, $this->container->get('lock'));
    self::assertSame(ForumCommentMover::COMMENT_TYPE, $comment->bundle());
    self::assertSame('node', $comment->getCommentedEntityTypeId());
    self::assertSame((int) $topic->id(), (int) $comment->getCommentedEntityId());
    self::assertSame(ForumCommentMover::FIELD_NAME, $comment->getFieldName());
    $this->writeGuard->beforeCommentSave($comment);

    $connection_info = Database::getConnectionInfo('default');
    Database::addConnectionInfo('forum_lock_observer', 'default', $connection_info['default']);
    $observer_connection = Database::getConnection('default', 'forum_lock_observer');
    $observer = new DatabaseLockBackend($observer_connection);
    try {
      $transaction = $this->container->get('database')->startTransaction();
      $this->writeGuard->afterCommentSave($comment);
      self::assertFalse($observer->acquire($lock_name));

      $transaction->commitOrRelease();
      unset($transaction);
      self::assertTrue($observer->acquire($lock_name));
    }
    finally {
      $observer->releaseAll();
      Database::removeConnection('forum_lock_observer');
    }
  }

  /**
   * A save failure after the root write rolls the entire operation back. */
  public function testPartialSaveFailureRollsBackTheWholeSubtree(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $root = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $child = $this->comment($source, $root, CommentInterface::PUBLISHED);
    $this->container->get('state')->set('aculta_forum_comment_move_test.fail_cid', (int) $child->id());
    $this->container->get('state')->set('aculta_forum_comment_move_test.destination', (int) $destination->id());

    try {
      $this->mover->move((int) $root->id(), (int) $destination->id(), $this->account);
      self::fail('The fixture hook should throw after the root Comment was saved.');
    }
    catch (\Throwable $exception) {
      self::assertStringContainsString('Injected Forum comment move failure.', $exception->getMessage());
    }
    finally {
      $this->container->get('state')->delete('aculta_forum_comment_move_test.fail_cid');
      $this->container->get('state')->delete('aculta_forum_comment_move_test.destination');
    }

    self::assertSame((int) $source->id(), (int) Comment::load($root->id())->getCommentedEntityId());
    self::assertSame((int) $source->id(), (int) Comment::load($child->id())->getCommentedEntityId());
    self::assertSame('01/', Comment::load($root->id())->getThread());
    self::assertSame('01.00/', Comment::load($child->id())->getThread());
    self::assertSame(2, $this->commentCount($source));
    self::assertSame(0, $this->commentCount($destination));
  }

  /**
   * Creates a public Forum topic for a test scenario.
   */
  private function topic(string $title, string $legacyAuthor = ''): Node {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => $title,
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_autor_legado' => $legacyAuthor === '' ? [] : ['value' => $legacyAuthor],
      ForumCommentMover::FIELD_NAME => ['status' => CommentingStatus::Open->value],
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * Creates a Comment attached to the host and optional parent.
   */
  private function comment(Node $host, ?CommentInterface $parent, int $status): Comment {
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => (int) $host->id(),
      'field_name' => ForumCommentMover::FIELD_NAME,
      'comment_type' => ForumCommentMover::COMMENT_TYPE,
      'pid' => $parent?->id(),
      'uid' => 42,
      'subject' => 'Reply subject',
      'comment_body' => ['value' => 'Reply body', 'format' => 'plain_text'],
      'status' => $status,
    ]);
    $comment->save();
    return $comment;
  }

  /**
   * Reads Core's published-comment aggregate for a host.
   */
  private function commentCount(Node $host): int {
    $records = $this->container->get('comment.statistics')->read([(int) $host->id() => $host], 'node');
    foreach ($records as $record) {
      if ($record->field_name === ForumCommentMover::FIELD_NAME) {
        return (int) $record->comment_count;
      }
    }
    return 0;
  }

  /**
   * Adds a field storage and its bundle field configuration.
   */
  private function addField(string $type, string $name, array $settings): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

}
