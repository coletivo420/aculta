<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\Event\WikiProposalOutcomeRecordedEvent;
use Drupal\aculta_wiki\Plugin\QueueWorker\WikiEditorialEventWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests event dispatch and lease-based retry behavior. */
#[CoversClass(WikiEditorialEventWorker::class)]
#[Group('aculta_wiki')]
final class WikiEditorialEventWorkerTest extends TestCase {

  private const ENTRY_UUID = 'b4a5e745-63b0-4f5d-94a1-57b25283db0e';

  private const PROPOSAL_UUID = 'e9d0ae94-9c81-4d09-9ab0-c3d2c9a579f5';

  /**
   * Dispatches a review envelope to its registered consumer. */
  public function testDispatchesReviewPayload(): void {
    $event = new WikiEntryReviewRequestedEvent(self::ENTRY_UUID, 18);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('getListeners')->with(WikiEntryReviewRequestedEvent::NAME)->willReturn([static function (): void {}]);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(static fn ($actual): bool => $actual instanceof WikiEntryReviewRequestedEvent && $actual->entryUuid === self::ENTRY_UUID && $actual->entryRevisionId === 18), WikiEntryReviewRequestedEvent::NAME)->willReturn($event);

    $this->worker($dispatcher)->processItem($event->toQueuePayload());
  }

  /**
   * Dispatches a proposal outcome envelope to its registered consumer. */
  public function testDispatchesProposalOutcomePayload(): void {
    $event = new WikiProposalOutcomeRecordedEvent(self::PROPOSAL_UUID, 22, 7, 'accepted');
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('getListeners')->with(WikiProposalOutcomeRecordedEvent::NAME)->willReturn([static function (): void {}]);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(static fn ($actual): bool => $actual instanceof WikiProposalOutcomeRecordedEvent && $actual->proposalUuid === self::PROPOSAL_UUID && $actual->recipientUid === 7 && $actual->outcome === 'accepted'), WikiProposalOutcomeRecordedEvent::NAME)->willReturn($event);

    $this->worker($dispatcher)->processItem($event->toQueuePayload());
  }

  /**
   * Preserves an event when the Messenger consumer is not installed. */
  public function testLeavesEventPendingWhenNoConsumerIsRegistered(): void {
    $event = new WikiEntryReviewRequestedEvent(self::ENTRY_UUID, 18);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('getListeners')->willReturn([]);
    $dispatcher->expects(self::never())->method('dispatch');

    $this->expectException(\UnexpectedValueException::class);
    $this->worker($dispatcher)->processItem($event->toQueuePayload());
  }

  /**
   * Rejects malformed data without dispatching an event. */
  public function testRejectsMalformedPayloadForRetry(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $this->expectException(\UnexpectedValueException::class);
    $this->worker($dispatcher)->processItem(['schema_version' => 1, 'event' => WikiEntryReviewRequestedEvent::NAME]);
  }

  /**
   * Builds the queue worker with the supplied dispatcher. */
  private function worker(EventDispatcherInterface $dispatcher): WikiEditorialEventWorker {
    return new WikiEditorialEventWorker([], 'aculta_wiki_editorial_events', [], $dispatcher);
  }

}
