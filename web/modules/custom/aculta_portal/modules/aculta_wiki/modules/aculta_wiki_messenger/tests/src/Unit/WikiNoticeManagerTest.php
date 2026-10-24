<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki_messenger\WikiNoticeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\domain\DomainNegotiatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests persistent Wiki notice creation and email preference routing. */
#[CoversClass(WikiNoticeManager::class)]
#[Group('aculta_wiki_messenger')]
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
   * Creates a lock backend that grants the idempotency lock. */
  private function lock(): LockBackendInterface {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return $lock;
  }

}
