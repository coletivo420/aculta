<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Event\ForumCommentSubtreeMovedEvent;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeEventProducer;
use Drupal\aculta_messenger\Aviso\ForumNoticeEmailOutbox;
use Drupal\aculta_messenger\Aviso\AvisoRetencao;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_messenger\Database\ForumCommentNoticeSchema;
use Drupal\aculta_messenger\Database\ForumNoticeEventSchema;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_messenger\EventSubscriber\ForumNoticeEventSubscriber;
use Drupal\aculta_messenger\EventSubscriber\ForumCommentMoveSubscriber;
use Drupal\aculta_messenger\Service\MessengerNoticePublisher;
use Drupal\aculta_messenger\Hook\AvisoRetencaoHooks;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Lock\PersistentDatabaseLockBackend;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Psr\Log\LoggerInterface;

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
    'comment',
    'aculta_forum_post_test',
    'aculta_messenger_aviso_test',
  ];

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
    $this->installEntitySchema('aculta_forum_post');
    $this->installEntitySchema('aculta_aviso');
    $this->installSchema('aculta_messenger_aviso_test', [
      ForumCommentNoticeSchema::TABLE,
      ForumNoticeEventSchema::TABLE,
    ]);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'comment']);
    $this->container->get('state')->set('comment.maintain_entity_statistics', TRUE);
    NodeType::create([
      'type' => 'aculta_forum_topic',
      'name' => 'ACULTA Forum topic',
      'new_revision' => TRUE,
    ])->save();
    CommentType::create([
      'id' => 'aculta_discussion_post',
      'label' => 'ACULTA discussion post',
      'target_entity_type_id' => 'node',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_aculta_discussion',
      'type' => 'comment',
      'settings' => ['comment_type' => 'aculta_discussion_post'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_aculta_discussion',
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'label' => 'Comments',
      'required' => TRUE,
    ])->save();
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

    $avisos->registrarPost($post);

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
   * Forum read-state filters run before the per-account result limit.
   */
  public function testForumReadFilterSelectsMatchingAggregates(): void {
    $author = $this->createUser('autor_filtro_leitura');
    $follower = $this->createUser('seguidor_filtro_leitura');
    $uid = (int) $follower->id();
    $readTopic = $this->createTopic('Tópico lido');
    $unreadTopic = $this->createTopic('Tópico não lido');
    $service = $this->createAvisoService([$uid], $this->createAccessPolicy(canViewTopic: TRUE, canViewComment: TRUE));
    self::assertSame(1, $service->registrarResposta($this->createPersistedComment($readTopic, (int) $author->id())));
    self::assertSame(1, $service->registrarResposta($this->createPersistedComment($unreadTopic, (int) $author->id())));

    $service->marcarLido($uid, (int) $readTopic->id());

    $read = $service->listarDaPessoa($uid, 1, 'read');
    $unread = $service->listarDaPessoa($uid, 1, 'unread');
    self::assertCount(1, $read);
    self::assertSame((int) $readTopic->id(), (int) $read[0]->get('assunto_nid')->value);
    self::assertCount(1, $unread);
    self::assertSame((int) $unreadTopic->id(), (int) $unread[0]->get('assunto_nid')->value);
  }

  /**
   * The public event contract persists one idempotent ForumPost notice.
   */
  public function testForumPostEventRoundTripUsesTheSharedNoticeAggregate(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $post = $this->createPost($topic, (int) $author->id(), TRUE);

    $flag = $this->createMock(FlagInterface::class);
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getOwnerId')->willReturn((int) $follower->id());
    $flags = $this->createMock(FlagServiceInterface::class);
    $flags->method('getFlagById')->with('seguir_topico')->willReturn($flag);
    $flags->method('getEntityFlaggings')->willReturn([$flagging]);

    $access = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $access->method('supportsTopic')->willReturn(TRUE);
    $access->method('canViewPost')->willReturn(TRUE);
    $access->method('canViewTopic')->willReturn(TRUE);

    $avisos = new AvisoService(
      $this->container->get('entity_type.manager'),
      $flags,
      $access,
      $this->container->get('database'),
      $this->container->get('datetime.time'),
      $this->container->get('lock'),
    );
    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new ForumNoticeEventSubscriber(
      $this->container->get('entity_type.manager'),
      $avisos,
    ));
    $producer = new ForumNoticeEventProducer($flags, $access, new MessengerNoticePublisher($dispatcher));

    self::assertSame(1, $producer->publishPost($post));
    self::assertSame(1, $producer->publishPost($post), 'Replaying the producer event is harmless.');
    $notices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $notices);
    self::assertSame(1, (int) $notices[0]->get('contador_site')->value);
    self::assertSame(1, (int) $notices[0]->get('contador_email')->value);
    self::assertSame((int) $post->getRevisionId(), (int) $notices[0]->get('ultimo_post')->value);
  }

  /**
   * A published Comment is delivered through the shared event contract once.
   */
  public function testCommentEventRoundTripUsesTheSharedNoticeAggregate(): void {
    $author = $this->createUser('autora_comentario');
    $follower = $this->createUser('seguidora_comentario');
    $topic = $this->createTopic();
    $comment = Comment::create([
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $author->id(),
      'subject' => 'Resposta publicada',
      'status' => CommentInterface::PUBLISHED,
    ]);
    $comment->save();
    $producer = $this->createEventProducer([
      (int) $author->id(),
      (int) $follower->id(),
    ], $this->createAccessPolicy(canViewComment: TRUE));

    self::assertSame(1, $producer->publishComment($comment));
    self::assertSame(1, $producer->publishComment($comment), 'Replaying the Comment event is harmless.');
    $notices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $notices);
    self::assertSame(1, (int) $notices[0]->get('contador_site')->value);
    self::assertSame(1, (int) $notices[0]->get('contador_email')->value);
    self::assertSame((int) $comment->id(), (int) $notices[0]->get('ultimo_comentario')->value);
    self::assertCount(0, $this->loadNotices((int) $author->id(), (int) $topic->id()));
  }

  /**
   * Access revoked after publication is rechecked by the event consumer.
   */
  public function testCommentAccessRevokedBeforeConsumptionDoesNotPersistNotice(): void {
    $author = $this->createUser('autor_revogacao');
    $follower = $this->createUser('seguidor_revogado');
    $topic = $this->createTopic();
    $comment = Comment::create([
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $author->id(),
      'subject' => 'Resposta com acesso revogado',
      'status' => CommentInterface::PUBLISHED,
    ]);
    $comment->save();
    $policy = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $policy->method('supportsTopic')->willReturn(TRUE);
    $policy->method('canViewTopic')->willReturn(TRUE);
    $policy->method('canViewPost')->willReturn(TRUE);
    $policy->expects(self::exactly(2))
      ->method('canViewComment')
      ->with(
        self::callback(fn(CommentInterface $candidate): bool => $candidate->uuid() === $comment->uuid()),
        (int) $follower->id(),
      )
      ->willReturnOnConsecutiveCalls(TRUE, FALSE);
    $producer = $this->createEventProducer([
      (int) $author->id(),
      (int) $follower->id(),
    ], $policy);

    self::assertSame(1, $producer->publishComment($comment));
    self::assertCount(0, $this->loadNotices((int) $follower->id(), (int) $topic->id()));
    self::assertSame(0, (int) $this->container->get('database')
      ->select(ForumNoticeEventSchema::TABLE)
      ->condition('uid', (int) $follower->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * The shared event path preserves the existing Forum notice aggregation.
   */
  public function testEventAndDirectCommentRegistrationShareExistingAggregate(): void {
    $author = $this->createUser('autor_equivalencia');
    $follower = $this->createUser('seguidor_equivalencia');
    $topic = $this->createTopic('Tópico equivalente');
    $directComment = $this->createPersistedComment($topic, (int) $author->id());
    $eventComment = $this->createPersistedComment($topic, (int) $author->id());
    $policy = $this->createAccessPolicy(canViewComment: TRUE);

    $direct = $this->createAvisoService([(int) $follower->id()], $policy);
    self::assertSame(1, $direct->registrarResposta($directComment));
    $directNotices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $directNotices);
    self::assertSame(1, (int) $directNotices[0]->get('contador_site')->value);
    self::assertSame(1, (int) $directNotices[0]->get('contador_email')->value);

    $producer = $this->createEventProducer([(int) $follower->id()], $policy);
    self::assertSame(1, $producer->publishComment($eventComment));

    $eventNotices = $this->loadNotices((int) $follower->id(), (int) $topic->id());
    self::assertCount(1, $eventNotices);
    self::assertSame(2, (int) $eventNotices[0]->get('contador_site')->value);
    self::assertSame(2, (int) $eventNotices[0]->get('contador_email')->value);
    self::assertSame('Tópico equivalente', $eventNotices[0]->get('titulo')->value);
    self::assertSame((string) $eventComment->id(), $eventNotices[0]->get('ultimo_comentario')->value);
  }

  /**
   * A combined Forum digest sends one aggregate row through the ACULTA Mailer.
   */
  public function testDirectAndSharedEventsProduceOneCombinedDigestRow(): void {
    $author = $this->createUser('autor_digest');
    $follower = $this->createUser('seguidor_digest');
    $follower->setEmail('seguidor@example.test');
    $follower->set('langcode', 'pt-br');
    $follower->set('preferred_langcode', 'pt-br');
    $follower->save();
    $uid = (int) $follower->id();
    $userData = $this->container->get('user.data');
    $userData->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    $preferences = new AvisoPreferencias($userData);
    $preferences->salvar($uid, TRUE, TRUE, TRUE);
    $topic = $this->createTopic('Resumo compartilhado');
    $directComment = $this->createPersistedComment($topic, (int) $author->id());
    $eventComment = $this->createPersistedComment($topic, (int) $author->id());
    $lateComment = $this->createPersistedComment($topic, (int) $author->id());
    $policy = $this->createAccessPolicy(canViewTopic: TRUE, canViewComment: TRUE);
    $avisos = $this->createAvisoService([$uid], $policy);
    self::assertSame(1, $avisos->registrarResposta($directComment));
    $producer = $this->createEventProducer([$uid], $policy);
    self::assertSame(1, $producer->publishComment($eventComment));

    $mailer = $this->createMock(AcultaMailerInterface::class);
    $deliveries = 0;
    $concurrentSummary = NULL;
    $mailer->expects(self::once())
      ->method('deliver')
      ->willReturnCallback(function (MailMessage $message) use (&$deliveries, &$concurrentSummary, $avisos, $lateComment): SendResult {
        $deliveries++;
        self::assertSame(AvisosResumo::CHAVE_MAIL, $message->type);
        self::assertSame('seguidor@example.test', $message->to);
        self::assertSame('pt-br', $message->langcode);
        self::assertSame('Respostas novas em "Resumo compartilhado": 2', $message->context['resumo']);
        if ($deliveries === 1) {
          self::assertInstanceOf(AvisosResumo::class, $concurrentSummary);
          self::assertSame(0, $concurrentSummary->enviarResumos(), 'A separate request skips the recipient while its persistent lock is held.');
          self::assertSame(1, $avisos->registrarResposta($lateComment), 'A new event arrives while the first digest is being sent.');
        }
        return SendResult::sent();
      });
    $emailPolicy = new EmailConfirmationPolicy(
      $userData,
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
    );
    $summary = new AvisosResumo(
      $this->container->get('entity_type.manager'),
      $preferences,
      $mailer,
      $emailPolicy,
      $userData,
      $this->container->get('datetime.time'),
      $avisos,
      $this->container->get('settings'),
      $this->container->get('database'),
      $this->container->get('lock.persistent'),
    );
    // A second backend instance models a separate request sharing the same DB
    // semaphore table; reusing one backend would renew its own lock.
    $concurrentSummary = new AvisosResumo(
      $this->container->get('entity_type.manager'),
      $preferences,
      $mailer,
      $emailPolicy,
      $userData,
      $this->container->get('datetime.time'),
      $avisos,
      $this->container->get('settings'),
      $this->container->get('database'),
      new PersistentDatabaseLockBackend($this->container->get('database')),
    );

    self::assertSame(1, $summary->enviarResumos());
    $notices = $this->loadNotices($uid, (int) $topic->id());
    self::assertCount(1, $notices);
    self::assertSame(1, (int) $notices[0]->get('contador_email')->value, 'The concurrent event remains pending after acknowledging the first digest.');
    self::assertSame((string) $lateComment->id(), $notices[0]->get('ultimo_comentario')->value);
  }

  /**
   * A new Forum digest batch cannot reuse a previously accepted correlation.
   */
  public function testNewForumDigestGenerationGetsNewCorrelationAfterAcknowledgement(): void {
    $author = $this->createUser('autor_digest_novo_lote');
    $follower = $this->createUser('seguidor_digest_novo_lote');
    $follower->setEmail('digest-generation@example.test');
    $follower->set('langcode', 'pt-br');
    $follower->set('preferred_langcode', 'pt-br');
    $follower->save();
    $uid = (int) $follower->id();
    $userData = $this->container->get('user.data');
    $userData->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    $preferences = new AvisoPreferencias($userData);
    $preferences->salvar($uid, TRUE, TRUE, TRUE);
    $topic = $this->createTopic('Gerações do digest');
    $avisos = $this->createAvisoService(
      [$uid],
      $this->createAccessPolicy(canViewTopic: TRUE, canViewComment: TRUE),
    );
    // The higher Comment ID is approved and delivered first; approval of the
    // older Comment must still become a new digest generation afterwards.
    $newerComment = $this->createPublishedComment($topic, (int) $author->id(), 20);
    $olderComment = $this->createPublishedComment($topic, (int) $author->id(), 10);
    self::assertSame(1, $avisos->registrarResposta($newerComment));

    $correlations = [];
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::exactly(2))
      ->method('deliver')
      ->willReturnCallback(static function (MailMessage $message) use (&$correlations): SendResult {
        $correlations[] = $message->correlationId;
        return SendResult::sent();
      });
    $emailPolicy = new EmailConfirmationPolicy(
      $userData,
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
    );
    $summary = new AvisosResumo(
      $this->container->get('entity_type.manager'),
      $preferences,
      $mailer,
      $emailPolicy,
      $userData,
      $this->container->get('datetime.time'),
      $avisos,
      $this->container->get('settings'),
      $this->container->get('database'),
      $this->container->get('lock.persistent'),
    );

    self::assertSame(1, $summary->enviarResumos());
    $userData->set('aculta_messenger', $uid, 'avisos_resumo_ultimo', 0);
    self::assertSame(1, $avisos->registrarResposta($olderComment));
    self::assertSame(1, $summary->enviarResumos());
    self::assertNotSame($correlations[0], $correlations[1], 'A later event with the same pending quantity is a distinct logical digest.');
  }

  /**
   * Rolls back all digest acknowledgements when one adapter fails.
   */
  public function testDigestAcknowledgementsRollbackAsOneBatch(): void {
    $author = $this->createUser('autor_digest_rollback');
    $follower = $this->createUser('seguidor_digest_rollback');
    $follower->setEmail('digest-rollback@example.test');
    $follower->set('langcode', 'pt-br');
    $follower->set('preferred_langcode', 'pt-br');
    $follower->save();
    $uid = (int) $follower->id();
    $userData = $this->container->get('user.data');
    $userData->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    $preferences = new AvisoPreferencias($userData);
    $preferences->salvar($uid, TRUE, TRUE, TRUE);
    $topic = $this->createTopic('Digest transacional');
    $comment = $this->createPersistedComment($topic, (int) $author->id());
    $policy = $this->createAccessPolicy(canViewTopic: TRUE, canViewComment: TRUE);
    $avisos = $this->createAvisoService([$uid], $policy);
    self::assertSame(1, $avisos->registrarResposta($comment));

    $database = $this->container->get('database');
    $database->schema()->createTable('aculta_messenger_digest_ack_test', [
      'fields' => [
        'id' => ['type' => 'int', 'size' => 'normal', 'not null' => TRUE],
        'acknowledged' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['id'],
    ]);
    $database->insert('aculta_messenger_digest_ack_test')->fields(['id' => 1, 'acknowledged' => 0])->execute();
    $provider = new FailOnceDigestAckProvider($uid, $database);
    $correlations = [];
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::exactly(2))
      ->method('deliver')
      ->willReturnCallback(static function (MailMessage $message) use (&$correlations): SendResult {
        $correlations[] = $message->correlationId;
        return SendResult::sent();
      });
    $summary = new AvisosResumo(
      $this->container->get('entity_type.manager'),
      $preferences,
      $mailer,
      new EmailConfirmationPolicy(
        $userData,
        $this->container->get('entity_type.manager'),
        $this->container->get('module_handler'),
      ),
      $userData,
      $this->container->get('datetime.time'),
      $avisos,
      $this->container->get('settings'),
      $database,
      $this->container->get('lock.persistent'),
      [$provider],
    );

    self::assertSame(0, $summary->enviarResumos());
    $notice = $this->loadNotices($uid, (int) $topic->id())[0];
    self::assertSame(1, (int) $notice->get('contador_email')->value, 'Forum email count rolls back when the adapter acknowledgement fails.');
    self::assertSame(0, (int) $database->select('aculta_messenger_digest_ack_test', 't')->fields('t', ['acknowledged'])->condition('id', 1)->execute()->fetchField());

    self::assertSame(1, $summary->enviarResumos());
    self::assertSame($correlations[0], $correlations[1]);
    $notice = $this->loadNotices($uid, (int) $topic->id())[0];
    self::assertSame(0, (int) $notice->get('contador_email')->value);
    self::assertSame(1, (int) $database->select('aculta_messenger_digest_ack_test', 't')->fields('t', ['acknowledged'])->condition('id', 1)->execute()->fetchField());
  }

  /**
   * A permanent email failure closes email delivery but preserves site notice.
   */
  public function testPermanentDigestFailureKeepsTheRequiredSiteNotice(): void {
    $author = $this->createUser('autor_falha_digest');
    $follower = $this->createUser('seguidor_falha_digest');
    $follower->setEmail('seguidor-falha@example.test');
    $follower->set('langcode', 'pt-br');
    $follower->set('preferred_langcode', 'pt-br');
    $follower->save();
    $uid = (int) $follower->id();
    $userData = $this->container->get('user.data');
    $userData->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    $preferences = new AvisoPreferencias($userData);
    $preferences->salvar($uid, TRUE, TRUE, TRUE);
    $topic = $this->createTopic('Falha permanente do digest');
    $comment = $this->createPersistedComment($topic, (int) $author->id());
    $policy = $this->createAccessPolicy(canViewTopic: TRUE, canViewComment: TRUE);
    $avisos = $this->createAvisoService([$uid], $policy);
    self::assertSame(1, $avisos->registrarResposta($comment));

    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())
      ->method('deliver')
      ->willReturn(SendResult::failed(DeliveryError::Permanent, 550));
    $summary = new AvisosResumo(
      $this->container->get('entity_type.manager'),
      $preferences,
      $mailer,
      new EmailConfirmationPolicy(
        $userData,
        $this->container->get('entity_type.manager'),
        $this->container->get('module_handler'),
      ),
      $userData,
      $this->container->get('datetime.time'),
      $avisos,
      $this->container->get('settings'),
      $this->container->get('database'),
      $this->container->get('lock.persistent'),
    );

    self::assertSame(0, $summary->enviarResumos());
    self::assertSame(0, $summary->enviarResumos(), 'A permanent email failure is not retried by a later digest run.');
    $notices = $this->loadNotices($uid, (int) $topic->id());
    self::assertCount(1, $notices);
    self::assertSame(1, (int) $notices[0]->get('contador_site')->value);
    self::assertSame(0, (int) $notices[0]->get('contador_email')->value);
  }

  /**
   * A topic revision round-trips once and suppresses the revision author.
   */
  public function testTopicRevisionEventRoundTripUsesTheSharedNoticeAggregate(): void {
    $author = $this->createUser('autor_topico');
    $follower = $this->createUser('seguidora_topico');
    $topic = $this->createTopic();
    $topic->setRevisionUserId((int) $author->id());
    $topic->setNewRevision(TRUE);
    $topic->save();
    $producer = $this->createEventProducer([
      (int) $author->id(),
      (int) $follower->id(),
    ], $this->createAccessPolicy(canViewTopic: TRUE));

    self::assertSame(1, $producer->publishTopicUpdate($topic, (int) $topic->getRevisionId()));
    self::assertSame(1, $producer->publishTopicUpdate($topic, (int) $topic->getRevisionId()));
    $updates = $this->loadNotices(
      (int) $follower->id(),
      (int) $topic->id(),
      Aviso::TIPO_ATUALIZACAO,
    );
    self::assertCount(1, $updates);
    self::assertSame(1, (int) $updates[0]->get('contador_site')->value);
    self::assertSame((int) $topic->getRevisionId(), (int) $updates[0]->get('ultimo_evento')->value);
    self::assertCount(0, $this->loadNotices(
      (int) $author->id(),
      (int) $topic->id(),
      Aviso::TIPO_ATUALIZACAO,
    ));
  }

  /**
   * A member who unfollows before delivery is no longer eligible.
   */
  public function testFollowerWhoUnfollowsBeforeDeliveryGetsNoNotice(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('ex_seguidora');
    $topic = $this->createTopic();
    // The event is delivered after the flagging has been removed.
    $avisos = $this->createAvisoService([]);
    $post = $this->createPost($topic, (int) $author->id(), TRUE);

    self::assertSame(0, $avisos->registrarPost($post));
    self::assertCount(0, $this->loadNotices((int) $follower->id(), (int) $topic->id()));
    self::assertSame(0, (int) $this->container->get('database')
      ->select(ForumNoticeEventSchema::TABLE)
      ->countQuery()
      ->execute()
      ->fetchField());
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
    $avisos->registrarPost($post);
    $avisos->registrarPost($post);
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post');
    $avisos->registrarPost($storage->load($post->id()));

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
    $pending = $this->createPost($topic, (int) $author->id(), FALSE);
    self::assertSame(0, $avisos->registrarPost($pending));

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

    self::assertSame(1, $avisos->registrarPost($post));
    self::assertSame(0, $avisos->registrarPost($post));

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
    // This post is created first but remains pending until after the second
    // post has already been published and counted.
    $delayed = $this->createPost($topic, (int) $author->id(), FALSE);
    $newer = $this->createPost($topic, (int) $author->id(), TRUE);
    $avisos->registrarPost($newer);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post');
    $beforeApproval = $storage->loadUnchanged($delayed->id());
    $delayed->set('status', TRUE);
    $delayed->set('first_published', time());
    $delayed->setNewRevision(TRUE);
    $delayed->save();
    $delayed->setOriginal($beforeApproval);

    self::assertSame(1, $avisos->registrarPost($delayed));
    self::assertSame(0, $avisos->registrarPost($delayed));

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
   * Moving a published reply transfers its aggregate without losing dedupe.
   */
  public function testMovedCommentTransfersPersistentAggregateAndKeepsLedger(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $source = $this->createTopic('Tópico de origem');
    $destination = $this->createTopic('Tópico de destino');
    $avisos = $this->createAvisoService(
      [(int) $follower->id()],
      $this->createAccessPolicy(canViewComment: TRUE),
    );
    $movedComment = $this->createPersistedComment($source, (int) $author->id());
    $destinationComment = $this->createPersistedComment($destination, (int) $author->id());

    self::assertSame(1, $avisos->registrarResposta($movedComment));
    self::assertSame(1, $avisos->registrarResposta($destinationComment));

    $movedComment->set('entity_id', $destination->id());
    $movedComment->save();
    (new ForumCommentMoveSubscriber($avisos))->transferNotices(
      new ForumCommentSubtreeMovedEvent(
        (int) $source->id(),
        (int) $destination->id(),
        TRUE,
      ),
    );

    self::assertCount(0, $this->loadNotices((int) $follower->id(), (int) $source->id()));
    $destinationNotices = $this->loadNotices((int) $follower->id(), (int) $destination->id());
    self::assertCount(1, $destinationNotices);
    self::assertSame(2, (int) $destinationNotices[0]->get('contador_site')->value);
    self::assertSame(2, (int) $destinationNotices[0]->get('contador_email')->value);
    self::assertSame((int) $destinationComment->id(), (int) $destinationNotices[0]->get('ultimo_comentario')->value);
    self::assertSame('Tópico de destino', $destinationNotices[0]->get('titulo')->value);

    // The source event key remains the dedupe authority after the Comment move.
    self::assertSame(0, $avisos->registrarResposta($movedComment));
    self::assertSame(2, (int) $this->container->get('database')
      ->select(ForumNoticeEventSchema::TABLE)
      ->countQuery()
      ->execute()
      ->fetchField());
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
   * A busy aggregate lock is waited on and retried before recording an event.
   */
  public function testLockContentionWaitsAndRetriesBeforeCountingNotice(): void {
    $author = $this->createUser('autor');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $lockName = sprintf(
      'aculta_messenger:notice:%d:%d:%s',
      $follower->id(),
      $topic->id(),
      Aviso::TIPO_RESPOSTA,
    );
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(2))
      ->method('acquire')
      ->with($lockName, 60.0)
      ->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $lock->expects(self::once())
      ->method('wait')
      ->with($lockName, 60);
    $lock->expects(self::once())
      ->method('release')
      ->with($lockName);

    $avisos = $this->createAvisoService([(int) $follower->id()], lock: $lock);
    $post = $this->createPost($topic, (int) $author->id(), TRUE);

    self::assertSame(1, $avisos->registrarPost($post));
    self::assertSame(1, (int) $this->loadNotices((int) $follower->id(), (int) $topic->id())[0]
      ->get('contador_site')->value);
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
   * Direct-email outbox fields are added without replacing the event ledger.
   */
  public function testDirectEmailPostUpdateAddsLedgerColumnsIdempotently(): void {
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';

    $database = $this->container->get('database');
    $schema = $database->schema();
    $table = ForumNoticeEventSchema::TABLE;
    foreach (['email_status', 'email_attempts', 'email_correlation', 'email_updated'] as $field) {
      $schema->dropField($table, $field);
    }
    $schema->dropIndex($table, 'email_status');

    aculta_messenger_update_10002();
    aculta_messenger_update_10002();
    aculta_messenger_update_10005();
    aculta_messenger_update_10005();

    self::assertTrue($schema->fieldExists($table, 'email_status'));
    self::assertTrue($schema->fieldExists($table, 'email_attempts'));
    self::assertTrue($schema->fieldExists($table, 'email_correlation'));
    self::assertTrue($schema->fieldExists($table, 'email_updated'));
    self::assertTrue($schema->indexExists($table, 'email_status'));
  }

  /**
   * Outbox retries preserve one valid Mailer correlation ID for an event.
   */
  public function testOutboxClaimKeepsCorrelationIdAcrossRetries(): void {
    $database = $this->container->get('database');
    $now = $this->container->get('datetime.time')->getRequestTime();
    $id = (int) $database->insert(ForumNoticeEventSchema::TABLE)
      ->fields([
        'uid' => 35,
        'event_type' => 'comment',
        'event_id' => 810,
        'nid' => 72,
        'created' => $now,
        'email_status' => 'queued',
        'email_attempts' => 0,
        'email_correlation' => '',
        'email_updated' => $now,
      ])
      ->execute();

    $outbox = new ForumNoticeEmailOutbox(
      $database,
      $this->createMock(QueueFactory::class),
      $this->container->get('datetime.time'),
      $this->createConfiguredMock(UuidInterface::class, ['generate' => '01234567-89ab-4cde-8fab-0123456789ab']),
      $this->createMock(LoggerInterface::class),
    );
    $firstClaim = $outbox->claim($id);
    self::assertNotNull($firstClaim);
    $correlation = (string) $firstClaim['email_correlation'];
    self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $correlation);
    self::assertSame(1, (int) $firstClaim['email_attempts']);

    $database->update(ForumNoticeEventSchema::TABLE)
      ->fields(['email_updated' => $now - 601])
      ->condition('id', $id)
      ->execute();
    $retryClaim = $outbox->claim($id);
    self::assertNotNull($retryClaim);
    self::assertSame($correlation, $retryClaim['email_correlation']);
    self::assertSame(2, (int) $retryClaim['email_attempts']);
  }

  /**
   * Cron recovery requeues expired processing and queued outbox rows.
   */
  public function testOutboxCronRecoversExpiredQueueLeases(): void {
    $database = $this->container->get('database');
    $now = $this->container->get('datetime.time')->getRequestTime();
    $ids = [];
    foreach ([ForumNoticeEmailOutbox::STATUS_PROCESSING, ForumNoticeEmailOutbox::STATUS_QUEUED] as $index => $status) {
      $ids[] = (int) $database->insert(ForumNoticeEventSchema::TABLE)
        ->fields([
          'uid' => 35,
          'event_type' => 'comment',
          'event_id' => 900 + $index,
          'nid' => 72,
          'created' => $now,
          'email_status' => $status,
          'email_attempts' => 1,
          'email_correlation' => '',
          'email_updated' => $now - 601,
        ])
        ->execute();
    }
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::exactly(2))
      ->method('createItem')
      ->willReturnOnConsecutiveCalls(1, 2);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->with(ForumNoticeEmailOutbox::QUEUE)->willReturn($queue);
    $outbox = new ForumNoticeEmailOutbox(
      $database,
      $queueFactory,
      $this->container->get('datetime.time'),
      $this->createConfiguredMock(UuidInterface::class, ['generate' => '01234567-89ab-4cde-8fab-0123456789ab']),
      $this->createMock(LoggerInterface::class),
    );

    $outbox->requeuePending();

    foreach ($ids as $id) {
      $row = $outbox->load($id);
      self::assertNotNull($row);
      self::assertSame(ForumNoticeEmailOutbox::STATUS_QUEUED, $row['email_status']);
      self::assertSame($now, (int) $row['email_updated']);
    }
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
    $post = $this->createPost($topic, (int) $author->id(), TRUE);
    $post->set('first_published', time());
    $post->save();
    $avisos->registrarPost($post);
    $firstRevision = (int) $post->getRevisionId();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post');
    $before = $storage->loadUnchanged($post->id());
    $post->setNewRevision(TRUE);
    $post->set('body', ['value' => 'Texto corrigido', 'format' => 'plain_text']);
    $post->save();
    $post->setOriginal($before);

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

    $avisos->registrarPost($post);

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
   * A topic revision author does not receive an update notice for their action.
   */
  public function testTopicRevisionAuthorDoesNotReceiveOwnNotice(): void {
    $author = $this->createUser('autora_atualizacao');
    $follower = $this->createUser('seguidora');
    $topic = $this->createTopic();
    $topic->setRevisionUserId((int) $author->id());
    $topic->save();
    $avisos = $this->createAvisoService([
      (int) $author->id(),
      (int) $follower->id(),
    ]);

    self::assertSame(1, $avisos->notificarAtualizacao($topic, (int) $topic->getRevisionId()));
    self::assertCount(0, $this->loadNotices(
      (int) $author->id(),
      (int) $topic->id(),
      Aviso::TIPO_ATUALIZACAO,
    ));
    self::assertCount(1, $this->loadNotices(
      (int) $follower->id(),
      (int) $topic->id(),
      Aviso::TIPO_ATUALIZACAO,
    ));
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
   * Creates a real published legacy Comment attached to a Forum topic.
   */
  private function createPersistedComment(NodeInterface $topic, int $author): CommentInterface {
    $comment = Comment::create([
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $author,
      'subject' => 'Resposta de teste',
      'status' => CommentInterface::PUBLISHED,
    ]);
    $comment->save();
    return $comment;
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
   * @param \Drupal\Core\Lock\LockBackendInterface|null $lock
   *   Optional lock backend for retry characterization.
   */
  private function createAvisoService(
    array $followers,
    ?ForumNoticeAccessPolicyInterface $policy = NULL,
    ?LockBackendInterface $lock = NULL,
  ): AvisoService {
    return new AvisoService(
      $this->container->get('entity_type.manager'),
      $this->createFlagService($followers),
      $policy ?? $this->createAccessPolicy(),
      $this->container->get('database'),
      $this->container->get('datetime.time'),
      $lock ?? $this->container->get('lock'),
    );
  }

  /**
   * Builds an actual producer-to-consumer event pipeline over test storage.
   *
   * @param int[] $followers
   *   Account IDs returned by the topic's follow query.
   * @param \Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface $policy
   *   Access policy used by the producer and registrar.
   */
  private function createEventProducer(
    array $followers,
    ForumNoticeAccessPolicyInterface $policy,
  ): ForumNoticeEventProducer {
    $entityTypeManager = $this->container->get('entity_type.manager');
    $flags = $this->createFlagService($followers);
    $avisos = new AvisoService(
      $entityTypeManager,
      $flags,
      $policy,
      $this->container->get('database'),
      $this->container->get('datetime.time'),
      $this->container->get('lock'),
    );
    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new ForumNoticeEventSubscriber($entityTypeManager, $avisos));
    return new ForumNoticeEventProducer(
      $flags,
      $policy,
      new MessengerNoticePublisher($dispatcher),
    );
  }

  /**
   * Builds FlagService results for the given follower accounts.
   *
   * @param int[] $followers
   *   Account IDs returned by the topic's follow query.
   */
  private function createFlagService(array $followers): FlagServiceInterface {
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
    return $flags;
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

/**
 * Digest provider that fails after updating its source once.
 */
final class FailOnceDigestAckProvider implements AvisoDigestProviderInterface {

  /**
   * Whether the synthetic first failure has happened.
   */
  private bool $failed = FALSE;

  public function __construct(
    private readonly int $uid,
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function pendingRecipientIds(): array {
    return [$this->uid];
  }

  /**
   * {@inheritdoc}
   */
  public function digestRowsFor(int $uid): array {
    if ($uid !== $this->uid) {
      return [];
    }
    return [
      [
        'tipo' => 'wiki',
        'titulo' => 'Revisão publicada',
        'quantidade' => 1,
        'delivery_id' => 998,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    $this->database->update('aculta_messenger_digest_ack_test')
      ->fields(['acknowledged' => 1])
      ->condition('id', 1)
      ->execute();
    if (!$this->failed) {
      $this->failed = TRUE;
      throw new \RuntimeException('Synthetic acknowledgement failure.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {}

}
