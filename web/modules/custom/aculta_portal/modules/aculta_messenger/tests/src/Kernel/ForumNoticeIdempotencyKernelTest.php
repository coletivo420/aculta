<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisoRetencao;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_messenger\Database\ForumCommentNoticeSchema;
use Drupal\aculta_messenger\Database\ForumNoticeEventSchema;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_messenger\Hook\AvisoRetencaoHooks;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\aculta_messenger\Hook\ForumAvisoHooks;
use Drupal\comment\CommentInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Fixes the no-duplicate notice behavior of the existing Forum events.
 *
 * Every scenario drives the production service through the same hooks that
 * receive the Forum events. The only mocked collaborators are the ones that
 * would otherwise require the full Messenger module: the flag service and the
 * notice access policy. Entities, revision markers and counters are real.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class ForumNoticeIdempotencyKernelTest extends KernelTestBase {

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
    'aculta_forum_post_test',
    'aculta_messenger_aviso_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_forum_post');
    $this->installEntitySchema('aculta_aviso');
    $this->installSchema('aculta_messenger_aviso_test', [
      ForumCommentNoticeSchema::TABLE,
      ForumNoticeEventSchema::TABLE,
    ]);
    $this->installSchema('node', ['node_access']);
  }

  /**
   * Counts one notice per follower and never for the author or uid 0.
   */
  public function testFirstPublicationRegistersOneNoticePerFollower(): void {
    $author = $this->createUser('autor');
    $first = $this->createUser('seguidora_um');
    $second = $this->createUser('seguidor_dois');
    $topic = $this->createTopic('Tópico com seguidores');
    $avisos = $this->createAvisoService([
      (int) $author->id(),
      0,
      (int) $first->id(),
      (int) $second->id(),
    ]);
    $post = $this->createPost($topic, (int) $author->id(), TRUE);

    (new ForumAvisoHooks($avisos))->postInsert($post);

    foreach ([$first, $second] as $follower) {
      $notices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
      self::assertCount(1, $notices);
      $aviso = $notices[0];
      self::assertSame(1, (int) $aviso->get('contador_site')->value);
      self::assertSame(1, (int) $aviso->get('contador_email')->value);
      self::assertSame(
        (int) $post->getRevisionId(),
        (int) $aviso->get('ultimo_post')->value,
      );
      self::assertSame('Tópico com seguidores', $aviso->get('titulo')->value);
    }

    self::assertCount(
      0,
      $this->loadNotices((int) $author->id(), (int) $topic->id()),
    );
  }

  /**
   * Repeating the same insert event never increases the stored counter.
   */
  public function testRepeatedDeliveryOfTheSameEventIsIgnored(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $avisos = $this->createAvisoService([(int) $follower->id()]);
    $post = $this->createPost($topic, (int) $author->id(), TRUE);
    $hooks = new ForumAvisoHooks($avisos);

    $hooks->postInsert($post);
    $hooks->postInsert($post);
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post');
    $hooks->postInsert($storage->load($post->id()));

    $notices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $notices);
    self::assertSame(1, (int) $notices[0]->get('contador_site')->value);
    self::assertSame(0, $avisos->registrarPost($post));
  }

  /**
   * Pending posts and non-default revisions never register a notice.
   */
  public function testPendingAndNonDefaultRevisionsRegisterNothing(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $avisos = $this->createAvisoService([(int) $follower->id()]);
    $hooks = new ForumAvisoHooks($avisos);

    $pending = $this->createPost($topic, (int) $author->id(), FALSE);
    $hooks->postInsert($pending);
    $hooks->postUpdate($pending);

    $published = $this->createPost($topic, (int) $author->id(), TRUE);
    $published->setNewRevision(TRUE);
    $published->isDefaultRevision(FALSE);
    $published->save();
    self::assertSame(0, $avisos->registrarPost($published));

    self::assertCount(
      0,
      $this->loadNotices((int) $follower->id(), (int) $topic->id()),
    );
  }

  /**
   * A first publication reported as an update counts exactly once.
   */
  public function testFirstPublicationUpdateCountsOnce(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $avisos = $this->createAvisoService([(int) $follower->id()]);
    $hooks = new ForumAvisoHooks($avisos);
    $post = $this->createPost($topic, (int) $author->id(), FALSE);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post');
    $before = $storage->loadUnchanged($post->id());
    $previousRevision = (int) $post->getRevisionId();
    $post->set('status', TRUE);
    $post->set('first_published', time());
    $post->setNewRevision(TRUE);
    $post->save();
    $post->setOriginal($before);
    self::assertGreaterThan($previousRevision, (int) $post->getRevisionId());

    $hooks->postUpdate($post);
    $hooks->postUpdate($post);

    $notices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $notices);
    self::assertSame(1, (int) $notices[0]->get('contador_site')->value);
    self::assertSame(
      (int) $post->getRevisionId(),
      (int) $notices[0]->get('ultimo_post')->value,
    );
  }

  /**
   * A delayed approval counts after a newer post was already published.
   */
  public function testOutOfOrderPostApprovalCountsEachPublicationOnce(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $avisos = $this->createAvisoService([(int) $follower->id()]);
    $hooks = new ForumAvisoHooks($avisos);

    // This post is created first but remains pending until after the second
    // post has already been published and counted.
    $delayed = $this->createPost($topic, (int) $author->id(), FALSE);
    $newer = $this->createPost($topic, (int) $author->id(), TRUE);
    $hooks->postInsert($newer);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post');
    $beforeApproval = $storage->loadUnchanged($delayed->id());
    $delayed->set('status', TRUE);
    $delayed->set('first_published', time());
    $delayed->setNewRevision(TRUE);
    $delayed->save();
    $delayed->setOriginal($beforeApproval);

    $hooks->postUpdate($delayed);
    $hooks->postUpdate($delayed);

    $notices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $notices);
    self::assertSame(2, (int) $notices[0]->get('contador_site')->value);
    self::assertSame(2, (int) $notices[0]->get('contador_email')->value);
    self::assertSame(
      (int) $delayed->getRevisionId(),
      (int) $notices[0]->get('ultimo_post')->value,
    );
    self::assertGreaterThan(
      (int) $newer->getRevisionId(),
      (int) $delayed->getRevisionId(),
    );
  }

  /**
   * Counts both replies when legacy comment approvals arrive out of order.
   */
  public function testOutOfOrderCommentApprovalCountsBothReplies(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $avisos = $this->createAvisoService(
      [(int) $follower->id(), (int) $author->id()],
      $this->createAccessPolicy(canViewComment: TRUE),
    );

    // The second comment is approved first; the first comment is approved
    // after it. Both approvals are distinct publish events and should count.
    self::assertSame(1, $avisos->registrarResposta(
      $this->createPublishedComment($topic, (int) $author->id(), 20),
    ));
    $delayed = $this->createPublishedComment($topic, (int) $author->id(), 10);
    self::assertSame(1, $avisos->registrarResposta($delayed));
    self::assertSame(0, $avisos->registrarResposta($delayed));

    $notices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $notices);
    self::assertSame(2, (int) $notices[0]->get('contador_site')->value);
    self::assertSame(2, (int) $notices[0]->get('contador_email')->value);
    self::assertSame(20, (int) $notices[0]->get('ultimo_comentario')->value);
    self::assertSame(2, (int) $this->container->get('database')
      ->select(ForumNoticeEventSchema::TABLE)
      ->countQuery()
      ->execute()
      ->fetchField());
    self::assertCount(
      0,
      $this->loadNotices((int) $author->id(), (int) $topic->id()),
    );
  }

  /**
   * Out-of-order post and topic revisions remain countable.
   */
  public function testOutOfOrderPostAndTopicRevisionsAreCountedOnce(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $avisos = $this->createAvisoService([(int) $follower->id()]);
    $first = $this->createPost($topic, (int) $author->id(), TRUE);
    $second = $this->createPost($topic, (int) $author->id(), TRUE);

    self::assertLessThan((int) $second->getRevisionId(), (int) $first->getRevisionId());
    self::assertSame(1, $avisos->registrarPost($second));
    self::assertSame(1, $avisos->registrarPost($first));
    self::assertSame(0, $avisos->registrarPost($first));
    $response = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $response);
    self::assertSame(2, (int) $response[0]->get('contador_site')->value);
    self::assertSame((int) $second->getRevisionId(), (int) $response[0]->get('ultimo_post')->value);

    self::assertSame(1, $avisos->notificarAtualizacao($topic, 20));
    self::assertSame(1, $avisos->notificarAtualizacao($topic, 10));
    self::assertSame(0, $avisos->notificarAtualizacao($topic, 10));
    $updates = $this->loadNotices(
      (int) $follower->id(),
      (int) $topic->id(),
      Aviso::TIPO_ATUALIZACAO,
    );
    self::assertCount(1, $updates);
    self::assertSame(2, (int) $updates[0]->get('contador_site')->value);
    self::assertSame(20, (int) $updates[0]->get('ultimo_evento')->value);
  }

  /**
   * The event ledger follows the six-month notice retention policy.
   */
  public function testExpiredCommentNoticeKeysArePrunedInBoundedCronBatch(): void {
    $now = 1_800_000_000;
    $limit = AvisoRetencao::limite($now);
    $database = $this->container->get('database');
    foreach ([['event_id' => 30, 'created' => $limit], ['event_id' => 31, 'created' => $limit + 1]] as $event) {
      $database->insert(ForumNoticeEventSchema::TABLE)
        ->fields([
          'uid' => 7,
          'event_type' => 'comment',
          'event_id' => $event['event_id'],
          'nid' => 99,
          'created' => $event['created'],
        ])
        ->execute();
    }
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($now);

    (new AvisoRetencaoHooks(
      $this->container->get('entity_type.manager'),
      $time,
      $database,
    ))->expirarAvisos();

    $ids = $database->select(ForumNoticeEventSchema::TABLE, 'event')
      ->fields('event', ['event_id'])
      ->orderBy('event_id')
      ->execute()
      ->fetchCol();
    self::assertSame([31], array_map('intval', $ids));
  }

  /**
   * The schema post-update creates the ledger once and tolerates re-entry.
   */
  public function testPostUpdateCreatesCommentLedgerIdempotently(): void {
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    require_once dirname(__DIR__, 3) . '/aculta_messenger.post_update.php';

    $schema = $this->container->get('database')->schema();
    $table = ForumCommentNoticeSchema::TABLE;
    $schema->dropTable($table);
    $sandbox = [];

    self::assertSame(
      'Added the Forum Comment notice idempotency ledger.',
      aculta_messenger_post_update_forum_comment_notice_ledger($sandbox),
    );
    self::assertTrue($schema->tableExists($table));
    self::assertSame(
      'The Forum Comment notice idempotency ledger already exists.',
      aculta_messenger_post_update_forum_comment_notice_ledger($sandbox),
    );
  }

  /**
   * The unified event ledger preserves keys from the prior Comment-only table.
   */
  public function testPostUpdateMigratesLegacyCommentKeysAcrossBatches(): void {
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    require_once dirname(__DIR__, 3) . '/aculta_messenger.post_update.php';

    $database = $this->container->get('database');
    $schema = $database->schema();
    $schema->dropTable(ForumNoticeEventSchema::TABLE);
    for ($cid = 1_001; $cid <= 1_501; $cid++) {
      $database->insert(ForumCommentNoticeSchema::TABLE)
        ->fields(['uid' => 7, 'cid' => $cid, 'nid' => 99, 'created' => 1_800_000_000])
        ->execute();
    }
    $sandbox = [];
    aculta_messenger_post_update_forum_notice_event_ledger($sandbox);

    self::assertLessThan(1, $sandbox['#finished']);
    self::assertTrue($schema->tableExists(ForumCommentNoticeSchema::TABLE));
    self::assertSame(500, (int) $database->select(ForumNoticeEventSchema::TABLE)
      ->countQuery()
      ->execute()
      ->fetchField());

    aculta_messenger_post_update_forum_notice_event_ledger($sandbox);

    self::assertTrue($schema->tableExists(ForumNoticeEventSchema::TABLE));
    self::assertFalse($schema->tableExists(ForumCommentNoticeSchema::TABLE));
    self::assertSame(501, (int) $database->select(ForumNoticeEventSchema::TABLE)
      ->countQuery()
      ->execute()
      ->fetchField());
    $event = $database->select(ForumNoticeEventSchema::TABLE, 'event')
      ->fields('event', ['uid', 'event_type', 'event_id', 'nid', 'created'])
      ->condition('event_id', 1_001)
      ->execute()
      ->fetchAssoc();
    self::assertSame([
      'uid' => '7',
      'event_type' => 'comment',
      'event_id' => '1001',
      'nid' => '99',
      'created' => '1800000000',
    ], $event);
    self::assertSame(1_501, (int) $database->select(ForumNoticeEventSchema::TABLE, 'event')
      ->fields('event', ['event_id'])
      ->condition('event_id', 1_501)
      ->execute()
      ->fetchField());
  }

  /**
   * Editing an already published post does not add another notice.
   */
  public function testEditOfPublishedPostDoesNotRegisterNotice(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $avisos = $this->createAvisoService([(int) $follower->id()]);
    $hooks = new ForumAvisoHooks($avisos);
    $post = $this->createPost($topic, (int) $author->id(), TRUE);
    $post->set('first_published', time());
    $post->save();
    $hooks->postInsert($post);
    $firstRevision = (int) $post->getRevisionId();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post');
    $before = $storage->loadUnchanged($post->id());
    $post->setNewRevision(TRUE);
    $post->set('body', ['value' => 'Texto corrigido', 'format' => 'plain_text']);
    $post->save();
    $post->setOriginal($before);

    $hooks->postUpdate($post);

    $notices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $notices);
    self::assertSame(1, (int) $notices[0]->get('contador_site')->value);
    self::assertSame($firstRevision, (int) $notices[0]->get('ultimo_post')->value);
  }

  /**
   * A recipient without access to the post is never counted.
   */
  public function testRecipientWithoutPostAccessIsSkipped(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $avisos = $this->createAvisoService(
      [(int) $follower->id()],
      $this->createAccessPolicy(canViewPost: FALSE),
    );
    $post = $this->createPost($topic, (int) $author->id(), TRUE);

    (new ForumAvisoHooks($avisos))->postInsert($post);

    self::assertCount(
      0,
      $this->loadNotices((int) $follower->id(), (int) $topic->id()),
    );
  }

  /**
   * Topic update notices dedupe by revision and stay apart from responses.
   */
  public function testTopicUpdateNoticesAreDeduplicated(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $avisos = $this->createAvisoService([(int) $follower->id()]);

    self::assertSame(1, $avisos->notificarAtualizacao($topic, 10));
    self::assertSame(0, $avisos->notificarAtualizacao($topic, 10));
    self::assertSame(1, $avisos->notificarAtualizacao($topic, 11));

    $updates = $this->loadNotices(
      (int) $follower->id(),
      (int) $topic->id(),
      Aviso::TIPO_ATUALIZACAO,
    );
    self::assertCount(1, $updates);
    self::assertSame(2, (int) $updates[0]->get('contador_site')->value);
    self::assertSame(11, (int) $updates[0]->get('ultimo_evento')->value);

    $post = $this->createPost($topic, (int) $author->id(), TRUE);
    self::assertSame(1, $avisos->registrarPost($post));

    self::assertCount(
      1,
      $this->loadNotices((int) $follower->id(), (int) $topic->id()),
    );
    self::assertCount(
      1,
      $this->loadNotices(
        (int) $follower->id(),
        (int) $topic->id(),
        Aviso::TIPO_ATUALIZACAO,
      ),
    );
    self::assertSame(2, (int) $updates[0]->get('contador_site')->value);
  }

  /**
   * Unpublished or unsupported topics and a missing flag register nothing.
   */
  public function testTopicWithoutEligiblePublicationRegistersNothing(): void {
    $follower = $this->createUser('seguidora');
    $unpublished = $this->createTopic('Tópico em rascunho', FALSE);
    $published = $this->createTopic('Tópico publicado');

    $avisos = $this->createAvisoService([(int) $follower->id()]);
    self::assertSame(0, $avisos->notificarAtualizacao($unpublished, 5));

    $unsupported = $this->createAvisoService(
      [(int) $follower->id()],
      $this->createAccessPolicy(supportsTopic: FALSE),
    );
    self::assertSame(0, $unsupported->notificarAtualizacao($published, 5));

    $noFlag = $this->createMock(FlagServiceInterface::class);
    $noFlag->method('getFlagById')->willReturn(NULL);
    $withoutFlag = new AvisoService(
      $this->container->get('entity_type.manager'),
      $noFlag,
      $this->createAccessPolicy(),
      $this->container->get('database'),
      $this->container->get('datetime.time'),
    );
    self::assertSame(0, $withoutFlag->notificarAtualizacao($published, 5));

    self::assertCount(
      0,
      $this->loadNotices((int) $follower->id(), (int) $unpublished->id()),
    );
    self::assertCount(
      0,
      $this->loadNotices((int) $follower->id(), (int) $published->id()),
    );
  }

  /**
   * Creates a persisted account.
   */
  private function createUser(string $name): UserInterface {
    $user = User::create(['name' => $name, 'status' => 1]);
    $user->save();
    return $user;
  }

  /**
   * Creates a persisted Forum topic.
   */
  private function createTopic(
    string $title = 'Tópico de teste',
    bool $published = TRUE,
  ): NodeInterface {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => $title,
      'status' => $published ? 1 : 0,
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * Creates a persisted Forum post.
   */
  private function createPost(
    NodeInterface $topic,
    int $author,
    bool $published,
  ): ForumPost {
    $post = ForumPost::create([
      'uid' => $author,
      'topic' => $topic->id(),
      'body' => ['value' => 'Texto do post', 'format' => 'plain_text'],
      'status' => $published,
    ]);
    $post->save();
    return $post;
  }

  /**
   * Builds a published legacy comment for notification characterization.
   */
  private function createPublishedComment(
    NodeInterface $topic,
    int $author,
    int $comment_id,
  ): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('id')->willReturn((string) $comment_id);
    $comment->method('getOwnerId')->willReturn($author);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('getCommentedEntity')->willReturn($topic);
    return $comment;
  }

  /**
   * Builds the service with the given follower accounts.
   *
   * @param int[] $followers
   *   Account ids returned by the flagged topic query.
   * @param \Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface|null $policy
   *   Notice access policy; a permissive default is used when omitted.
   */
  private function createAvisoService(
    array $followers,
    ?ForumNoticeAccessPolicyInterface $policy = NULL,
  ): AvisoService {
    $flaggings = [];
    foreach ($followers as $uid) {
      $flagging = $this->createMock(FlaggingInterface::class);
      $flagging->method('getOwnerId')->willReturn($uid);
      $flaggings[] = $flagging;
    }
    $flag = $this->createMock(FlagInterface::class);
    $flags = $this->createMock(FlagServiceInterface::class);
    $flags->method('getFlagById')->with('seguir_topico')->willReturn($flag);
    $flags->method('getEntityFlaggings')->willReturn($flaggings);

    return new AvisoService(
      $this->container->get('entity_type.manager'),
      $flags,
      $policy ?? $this->createAccessPolicy(),
      $this->container->get('database'),
      $this->container->get('datetime.time'),
      $this->container->get('lock'),
    );
  }

  /**
   * Builds a permissive notice access policy, optionally denying one check.
   */
  private function createAccessPolicy(
    bool $supportsTopic = TRUE,
    bool $canViewTopic = TRUE,
    bool $canViewPost = TRUE,
    bool $canViewComment = FALSE,
  ): ForumNoticeAccessPolicyInterface {
    $policy = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $policy->method('supportsTopic')->willReturn($supportsTopic);
    $policy->method('canViewTopic')->willReturn($canViewTopic);
    $policy->method('canViewPost')->willReturn($canViewPost);
    $policy->method('canViewComment')->willReturn($canViewComment);
    return $policy;
  }

  /**
   * Loads a person's notices for a topic and type.
   *
   * @return \Drupal\aculta_messenger\Entity\Aviso[]
   *   Stored notices, newest id last.
   */
  private function loadNotices(
    int $uid,
    int $nid,
    string $tipo = Aviso::TIPO_RESPOSTA,
  ): array {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_aviso');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('assunto_nid', $nid)
      ->condition('tipo', $tipo)
      ->execute();
    return array_values($storage->loadMultiple($ids));
  }

}
