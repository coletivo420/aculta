<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Wiki\Kernel;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Wiki\Plugin\QueueWorker\WikiNoticeEmailWorker;
use Drupal\aculta_messenger\Service\NoticeReadStateManager;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_messenger\Integration\Wiki\EventSubscriber\WikiNoticeEventSubscriber;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Wiki event delivery through real shared Message storage. */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class WikiNoticePersistenceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'message',
    'node',
  ];

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('message');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'filter', 'message', 'node']);
    NodeType::create([
      'type' => 'wiki_entry',
      'name' => 'Wiki entry',
      'new_revision' => TRUE,
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'moderation_state',
      'type' => 'string',
      'settings' => ['max_length' => 32],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'moderation_state',
      'bundle' => 'wiki_entry',
      'label' => 'Moderation state',
    ])->save();
    MessageTemplate::create([
      'template' => WikiNoticeManager::TEMPLATE,
      'label' => 'Wiki notice',
      'description' => 'Kernel fixture.',
      'text' => [['value' => 'Notice', 'format' => 'plain_text']],
    ])->save();

    $this->installField('field_aculta_wiki_event_type', 'string', ['max_length' => 32]);
    $this->installField('field_aculta_wiki_source_uuid', 'string', ['max_length' => 64]);
    $this->installField('field_aculta_wiki_event_version', 'string', ['max_length' => 64]);
    $this->installField('field_aculta_wiki_read', 'boolean');
    $this->installField('field_aculta_wiki_digest_sent', 'boolean');
    $this->installField('field_aculta_msg_delivery', 'string', ['max_length' => 16]);
    $this->installField('field_aculta_messenger_attempts', 'integer');
  }

  /**
   * Site notice persists for opt-out; replay creates no duplicate. */
  public function testOffPreferenceKeepsOneRequiredSiteNotice(): void {
    $user = $this->createUser();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with((int) $user->id(), 'wiki')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $manager = $this->manager($preferences);
    $subscriber = new WikiNoticeEventSubscriber($manager);
    $event = $this->event((int) $user->id());

    $subscriber->consume($event);
    $subscriber->consume($event);

    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    self::assertSame(1, (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute());
    $message = $storage->load(reset($storage->getQuery()->accessCheck(FALSE)->execute()));
    self::assertSame(1, (int) $message->get('field_aculta_wiki_digest_sent')->value);
    self::assertSame('sent', $message->get('field_aculta_msg_delivery')->value);
  }

  /**
   * Direct selection queues Mailer delivery once and keeps the site notice. */
  public function testImmediatePreferenceQueuesOneEmailForOneSiteNotice(): void {
    $user = $this->createUser();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with((int) $user->id(), 'wiki')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->willReturn(1);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::once())->method('get')->with('aculta_wiki_messenger_email')->willReturn($queue);
    $manager = $this->manager($preferences, $queueFactory);
    $subscriber = new WikiNoticeEventSubscriber($manager);
    $event = $this->event((int) $user->id());

    $subscriber->consume($event);
    $subscriber->consume($event);

    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    self::assertSame(1, (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute());
    $message = $storage->load(reset($storage->getQuery()->accessCheck(FALSE)->execute()));
    self::assertSame(1, (int) $message->get('field_aculta_wiki_digest_sent')->value);
    self::assertSame('pending', $message->get('field_aculta_msg_delivery')->value);
  }

  /**
   * A different account cannot change the persisted Wiki read state.
   */
  public function testReadStateRejectsAnotherAccountWithoutMutation(): void {
    $owner = $this->createUser();
    $other = User::create([
      'name' => 'wiki_read_other',
      'mail' => 'wiki-read-other@example.invalid',
      'status' => 1,
    ]);
    $other->save();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $manager = $this->manager($preferences);
    (new WikiNoticeEventSubscriber($manager))->consume($this->event((int) $owner->id()));

    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    $message = $storage->load(reset($storage->getQuery()->accessCheck(FALSE)->execute()));
    self::assertSame(0, (int) $message->get('field_aculta_wiki_read')->value);
    self::assertFalse($manager->setRead($message, $other, TRUE));
    self::assertSame(0, (int) $storage->load($message->id())->get('field_aculta_wiki_read')->value);
  }

  /**
   * Central read state rejects a superseded Wiki source.
   */
  public function testCentralReadStateChangesForCurrentReviewerOnly(): void {
    $reviewer = $this->createUser();
    $role = Role::create(['id' => 'wiki_reviewer', 'label' => 'Wiki reviewer']);
    foreach ([
      'edit any wiki_entry content',
      'use wiki_editorial transition review_to_draft',
      'use wiki_editorial transition review_to_published',
      'view all wiki_entry revisions',
    ] as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
    $reviewer->addRole($role->id());
    $reviewer->save();

    $entry = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete em revisão',
      'uid' => $reviewer->id(),
      'status' => 1,
      'moderation_state' => 'review',
    ]);
    $entry->save();

    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $manager = $this->manager($preferences, NULL, $this->domainPurposeManager());
    $event = new MessengerNoticeEvent(
      'wiki.review_requested',
      'aculta_wiki',
      'node',
      (string) $entry->uuid(),
      (string) $entry->getRevisionId(),
      (int) $reviewer->id(),
      'wiki',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'wiki:review_requested:' . $entry->uuid() . ':' . $entry->getRevisionId() . ':' . $reviewer->id(),
      ['notice_type' => 'review_requested', 'mail_policy' => 'user_preference'],
    );
    $message = $manager->record($event);
    self::assertNotNull($message);
    self::assertSame(0, (int) $message->get('field_aculta_wiki_read')->value);

    $central = new NoticeReadStateManager(
      $this->container->get('entity_type.manager'),
      ['wiki' => $manager],
    );
    self::assertTrue($central->setRead((int) $message->id(), $reviewer, TRUE));
    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    self::assertSame(1, (int) $storage->load($message->id())->get('field_aculta_wiki_read')->value);

    $entry->set('moderation_state', 'published');
    $entry->setNewRevision(TRUE);
    $entry->save();
    self::assertFalse($central->setRead((int) $message->id(), $reviewer, FALSE));
    self::assertSame(1, (int) $storage->load($message->id())->get('field_aculta_wiki_read')->value);
  }

  /**
   * Batch revision resolution counts only current, accessible review notices.
   */
  public function testUnreadCountBatchResolvesReviewRevisionsAndRevocation(): void {
    $reviewer = $this->createReviewer('wiki_batch_reviewer');
    $uid = (int) $reviewer->id();
    $entryA = $this->createReviewEntry($reviewer);
    $entryB = $this->createReviewEntry($reviewer);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $manager = $this->manager($preferences, NULL, $this->domainPurposeManager());
    self::assertNotNull($manager->record($this->reviewEvent($uid, $entryA)));
    self::assertNotNull($manager->record($this->reviewEvent($uid, $entryB)));

    self::assertSame(2, $manager->unreadCount($uid));

    $entryA->setNewRevision(TRUE);
    $entryA->set('moderation_state', 'published');
    $entryA->save();
    self::assertSame(1, $manager->unreadCount($uid), 'A superseded review revision is not counted after access changes.');
  }

  /**
   * Digest includes and acknowledges a currently eligible review notice.
   */
  public function testReviewDigestIncludesAndAcknowledgesEligibleMessage(): void {
    $reviewer = $this->createReviewer('wiki_digest_reviewer');
    $entry = $this->createReviewEntry($reviewer);
    $uid = (int) $reviewer->id();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_DIGEST);
    $manager = $this->manager($preferences, NULL, $this->domainPurposeManager());
    $message = $manager->record($this->reviewEvent($uid, $entry));
    self::assertNotNull($message);
    $replayedMessage = $manager->record($this->reviewEvent($uid, $entry));
    self::assertSame($message->id(), $replayedMessage?->id(), 'Replaying the same source revision reuses the persisted notice.');

    $rows = $manager->digestRowsFor($uid);

    self::assertCount(1, $rows);
    self::assertSame('wiki', $rows[0]['tipo']);
    self::assertSame(1, $rows[0]['quantidade']);
    self::assertSame((int) $message->id(), $rows[0]['delivery_id']);
    $manager->markDigestSent($uid, [$rows[0]['delivery_id']]);
    $stored = $this->container->get('entity_type.manager')->getStorage('message')->load($message->id());
    self::assertSame(1, (int) $stored->get('field_aculta_wiki_digest_sent')->value);
  }

  /**
   * A review that is published before digest delivery is omitted and discarded.
   */
  public function testDigestDiscardsReviewAfterSourceRevisionIsPublished(): void {
    $reviewer = $this->createReviewer('wiki_digest_revoked_reviewer');
    $entry = $this->createReviewEntry($reviewer);
    $uid = (int) $reviewer->id();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_DIGEST);
    $manager = $this->manager($preferences, NULL, $this->domainPurposeManager());
    $message = $manager->record($this->reviewEvent($uid, $entry));
    self::assertNotNull($message);
    self::assertCount(1, $manager->digestRowsFor($uid));

    $entry->setNewRevision(TRUE);
    $entry->set('moderation_state', 'published');
    $entry->save();

    self::assertSame([], $manager->digestRowsFor($uid));
    $stored = $this->container->get('entity_type.manager')->getStorage('message')->load($message->id());
    self::assertSame(1, (int) $stored->get('field_aculta_wiki_digest_sent')->value, 'A revoked review is discarded from email and is not retried later.');
  }

  /**
   * A queued direct email is cancelled if its review is no longer accessible.
   */
  public function testQueuedDirectEmailIsCancelledAfterReviewAccessIsRevoked(): void {
    $reviewer = $this->createReviewer('wiki_direct_revoked_reviewer');
    $entry = $this->createReviewEntry($reviewer);
    $uid = (int) $reviewer->id();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with($uid, 'wiki')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->willReturn(1);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::once())->method('get')->with('aculta_wiki_messenger_email')->willReturn($queue);
    $manager = $this->manager($preferences, $queueFactory, $this->domainPurposeManager());
    $message = $manager->record($this->reviewEvent($uid, $entry));
    self::assertNotNull($message);

    // Replacing the current review revision invalidates the pending delivery.
    $entry->setNewRevision(TRUE);
    $entry->set('moderation_state', 'published');
    $entry->save();

    $this->container->get('user.data')->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->willReturn(TRUE);
    $lock->expects(self::once())->method('release');
    $worker = new WikiNoticeEmailWorker(
      [],
      'aculta_wiki_messenger_email',
      ['cron' => ['time' => 60]],
      $this->container->get('entity_type.manager'),
      $manager,
      $preferences,
      new EmailConfirmationPolicy(
        $this->container->get('user.data'),
        $this->container->get('entity_type.manager'),
        $this->container->get('module_handler'),
      ),
      $mailer,
      $lock,
    );

    $worker->processItem(['message_id' => (int) $message->id()]);

    $stored = $this->container->get('entity_type.manager')->getStorage('message')->load($message->id());
    self::assertSame('cancelled', $stored->get('field_aculta_msg_delivery')->value);
    self::assertSame(0, (int) $stored->get('field_aculta_wiki_read')->value, 'The required site notice remains available after its email is cancelled.');
  }

  /**
   * Creates an account authorized to review Wiki revisions.
   */
  private function createReviewer(string $name): User {
    $reviewer = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => 1,
    ]);
    $role = Role::create(['id' => $name . '_role', 'label' => 'Wiki reviewer']);
    foreach ([
      'edit any wiki_entry content',
      'use wiki_editorial transition review_to_draft',
      'use wiki_editorial transition review_to_published',
      'view all wiki_entry revisions',
    ] as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
    $reviewer->addRole($role->id());
    $reviewer->save();
    return $reviewer;
  }

  /**
   * Creates a published Wiki node currently awaiting editorial review.
   */
  private function createReviewEntry(User $reviewer): Node {
    $entry = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Verbete para digest',
      'uid' => $reviewer->id(),
      'status' => 1,
      'moderation_state' => 'review',
    ]);
    $entry->save();
    return $entry;
  }

  /**
   * Builds a canonical review-request event tied to a real Node revision.
   */
  private function reviewEvent(int $uid, Node $entry): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      'wiki.review_requested',
      'aculta_wiki',
      'node',
      (string) $entry->uuid(),
      (string) $entry->getRevisionId(),
      $uid,
      'wiki',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'wiki:review_requested:' . $entry->uuid() . ':' . $entry->getRevisionId() . ':' . $uid,
      ['notice_type' => 'review_requested', 'mail_policy' => 'user_preference'],
    );
  }

  /**
   * Creates an active account for the notice recipient.
   */
  private function createUser(): User {
    $user = User::create([
      'name' => 'wiki_notice_recipient',
      'mail' => 'wiki-notice@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    return $user;
  }

  /**
   * Builds the canonical editorial proposal outcome event.
   */
  private function event(int $uid): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      'wiki.proposal_outcome',
      'aculta_wiki',
      'aculta_wiki_proposal',
      'proposal-uuid',
      'revision-1',
      $uid,
      'wiki',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'wiki:wiki.proposal_outcome:proposal-uuid:revision-1:' . $uid,
      ['notice_type' => 'proposal_outcome', 'mail_policy' => 'user_preference'],
    );
  }

  /**
   * Creates the real Message manager with isolated dependencies.
   */
  private function manager(
    AvisoPreferenciasInterface $preferences,
    ?QueueFactory $queueFactory = NULL,
    ?DomainPurposeManager $domainPurposes = NULL,
  ): WikiNoticeManager {
    $queueFactory ??= $this->createMock(QueueFactory::class);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return new WikiNoticeManager(
      $this->container->get('entity_type.manager'),
      $domainPurposes ?? (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      $preferences,
      $queueFactory,
      $lock,
      new NullLogger(),
    );
  }

  /**
   * Builds canonical Wiki URLs without enabling the Domain module.
   */
  private function domainPurposeManager(): DomainPurposeManager {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(FALSE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('wiki420_aculta_org')->willReturn($domain);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('domain')->willReturn($domainStorage);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn(NULL);
    return new DomainPurposeManager(
      $entities,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

  /**
   * Creates one field storage/config pair for the fixture bundle.
   */
  private function installField(string $name, string $type, array $settings = []): void {
    FieldStorageConfig::create([
      'entity_type' => 'message',
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'message',
      'bundle' => WikiNoticeManager::TEMPLATE,
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

}
