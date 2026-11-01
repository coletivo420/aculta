<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Magazine\Kernel;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Magazine\MagazineNoticeManager;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_magazine\Hook\MagazineNoticeEventPublisher;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Component\Datetime\TimeInterface;
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
use Drupal\workflows\Entity\Workflow;
use Drupal\flag\FlagServiceInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies Magazine delivery through shared Drupal Message storage. */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class MagazineNoticePersistenceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'message', 'node',
    'workflows', 'content_moderation',
  ];

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('message');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'filter', 'message', 'node', 'workflows', 'content_moderation']);
    NodeType::create(['type' => 'revista', 'name' => 'Revista', 'new_revision' => TRUE])->save();
    $workflow = Workflow::create([
      'id' => 'revista',
      'type' => 'content_moderation',
      'label' => 'Editorial da Revista',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Rascunho', 'published' => FALSE, 'default_revision' => FALSE, 'weight' => 0],
          'review' => ['label' => 'Em revisão', 'published' => FALSE, 'default_revision' => FALSE, 'weight' => 1],
          'published' => ['label' => 'Publicado', 'published' => TRUE, 'default_revision' => TRUE, 'weight' => 2],
        ],
        'transitions' => [
          'draft_to_review' => ['label' => 'Enviar para revisão', 'from' => ['draft'], 'to' => 'review', 'weight' => 0],
          'review_to_draft' => [
            'label' => 'Devolver para ajustes',
            'from' => ['review'],
            'to' => 'draft',
            'weight' => 1,
          ],
          'publish' => [
            'label' => 'Publicar',
            'from' => ['draft', 'review', 'published'],
            'to' => 'published',
            'weight' => 2,
          ],
        ],
      ],
    ]);
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'revista');
    $workflow->save();
    MessageTemplate::create([
      'template' => MagazineNoticeManager::TEMPLATE,
      'label' => 'Aviso da Revista',
      'description' => 'Fixture do Kernel.',
      'text' => [['value' => 'Aviso', 'format' => 'plain_text']],
    ])->save();
    $this->installMessageField('field_aculta_mag_event_type', 'string', ['max_length' => 32]);
    $this->installMessageField('field_aculta_mag_source_uuid', 'string', ['max_length' => 64]);
    $this->installMessageField('field_aculta_mag_event_version', 'string', ['max_length' => 64]);
    $this->installMessageField('field_aculta_mag_read', 'boolean');
    $this->installMessageField('field_aculta_mag_digest_sent', 'boolean');
    $this->installMessageField('field_aculta_msg_delivery', 'string', ['max_length' => 24]);
    $this->installMessageField('field_aculta_messenger_attempts', 'integer');
  }

  /**
   * Opt-out keeps one site notice and event replay does not duplicate it. */
  public function testSiteNoticePersistsAndDeduplicatesWhenEmailIsOff(): void {
    $user = $this->createActiveUser();
    $node = $this->publishedNode($user);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with((int) $user->id(), 'revista')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $manager = $this->manager($preferences);
    $event = $this->event($user, $node);

    $first = $manager->record($event);
    $second = $manager->record($event);

    self::assertNotNull($first);
    self::assertSame((int) $first->id(), (int) $second?->id());
    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    self::assertSame(1, (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute());
    self::assertSame(1, (int) $first->get('field_aculta_mag_digest_sent')->value);
    self::assertSame('sent', $first->get('field_aculta_msg_delivery')->value);
  }

  /**
   * Direct email is queued while the required site notice remains stored. */
  public function testImmediateEmailUsesMailerQueueAndKeepsSiteNotice(): void {
    $user = $this->createActiveUser();
    $node = $this->publishedNode($user);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with((int) $user->id(), 'revista')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->willReturn(17);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::once())
      ->method('get')
      ->with('aculta_magazine_messenger_email')
      ->willReturn($queue);
    $manager = $this->manager($preferences, $queueFactory);

    $message = $manager->record($this->event($user, $node));

    self::assertNotNull($message);
    self::assertSame('pending', $message->get('field_aculta_msg_delivery')->value);
    self::assertSame(1, (int) $message->get('field_aculta_mag_digest_sent')->value);
  }

  /**
   * Publishes the author audience for a real saved Magazine publication. */
  public function testPublisherTargetsActiveEditorialAuthorAfterPublicationSave(): void {
    $author = $this->createActiveUser();
    $actor = $this->createActiveUser();
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Publicação criada por editor',
      'uid' => $author->id(),
      'revision_uid' => $actor->id(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $node->save();
    $settings = $this->createMock(Config::class);
    $settings->method('get')->with('magazine_events.published')->willReturn(TRUE);
    $settingsFactory = $this->createMock(ConfigFactoryInterface::class);
    $settingsFactory->method('get')->with('aculta_messenger.settings')->willReturn($settings);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())->method('publish')->with(self::callback(static function (MessengerNoticeEvent $event) use ($author, $node): bool {
      return $event->eventType() === 'magazine.publication_published'
        && $event->recipientUid() === (int) $author->id()
        && $event->sourceEntityUuid() === $node->uuid()
        && $event->purpose() === 'magazine';
    }));
    $flags = $this->createMock(FlagServiceInterface::class);
    $flags->method('getFlagById')->with('seguir_publicacao')->willReturn(NULL);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_793_000_000);
    $producer = new MagazineNoticeEventPublisher(
      $flags,
      $this->domainPurposeManager(),
      $this->container->get('entity_type.manager'),
      $publisher,
      $settingsFactory,
      $time,
    );

    $producer->entityInsert($node);
  }

  /**
   * Editorial submission alerts eligible authors with access to the revision. */
  public function testPublisherTargetsAuthorWhenReviewRevisionIsSubmitted(): void {
    $author = $this->createActiveUser();
    $editor = $this->createActiveUser();
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Rascunho editorial',
      'uid' => $author->id(),
      'revision_uid' => $editor->id(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $node->save();
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId((int) $author->id());
    $node->set('moderation_state', 'draft');
    $node->save();
    $original = clone $node;
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId((int) $editor->id());
    $node->set('moderation_state', 'review');
    $node->save();
    $node->setOriginal($original);

    self::assertSame('draft', $node->getOriginal()?->get('moderation_state')->value);
    self::assertSame('review', $node->get('moderation_state')->value);
    self::assertTrue($node->access('view', $author, TRUE)->isAllowed());
    $domainPurposes = $this->domainPurposeManager();
    $url = $domainPurposes->routeUrl('magazine', 'entity.node.latest_version', ['node' => $node->id()]);
    self::assertNotNull($url);
    $revisionAccess = $domainPurposes->runInPurpose('magazine', static fn() => $url->access($author, TRUE));
    self::assertTrue($revisionAccess->isAllowed());

    $this->editorialProducer($author, 'review_submitted')->entityUpdate($node);
  }

  /**
   * Editorial event preferences can suppress a review submission notice. */
  public function testEditorialSubmissionCanBeDisabledByAdministrator(): void {
    $author = $this->createActiveUser();
    $editor = $this->createActiveUser();
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Rascunho sem aviso',
      'uid' => $author->id(),
      'revision_uid' => $editor->id(),
      'status' => 0,
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $original = clone $node;
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId((int) $editor->id());
    $node->set('moderation_state', 'review');
    $node->save();
    $node->setOriginal($original);

    $this->editorialProducer($author, 'review_submitted', FALSE)->entityUpdate($node);
  }

  /**
   * Editorial return alerts the author after an editor requests changes. */
  public function testPublisherTargetsAuthorWhenReviewIsReturned(): void {
    $author = $this->createActiveUser();
    $editor = $this->createActiveUser();
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Publicação em revisão',
      'uid' => $author->id(),
      'revision_uid' => $editor->id(),
      'status' => 0,
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId((int) $editor->id());
    $node->set('moderation_state', 'review');
    $node->save();
    $review = clone $node;
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId((int) $editor->id());
    $node->set('moderation_state', 'draft');
    $node->save();
    $node->setOriginal($review);

    $this->editorialProducer($author, 'review_returned')->entityUpdate($node);
  }

  /**
   * Stores and opens an editorial notice on an unpublished default revision. */
  public function testEditorialNoticeUsesAuthorizedCanonicalRoute(): void {
    $author = $this->createActiveUser();
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Rascunho devolvido',
      'uid' => $author->id(),
      'status' => 0,
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $manager = $this->manager($preferences);

    $message = $manager->record($this->editorialEvent($author, $node, 'review_returned'));

    self::assertNotNull($message);
    $resolved = $manager->resolve($message, $author);
    self::assertNotNull($resolved);
    self::assertSame('Uma publicação sua voltou da revisão editorial para ajustes.', (string) $resolved['summary']);
    self::assertStringContainsString('/node/' . $node->id(), $resolved['url']->toString());
  }

  /**
   * An uninstalled optional bundle is ignored without querying its fields. */
  public function testMissingMagazineBundleDisablesAdapterSafely(): void {
    $user = $this->createActiveUser();
    $node = $this->publishedNode($user);
    $event = $this->event($user, $node);
    $entityTypeManager = $this->container->get('entity_type.manager');
    $fieldConfigStorage = $entityTypeManager->getStorage('field_config');
    $fieldStorage = $entityTypeManager->getStorage('field_storage_config');
    foreach ([
      'field_aculta_mag_event_type',
      'field_aculta_mag_source_uuid',
      'field_aculta_mag_event_version',
      'field_aculta_mag_read',
      'field_aculta_mag_digest_sent',
      'field_aculta_msg_delivery',
      'field_aculta_messenger_attempts',
    ] as $field) {
      $fieldConfigStorage->load('message.' . MagazineNoticeManager::TEMPLATE . '.' . $field)?->delete();
      $fieldStorage->load('message.' . $field)?->delete();
    }
    $entityTypeManager->getStorage('message_template')->load(MagazineNoticeManager::TEMPLATE)?->delete();
    $manager = $this->manager($this->createMock(AvisoPreferenciasInterface::class));
    $items = [];

    self::assertNull($manager->record($event));
    self::assertSame(0, $manager->unreadCount((int) $user->id()));
    self::assertSame([], $manager->pendingRecipientIds());
    self::assertSame([], $manager->digestRowsFor((int) $user->id()));
    self::assertFalse($manager->addPersonalNoticesPage($items, $user, [], 10, 0));
    self::assertSame([], $items);
  }

  /**
   * Refuses a queued event after the source moved to another revision. */
  public function testStalePublicationRevisionIsNotPersisted(): void {
    $user = $this->createActiveUser();
    $node = $this->publishedNode($user);
    $event = $this->event($user, $node);
    $node->setNewRevision(TRUE);
    $node->setTitle('Revisão mais recente')->save();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $manager = $this->manager($preferences);

    self::assertNull($manager->record($event));
    self::assertSame(0, (int) $this->container->get('entity_type.manager')->getStorage('message')
      ->getQuery()->accessCheck(FALSE)->count()->execute());
  }

  /**
   * Counts multiple event rows for one publication through batched resolution. */
  public function testUnreadCountResolvesMultipleNoticeEventsForSamePublication(): void {
    $user = $this->createActiveUser();
    $node = $this->publishedNode($user);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $manager = $this->manager($preferences);
    self::assertNotNull($manager->record($this->event($user, $node, 'published')));
    self::assertNotNull($manager->record($this->event($user, $node, 'updated')));

    self::assertSame(2, $manager->unreadCount((int) $user->id()));
  }

  /**
   * Creates the actual adapter with controlled preferences and locks. */
  private function manager(AvisoPreferenciasInterface $preferences, ?QueueFactory $queueFactory = NULL): MagazineNoticeManager {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return new MagazineNoticeManager(
      $this->container->get('entity_type.manager'),
      $this->domainPurposeManager(),
      $preferences,
      $queueFactory ?? $this->createMock(QueueFactory::class),
      $lock,
      $this->createMock(FlagServiceInterface::class),
      new NullLogger(),
    );
  }

  /**
   * Creates a producer fixture and asserts its editorial event envelope. */
  private function editorialProducer(User $author, string $noticeType, bool $enabled = TRUE): MagazineNoticeEventPublisher {
    $settings = $this->createMock(Config::class);
    $settings->method('get')->with('magazine_events.' . $noticeType)->willReturn($enabled);
    $settingsFactory = $this->createMock(ConfigFactoryInterface::class);
    $settingsFactory->method('get')->with('aculta_messenger.settings')->willReturn($settings);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    if ($enabled) {
      $eventType = $noticeType === 'review_submitted'
        ? 'magazine.editorial_review_submitted'
        : 'magazine.editorial_review_returned';
      $publisher->expects(self::once())->method('publish')->with(self::callback(static function (MessengerNoticeEvent $event) use ($author, $eventType, $noticeType): bool {
        return $event->eventType() === $eventType
          && $event->recipientUid() === (int) $author->id()
          && $event->metadata()['notice_type'] === $noticeType
          && $event->purpose() === 'magazine';
      }));
    }
    else {
      $publisher->expects(self::never())->method('publish');
    }
    $flags = $this->createMock(FlagServiceInterface::class);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_793_000_000);
    return new MagazineNoticeEventPublisher(
      $flags,
      $this->domainPurposeManager(),
      $this->container->get('entity_type.manager'),
      $publisher,
      $settingsFactory,
      $time,
    );
  }

  /**
   * Builds Domain Purpose context without enabling the Domain module. */
  private function domainPurposeManager(): DomainPurposeManager {
    $domain = $this->createMock(DomainInterface::class);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('revista420_aculta_org')->willReturn($domain);
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
   * Creates an active account that can view published content. */
  private function createActiveUser(): User {
    $user = User::create(['name' => $this->randomMachineName(), 'status' => 1]);
    $user->save();
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load('authenticated');
    if ($role === NULL) {
      $role = Role::create(['id' => 'authenticated', 'label' => 'Authenticated']);
    }
    $role->grantPermission('access content')->save();
    $role->grantPermission('view latest version')->save();
    $role->grantPermission('view own unpublished content')->save();
    $user->save();
    return $user;
  }

  /**
   * Creates a default public Revista revision. */
  private function publishedNode(User $user): Node {
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Publicação Kernel',
      'uid' => $user->id(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $node->save();
    return $node;
  }

  /**
   * Creates the shared event envelope for one published revision. */
  private function event(User $user, Node $node, string $noticeType = 'published'): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      'magazine.publication_' . $noticeType,
      'aculta_magazine',
      'node',
      $node->uuid(),
      (string) $node->getRevisionId(),
      (int) $user->id(),
      'magazine',
      new \DateTimeImmutable('@' . $node->getChangedTime()),
      'magazine.publication_' . $noticeType . ':' . $node->uuid() . ':' . $node->getRevisionId() . ':' . $user->id(),
      ['notice_type' => $noticeType, 'mail_policy' => 'user_preference'],
    );
  }

  /**
   * Creates the shared envelope for one editorial transition. */
  private function editorialEvent(User $user, Node $node, string $noticeType): MessengerNoticeEvent {
    $eventType = match ($noticeType) {
      'review_submitted' => 'magazine.editorial_review_submitted',
      'review_returned' => 'magazine.editorial_review_returned',
    };
    return new MessengerNoticeEvent(
      $eventType,
      'aculta_magazine',
      'node',
      $node->uuid(),
      (string) $node->getRevisionId(),
      (int) $user->id(),
      'magazine',
      new \DateTimeImmutable('@' . $node->getChangedTime()),
      $eventType . ':' . $node->uuid() . ':' . $node->getRevisionId() . ':' . $user->id(),
      ['notice_type' => $noticeType, 'mail_policy' => 'user_preference'],
    );
  }

  /**
   * Installs one field and its instance on the notice Message bundle. */
  private function installMessageField(string $name, string $type, array $settings = []): void {
    FieldStorageConfig::create([
      'entity_type' => 'message',
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'message',
      'bundle' => MagazineNoticeManager::TEMPLATE,
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

}
