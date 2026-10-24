<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Wiki\Unit;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\message\MessageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\domain\DomainInterface;
use Drupal\user\UserInterface;
use Drupal\domain\DomainNegotiatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests persistent Wiki notice creation and email preference routing. */
#[CoversClass(WikiNoticeManager::class)]
#[Group('aculta_messenger')]
final class WikiNoticeManagerTest extends UnitTestCase {

  /**
   * Persists the site notice and queues direct email as a separate channel. */
  public function testDirectModePersistsAndQueuesEmail(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('id')->willReturn('42');
    $message->expects(self::once())->method('save')->willReturn(1);
    [$entities, $queueFactory] = $this->dependencies($message);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'wiki')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->with(['message_id' => 42])->willReturn(5);
    $queueFactory->method('get')->with('aculta_wiki_messenger_email')->willReturn($queue);
    $manager = $this->manager($entities, $preferences, $queueFactory);

    $result = $manager->record($this->event());

    self::assertSame($message, $result);
  }

  /**
   * Persists mandatory site notices without queuing email when opted out. */
  public function testOffModeKeepsSiteNoticeAndSkipsEmail(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('id')->willReturn('42');
    $message->expects(self::once())->method('save')->willReturn(1);
    [$entities, $queueFactory] = $this->dependencies($message);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'wiki')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $queueFactory->expects(self::never())->method('get');

    self::assertSame($message, $this->manager($entities, $preferences, $queueFactory)->record($this->event()));
  }

  /**
   * Unread count batch-loads Wiki revisions while revalidating each target.
   */
  public function testUnreadCountBatchLoadsReviewRevisions(): void {
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('hasPermission')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($account);

    $messages = [
      $this->noticeMessage(27, 'wiki-uuid-a', '11'),
      $this->noticeMessage(27, 'wiki-uuid-b', '12'),
      $this->noticeMessage(27, 'wiki-uuid-c', '13'),
    ];
    $messageQuery = $this->createMock(QueryInterface::class);
    $messageQuery->method('accessCheck')->willReturnSelf();
    $messageQuery->method('condition')->willReturnSelf();
    $messageQuery->method('execute')->willReturn([101, 102, 103]);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($messageQuery);
    $messageStorage->method('loadMultiple')->with([101, 102, 103])->willReturn($messages);

    $nodeQuery = $this->createMock(QueryInterface::class);
    $nodeQuery->method('accessCheck')->willReturnSelf();
    $nodeQuery->expects(self::exactly(2))->method('condition')->willReturnSelf();
    $nodeQuery->method('execute')->willReturn([201, 202, 203]);
    $nodeStorage = $this->createMock(RevisionableStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($nodeQuery);
    $nodeStorage->method('loadMultiple')->with([201, 202, 203])->willReturn([
      $this->revision('wiki-uuid-a', 11),
      $this->revision('wiki-uuid-b', 12, 'review'),
      $this->revision('wiki-uuid-c', 13),
    ]);
    $nodeStorage->expects(self::once())
      ->method('loadMultipleRevisions')
      ->with([11, 12, 13])
      ->willReturn([
        11 => $this->revision('wiki-uuid-a', 11),
        12 => $this->revision('wiki-uuid-b', 12, 'published'),
        13 => $this->revision('wiki-uuid-c', 13, 'review', FALSE),
      ]);

    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('wiki420_aculta_org')->willReturn($domain);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn(NULL);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $userStorage,
      'message' => $messageStorage,
      'node' => $nodeStorage,
      'domain' => $domainStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $domainPurposes = new DomainPurposeManager(
      $entities,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $manager = new WikiNoticeManager(
      $entities,
      $domainPurposes,
      $this->createMock(AvisoPreferenciasInterface::class),
      $this->createMock(QueueFactory::class),
      $this->lock(),
      $this->createMock(LoggerInterface::class),
    );

    try {
      self::assertSame(1, $manager->unreadCount(27));
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * Creates the manager using only mocked I/O dependencies. */
  private function manager(
    EntityTypeManagerInterface $entities,
    AvisoPreferenciasInterface $preferences,
    QueueFactory $queueFactory,
  ): WikiNoticeManager {
    $domainPurposes = new DomainPurposeManager(
      $entities,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    return new WikiNoticeManager(
      $entities,
      $domainPurposes,
      $preferences,
      $queueFactory,
      $this->lock(),
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Creates user, Message storage, query, and queue factory mocks. */
  private function dependencies(MessageInterface $message): array {
    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($user);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($query);
    $messageStorage->expects(self::once())->method('create')->with(self::callback(
      static fn(array $values): bool => $values['template'] === WikiNoticeManager::TEMPLATE
        && $values['uid'] === 27
        && $values['field_aculta_wiki_read'] === 0,
    ))->willReturn($message);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $userStorage,
      'message' => $messageStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    return [$entities, $this->createMock(QueueFactory::class)];
  }

  /**
   * Builds a valid Wiki notice event. */
  private function event(): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      'wiki.proposal_outcome',
      'aculta_wiki',
      'aculta_wiki_proposal',
      'e56f3248-f7b3-4e55-9ea1-91b8e6a0df9f',
      '12',
      27,
      'wiki',
      new \DateTimeImmutable('2026-10-24T00:00:00Z'),
      'wiki:test:12:27',
      ['notice_type' => 'proposal_outcome', 'mail_policy' => 'user_preference'],
    );
  }

  /**
   * Creates one stored-looking review notice for an owner.
   */
  private function noticeMessage(int $uid, string $uuid, string $revision): MessageInterface {
    $values = [
      'field_aculta_wiki_event_type' => 'review_requested',
      'field_aculta_wiki_source_uuid' => $uuid,
      'field_aculta_wiki_event_version' => $revision,
    ];
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn(WikiNoticeManager::TEMPLATE);
    $message->method('getOwnerId')->willReturn($uid);
    $message->method('get')->willReturnCallback(function (string $field) use ($values): FieldItemListInterface {
      $list = $this->createMock(FieldItemListInterface::class);
      $list->method('__get')->with('value')->willReturn($values[$field]);
      return $list;
    });
    return $message;
  }

  /**
   * Creates a visible, default Wiki review revision.
   */
  private function revision(string $uuid, int $revisionId, string $state = 'review', bool $viewable = TRUE): NodeInterface {
    $moderationState = $this->createMock(FieldItemListInterface::class);
    $moderationState->method('__get')->with('value')->willReturn($state);
    $access = $this->createMock(AccessResultInterface::class);
    $access->method('isAllowed')->willReturn($viewable);
    $revision = $this->createMock(NodeInterface::class);
    $revision->method('uuid')->willReturn($uuid);
    $revision->method('getRevisionId')->willReturn($revisionId);
    $revision->method('isDefaultRevision')->willReturn(TRUE);
    $revision->method('hasField')->with('moderation_state')->willReturn(TRUE);
    $revision->method('get')->with('moderation_state')->willReturn($moderationState);
    $revision->method('access')->willReturn($access);
    $revision->method('id')->willReturn((string) $revisionId);
    return $revision;
  }

  /**
   * Creates a lock backend that grants the idempotency lock. */
  private function lock(): LockBackendInterface {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return $lock;
  }

}
