<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Form\ForumCommentMoveForm;
use Drupal\aculta_forum\Event\ForumCommentSubtreeMovedEvent;
use Drupal\aculta_forum\Service\ForumCommentMover;
use Drupal\aculta_forum\Service\ForumDiscussionWriteGuard;
use Drupal\aculta_forum\Origin\CoreDiscussionOriginClassifier;
use Drupal\aculta_forum\Origin\DiscussionOriginResolver;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\file\Entity\File;
use Drupal\flag\FlaggingInterface;
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
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests the integrity and access boundaries of public Forum comment moves.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumCommentMoverKernelTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * The persisted Drupal user used as the current content owner.
   */
  private int $ownerId;

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
    'editor',
    'text',
    'node',
    'comment',
    'domain',
    'file',
    'flag',
    'history',
    'language',
    'path',
    'path_alias',
    'search_api',
    'search_api_test',
    'options',
    'aculta_forum_test_routes',
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
    $this->container->get('language.default')->set(new Language([
      'id' => 'pt-br',
      'name' => 'Português (Brasil)',
      'direction' => LanguageInterface::DIRECTION_LTR,
      'weight' => 0,
    ]));
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
    $this->installSchema('history', ['history']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig([
      'system',
      'filter',
      'node',
      'comment',
      'domain',
      'file',
      'flag',
      'history',
      'language',
      'path',
      'path_alias',
      'search_api',
      'user',
    ]);
    $owner = User::create(['name' => 'forum-comment-move-owner', 'status' => 1]);
    $owner->save();
    $this->ownerId = (int) $owner->id();
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
    Domain::create([
      'id' => 'aculta_org',
      'hostname' => 'main.example.test',
      'name' => 'Main test domain',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => TRUE,
    ])->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
    $this->addField('entity_reference', 'field_domain_source', ['target_type' => 'domain']);
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
    $this->account->method('id')->willReturn($this->ownerId);
    $this->account->method('isAnonymous')->willReturn(FALSE);
    $this->account->method('isAuthenticated')->willReturn(TRUE);

    $manager = $this->container->get('entity_type.manager');
    $purpose = new DomainSiteManager(
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
      new DiscussionOriginResolver([
        new CoreDiscussionOriginClassifier(new DiscussionContextResolver([])),
      ]),
      $purpose,
      $this->container->get('lock'),
      $this->container->get('database'),
      $this->container->get('comment.statistics'),
      $this->container->get('cache_tags.invalidator'),
      $this->container->get('event_dispatcher'),
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
    $move_event = NULL;
    $this->container->get('event_dispatcher')->addListener(
      ForumCommentSubtreeMovedEvent::NAME,
      static function (ForumCommentSubtreeMovedEvent $event) use (&$move_event): void {
        $move_event = $event;
      },
    );
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
    self::assertInstanceOf(ForumCommentSubtreeMovedEvent::class, $move_event);
    self::assertSame((int) $source->id(), $move_event->sourceTopicId);
    self::assertSame((int) $destination->id(), $move_event->destinationTopicId);
    self::assertTrue($move_event->containsPublishedComment);
    self::assertEqualsCanonicalizing(
      [(int) $root->id(), (int) $child->id(), (int) $grandchild->id()],
      $move_event->commentIds,
    );

    $storage = $this->container->get('entity_type.manager')->getStorage('comment');
    $moved_root = $this->requireComment($storage->load($this->entityId($root)));
    $moved_child = $this->requireComment($storage->load($this->entityId($child)));
    $moved_grandchild = $this->requireComment($storage->load($this->entityId($grandchild)));
    self::assertSame((int) $destination->id(), (int) $moved_root->getCommentedEntityId());
    self::assertNull($moved_root->getParentComment());
    self::assertSame('02/', $moved_root->getThread());
    $permalink = $moved_root->permalink();
    self::assertSame('entity.comment.canonical', $permalink->getRouteName());
    self::assertSame('comment-' . $moved_root->id(), $permalink->getOption('fragment'));
    self::assertSame('02.00/', $moved_child->getThread());
    self::assertSame('02.00.00/', $moved_grandchild->getThread());
    self::assertSame((int) $moved_root->id(), (int) $this->requireComment($moved_child->getParentComment())->id());
    self::assertSame((int) $moved_child->id(), (int) $this->requireComment($moved_grandchild->getParentComment())->id());

    foreach ([$moved_root, $moved_child, $moved_grandchild] as $moved) {
      self::assertSame($identities[(int) $moved->id()]['uuid'], $moved->uuid());
      self::assertSame((string) $identities[(int) $moved->id()]['created'], (string) $moved->getCreatedTime());
      self::assertSame((string) $identities[(int) $moved->id()]['changed'], (string) $moved->getChangedTime());
      self::assertSame($identities[(int) $moved->id()]['subject'], $moved->getSubject());
      self::assertSame($identities[(int) $moved->id()]['body'], $moved->get('comment_body')->value);
      self::assertSame(CommentInterface::PUBLISHED, $this->integerValue($moved->get('status')->value));
    }
    self::assertSame((int) $source_keeper->id(), (int) $this->loadComment($source_keeper->id())->id());
    self::assertSame((int) $destination_existing->id(), (int) $this->loadComment($destination_existing->id())->id());
    self::assertSame(1, $this->commentCount($source));
    self::assertSame(4, $this->commentCount($destination));
  }

  /**
   * Moving a subtree with sibling branches preserves each branch's thread path.
   */
  public function testMovePreservesSiblingBranchesAtMultipleDepths(): void {
    $source = $this->topic('Branch source');
    $destination = $this->topic('Branch destination');
    $root = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $left = $this->comment($source, $root, CommentInterface::PUBLISHED);
    $left_leaf = $this->comment($source, $left, CommentInterface::PUBLISHED);
    $right = $this->comment($source, $root, CommentInterface::PUBLISHED);
    $right_leaf = $this->comment($source, $right, CommentInterface::PUBLISHED);
    $this->comment($destination, NULL, CommentInterface::PUBLISHED);

    self::assertSame(5, $this->mover->move((int) $root->id(), (int) $destination->id(), $this->account));

    $storage = $this->container->get('entity_type.manager')->getStorage('comment');
    $moved_root = $this->requireComment($storage->load($this->entityId($root)));
    $moved_left = $this->requireComment($storage->load($this->entityId($left)));
    $moved_left_leaf = $this->requireComment($storage->load($this->entityId($left_leaf)));
    $moved_right = $this->requireComment($storage->load($this->entityId($right)));
    $moved_right_leaf = $this->requireComment($storage->load($this->entityId($right_leaf)));

    self::assertSame('02/', $moved_root->getThread());
    self::assertSame('02.00/', $moved_left->getThread());
    self::assertSame('02.00.00/', $moved_left_leaf->getThread());
    self::assertSame('02.01/', $moved_right->getThread());
    self::assertSame('02.01.00/', $moved_right_leaf->getThread());
    self::assertSame((int) $moved_root->id(), (int) $this->requireComment($moved_left->getParentComment())->id());
    self::assertSame((int) $moved_root->id(), (int) $this->requireComment($moved_right->getParentComment())->id());
    self::assertSame((int) $moved_left->id(), (int) $this->requireComment($moved_left_leaf->getParentComment())->id());
    self::assertSame((int) $moved_right->id(), (int) $this->requireComment($moved_right_leaf->getParentComment())->id());
    self::assertSame(0, $this->commentCount($source));
    self::assertSame(6, $this->commentCount($destination));
  }

  /**
   * Pending comments stay pending and cannot escape the public Forum. */
  public function testMovePreservesPendingStateAndRejectsUnclassifiedTopics(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $pending = $this->comment($source, NULL, CommentInterface::NOT_PUBLISHED);
    $child = $this->comment($source, $pending, CommentInterface::NOT_PUBLISHED);
    $event_dispatched = FALSE;
    $this->container->get('event_dispatcher')->addListener(
      ForumCommentSubtreeMovedEvent::NAME,
      static function () use (&$event_dispatched): void {
        $event_dispatched = TRUE;
      },
    );

    $this->mover->move((int) $pending->id(), (int) $destination->id(), $this->account);
    self::assertFalse($event_dispatched);
    self::assertSame(CommentInterface::NOT_PUBLISHED, $this->integerValue($this->loadComment($pending->id())->get('status')->value));
    self::assertSame(CommentInterface::NOT_PUBLISHED, $this->integerValue($this->loadComment($child->id())->get('status')->value));
    self::assertSame(0, $this->commentCount($source));
    self::assertSame(0, $this->commentCount($destination));

    $unclassified = $this->topic('Unclassified', CommentingStatus::Open, 'forum_aculta_org', FALSE);
    $unclassified_comment = $this->comment($unclassified, NULL, CommentInterface::PUBLISHED);
    self::assertTrue($this->mover->access($unclassified_comment, $this->account)->isForbidden());
    $this->expectException(\InvalidArgumentException::class);
    $this->mover->move((int) $unclassified_comment->id(), (int) $destination->id(), $this->account);
  }

  /**
   * Closed or cross-purpose destinations cannot alter the Comment host.
   */
  public function testMoveRejectsClosedAndCrossPurposeDestinations(): void {
    $source = $this->topic('Source');
    $closed = $this->topic('Closed destination', CommentingStatus::Closed);
    $cross_purpose = $this->topic('Main destination', CommentingStatus::Open, 'aculta_org');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);

    foreach ([$closed, $cross_purpose] as $destination) {
      try {
        $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account);
        self::fail('A closed or cross-purpose destination must be rejected.');
      }
      catch (\InvalidArgumentException) {
        self::assertSame((int) $source->id(), (int) $this->loadComment($comment->id())->getCommentedEntityId());
      }
    }
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
    self::assertInstanceOf(FlaggingInterface::class, $stored_flagging);
    $stored_flagging = $this->requireFlagging($stored_flagging);
    self::assertSame('comment', $stored_flagging->getFlaggableType());
    self::assertSame((int) $comment->id(), (int) $stored_flagging->getFlaggableId());
    self::assertSame((int) $destination->id(), (int) $this->loadComment($comment->id())->getCommentedEntityId());
    $current_flagging = $this->container->get('flag')->getFlagging($flag, $this->loadComment($comment->id()), $flagger);
    self::assertInstanceOf(FlaggingInterface::class, $current_flagging);
    self::assertSame($flagging_id, (int) $this->requireFlagging($current_flagging)->id());
  }

  /**
   * Moving a reply transfers its unread status without rewriting read receipts.
   */
  public function testMoveTransfersUnreadCommentToDestinationWithoutChangingHistory(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $role = $this->requireRole($this->container->get('entity_type.manager')->getStorage('user_role')->load('authenticated'));
    $role->grantPermission('access content');
    $role->grantPermission('access comments');
    $role->save();
    $reader = User::create([
      'name' => 'forum-history-reader',
      'mail' => 'forum-history-reader@example.test',
      'status' => 1,
      'roles' => ['authenticated'],
    ]);
    $reader->save();
    $this->container->get('current_user')->setAccount($reader);

    $timestamp = $this->container->get('datetime.time')->getRequestTime();
    history_write($source->id(), $reader);
    history_write($destination->id(), $reader);
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $comment->set('created', $timestamp + 1)->save();
    $history = $this->container->get('Drupal\\history\\HistoryManager');

    self::assertSame(1, $history->getCountNewComments($source, ForumCommentMover::FIELD_NAME));
    self::assertSame(0, $history->getCountNewComments($destination, ForumCommentMover::FIELD_NAME));
    $receipts_before = history_read_multiple([(int) $source->id(), (int) $destination->id()]);

    $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account);

    self::assertSame(0, $history->getCountNewComments($this->loadNode($source->id()), ForumCommentMover::FIELD_NAME));
    self::assertSame(1, $history->getCountNewComments($this->loadNode($destination->id()), ForumCommentMover::FIELD_NAME));
    self::assertSame($receipts_before, history_read_multiple([(int) $source->id(), (int) $destination->id()]));
    self::assertSame((int) $destination->id(), (int) $this->loadComment($comment->id())->getCommentedEntityId());
  }

  /**
   * Moving a reply preserves the platform language and comment content.
   */
  public function testMovePreservesPlatformLanguage(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    self::assertSame('pt-br', $comment->language()->getId());
    self::assertSame(1, $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account));

    $moved = $this->loadComment($comment->id());
    self::assertSame((int) $destination->id(), (int) $moved->getCommentedEntityId());
    self::assertSame('pt-br', $moved->language()->getId());
    self::assertSame('Reply subject', $moved->getUntranslated()->getSubject());
    self::assertSame('Reply body', $moved->getUntranslated()->get('comment_body')->value);
  }

  /**
   * File field usage remains keyed to the same Comment after a move.
   */
  public function testMovePreservesAttachmentFileUsage(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $file = File::create([
      'uid' => $this->ownerId,
      'filename' => 'forum-attachment.txt',
      'uri' => 'private://forum-attachment.txt',
      'filemime' => 'text/plain',
      'status' => 1,
    ]);
    $file->save();
    $comment->set('field_test_attachment', ['target_id' => $file->id()])->save();
    $file_usage = $this->container->get('file.usage');
    $comment_id = $this->entityId($comment);
    $this->assertFileCommentUsage($file_usage->listUsage($file), $comment_id);

    $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account);

    $stored_file = $this->loadFile($file->id());
    $stored_comment = $this->loadComment($comment->id());
    self::assertSame((int) $file->id(), (int) $stored_comment->get('field_test_attachment')->target_id);
    $this->assertFileCommentUsage($file_usage->listUsage($stored_file), $comment_id);
    self::assertSame((int) $destination->id(), (int) $stored_comment->getCommentedEntityId());
  }

  /**
   * CKEditor inline image usage stays attached to the same Comment after move.
   */
  public function testMovePreservesInlineEditorImageUsage(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $file = File::create([
      'uid' => 42,
      'filename' => 'forum-inline-image.png',
      'uri' => 'public://inline-images/forum-inline-image.png',
      'filemime' => 'image/png',
      'status' => 1,
    ]);
    $file->save();
    $comment->set('comment_body', [
      'value' => '<p>Imagem da resposta</p><img src="/sites/default/files/inline-images/forum-inline-image.png" data-entity-type="file" data-entity-uuid="' . $file->uuid() . '">',
      'format' => 'usuario_aculta',
    ])->save();

    $file_usage = $this->container->get('file.usage');
    $expected_usage = ['editor' => ['comment' => [(int) $comment->id() => '1']]];
    self::assertSame($expected_usage, $file_usage->listUsage($file));

    $this->mover->move((int) $comment->id(), (int) $destination->id(), $this->account);

    $stored_file = $this->loadFile($file->id());
    $stored_comment = $this->loadComment($comment->id());
    self::assertSame($expected_usage, $file_usage->listUsage($stored_file));
    $body = $stored_comment->get('comment_body')->value;
    $uuid = $file->uuid();
    self::assertIsString($uuid);
    self::assertIsString($body);
    self::assertStringContainsString($uuid, $body);
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
   * Denied moves require permission and leave the host intact.
   */
  public function testMoveRequiresDedicatedPermission(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $unauthorized = $this->createMock(AccountInterface::class);
    $unauthorized->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, [
        'access content',
        'access comments',
      ], TRUE),
    );
    $unauthorized->method('id')->willReturn(43);
    $unauthorized->method('isAnonymous')->willReturn(FALSE);
    $unauthorized->method('isAuthenticated')->willReturn(TRUE);

    self::assertTrue($this->mover->access($comment, $unauthorized)->isForbidden());
    self::assertSame([], $this->mover->destinations($comment, $unauthorized));
    try {
      $this->mover->move((int) $comment->id(), (int) $destination->id(), $unauthorized);
      self::fail('A user without the move permission must be denied.');
    }
    catch (\InvalidArgumentException) {
      self::assertSame((int) $source->id(), (int) $this->loadComment($comment->id())->getCommentedEntityId());
    }
  }

  /**
   * The move permission cannot replace access to the source topic.
   */
  public function testMoveRequiresSourceTopicAccess(): void {
    $source = $this->topic('Source');
    $destination = $this->topic('Destination');
    $comment = $this->comment($source, NULL, CommentInterface::PUBLISHED);
    $without_topic_access = $this->createMock(AccountInterface::class);
    $without_topic_access->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, [
        'access comments',
        'move aculta forum comments',
      ], TRUE),
    );
    $without_topic_access->method('id')->willReturn(44);
    $without_topic_access->method('isAnonymous')->willReturn(FALSE);
    $without_topic_access->method('isAuthenticated')->willReturn(TRUE);

    self::assertTrue($this->mover->access($comment, $without_topic_access)->isForbidden());
    self::assertSame([], $this->mover->destinations($comment, $without_topic_access));
    $form = new ForumCommentMoveForm($this->mover, $without_topic_access);
    try {
      $form->buildForm([], new FormState(), $comment);
      self::fail('The form must not expose destinations without source access.');
    }
    catch (AccessDeniedHttpException) {
      self::assertSame((int) $source->id(), (int) $this->loadComment($comment->id())->getCommentedEntityId());
    }
    try {
      $this->mover->move((int) $comment->id(), (int) $destination->id(), $without_topic_access);
      self::fail('Move permission must not grant access to the source topic.');
    }
    catch (\InvalidArgumentException) {
      self::assertSame((int) $source->id(), (int) $this->loadComment($comment->id())->getCommentedEntityId());
    }
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
    self::assertSame((int) $destination->id(), (int) $this->loadComment($comment->id())->getCommentedEntityId());
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
    self::assertSame((int) $source->id(), (int) $this->loadComment($comment->id())->getCommentedEntityId());
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
    $stale_comment = $this->loadComment($comment->id());

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

    self::assertSame((int) $source->id(), (int) $this->loadComment($root->id())->getCommentedEntityId());
    self::assertSame((int) $source->id(), (int) $this->loadComment($child->id())->getCommentedEntityId());
    self::assertSame('01/', $this->loadComment($root->id())->getThread());
    self::assertSame('01.00/', $this->loadComment($child->id())->getThread());
    self::assertSame(2, $this->commentCount($source));
    self::assertSame(0, $this->commentCount($destination));
  }

  /**
   * Creates a public Forum topic for a test scenario.
   */
  private function topic(
    string $title,
    CommentingStatus $commentStatus = CommentingStatus::Open,
    string $domainId = 'forum_aculta_org',
    bool $currentOwner = TRUE,
  ): Node {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => $title,
      'status' => 1,
      'uid' => $currentOwner ? $this->ownerId : 0,
      'field_domain_source' => ['target_id' => $domainId],
      ForumCommentMover::FIELD_NAME => ['status' => $commentStatus->value],
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
      'uid' => $this->ownerId,
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
      $values = get_object_vars($record);
      if (($values['field_name'] ?? NULL) === ForumCommentMover::FIELD_NAME) {
        return $this->integerValue($values['comment_count'] ?? NULL);
      }
    }
    return 0;
  }

  /**
   * Adds a field storage and its bundle field configuration.
   *
   * @param string $type
   *   The field type.
   * @param string $name
   *   The field machine name.
   * @param array<string, mixed> $settings
   *   Field storage settings.
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

  /**
   * Loads a saved Comment or fails the test with a clear diagnostic.
   */
  private function loadComment(int|string|null $id): Comment {
    if ($id === NULL) {
      throw new \UnexpectedValueException('The saved comment has no ID.');
    }
    $comment = Comment::load($id);
    if (!$comment instanceof Comment) {
      throw new \UnexpectedValueException('The saved comment could not be loaded.');
    }
    return $comment;
  }

  /**
   * Narrows an entity to a saved Comment.
   */
  private function requireComment(mixed $entity): Comment {
    if (!$entity instanceof Comment) {
      throw new \UnexpectedValueException('Expected a saved Comment entity.');
    }
    return $entity;
  }

  /**
   * Loads a saved topic or fails the test with a clear diagnostic.
   */
  private function loadNode(int|string|null $id): Node {
    if ($id === NULL) {
      throw new \UnexpectedValueException('The saved topic has no ID.');
    }
    $node = Node::load($id);
    if (!$node instanceof Node) {
      throw new \UnexpectedValueException('The saved topic could not be loaded.');
    }
    return $node;
  }

  /**
   * Loads a saved File or fails the test with a clear diagnostic.
   */
  private function loadFile(int|string|null $id): File {
    if ($id === NULL) {
      throw new \UnexpectedValueException('The saved file has no ID.');
    }
    $file = File::load($id);
    if (!$file instanceof File) {
      throw new \UnexpectedValueException('The saved file could not be loaded.');
    }
    return $file;
  }

  /**
   * Narrows an entity to a saved Flagging.
   */
  private function requireFlagging(mixed $entity): FlaggingInterface {
    if (!$entity instanceof FlaggingInterface) {
      throw new \UnexpectedValueException('Expected a saved Flagging entity.');
    }
    return $entity;
  }

  /**
   * Narrows an entity to a Drupal Role.
   */
  private function requireRole(mixed $entity): Role {
    if (!$entity instanceof Role) {
      throw new \UnexpectedValueException('The authenticated role could not be loaded.');
    }
    return $entity;
  }

  /**
   * Returns the saved identifier for a Comment.
   */
  private function entityId(CommentInterface $comment): int|string {
    $id = $comment->id();
    if (!is_int($id) && !is_string($id)) {
      throw new \UnexpectedValueException('The saved comment has no scalar ID.');
    }
    return $id;
  }

  /**
   * Converts an integer-like value and rejects other scalar forms.
   */
  private function integerValue(mixed $value): int {
    if (is_int($value)) {
      return $value;
    }
    if (is_string($value) && preg_match('/^-?\\d+$/D', $value) === 1) {
      return (int) $value;
    }
    throw new \UnexpectedValueException('Expected an integer value.');
  }

  /**
   * Asserts that a File usage record points to the supplied Comment.
   *
   * @param array<mixed> $usage
   *   File usage data keyed by type and entity ID.
   * @param int|string $commentId
   *   The expected Comment identifier.
   */
  private function assertFileCommentUsage(array $usage, int|string $commentId): void {
    $file_usage = $usage['file'] ?? NULL;
    if (!is_array($file_usage)) {
      throw new \UnexpectedValueException('File usage data is missing.');
    }
    $comment_usage = $file_usage['comment'] ?? NULL;
    if (!is_array($comment_usage)) {
      throw new \UnexpectedValueException('Comment file usage data is missing.');
    }
    self::assertSame('1', $comment_usage[$commentId] ?? NULL);
  }

}
