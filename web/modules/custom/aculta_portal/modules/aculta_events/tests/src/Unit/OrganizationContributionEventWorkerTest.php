<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Drupal\aculta_events\Plugin\QueueWorker\OrganizationContributionEventWorker;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests validation and dispatch of minimal Events proposal events. */
#[CoversClass(OrganizationContributionEventWorker::class)]
final class OrganizationContributionEventWorkerTest extends UnitTestCase {

  /**
   * Verifies valid queue data dispatches a minimal outcome event.
   */
  public function testDispatchesValidatedEventWithoutPrivatePayload(): void {
    $data = [
      'schema_version' => 1,
      'event' => OrganizationContributionOutcomeRecordedEvent::NAME,
      'source' => 'events_location',
      'source_uuid' => 'f6be4a3e-6d93-40c8-a47a-379d170dc852',
      'source_revision_id' => 12,
      'recipient_uid' => 7,
      'outcome' => 'accepted',
    ];
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('getListeners')->with(OrganizationContributionOutcomeRecordedEvent::NAME)->willReturn([static fn() => NULL]);
    $dispatcher->expects(self::once())->method('dispatch')->with(
      self::callback(static fn ($event): bool => $event instanceof OrganizationContributionOutcomeRecordedEvent
        && $event->source === 'events_location' && $event->recipientUid === 7 && $event->outcome === 'accepted'),
      OrganizationContributionOutcomeRecordedEvent::NAME,
    );
    $worker = new OrganizationContributionEventWorker([], 'aculta_events_contribution_events', [], $dispatcher);
    $worker->processItem($data);
  }

  /**
   * Event proposals share the minimal outcome envelope without private data.
   */
  public function testDispatchesEventProposalOutcome(): void {
    $data = [
      'schema_version' => 1,
      'event' => OrganizationContributionOutcomeRecordedEvent::NAME,
      'source' => 'events_event',
      'source_uuid' => 'f6be4a3e-6d93-40c8-a47a-379d170dc852',
      'source_revision_id' => 13,
      'recipient_uid' => 9,
      'outcome' => 'needs_changes',
    ];
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('getListeners')->willReturn([static fn() => NULL]);
    $dispatcher->expects(self::once())->method('dispatch')->with(
      self::callback(static fn ($event): bool => $event instanceof OrganizationContributionOutcomeRecordedEvent
        && $event->source === 'events_event' && $event->recipientUid === 9 && $event->outcome === 'needs_changes'),
      OrganizationContributionOutcomeRecordedEvent::NAME,
    );
    (new OrganizationContributionEventWorker([], 'aculta_events_contribution_events', [], $dispatcher))->processItem($data);
  }

  /**
   * Verifies queue data cannot include private proposal fields.
   */
  public function testRejectsPayloadContainingPrivateFields(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $worker = new OrganizationContributionEventWorker([], 'aculta_events_contribution_events', [], $dispatcher);
    $this->expectException(\UnexpectedValueException::class);
    $worker->processItem([
      'schema_version' => 1,
      'event' => OrganizationContributionOutcomeRecordedEvent::NAME,
      'source' => 'events_organization',
      'source_uuid' => 'f6be4a3e-6d93-40c8-a47a-379d170dc852',
      'source_revision_id' => 12,
      'recipient_uid' => 7,
      'outcome' => 'accepted',
      'rationale' => 'private',
    ]);
  }

  /**
   * Existing queued organization events remain readable after schema extension. */
  public function testDispatchesLegacyOrganizationEnvelope(): void {
    $data = [
      'schema_version' => 1,
      'event' => OrganizationContributionOutcomeRecordedEvent::NAME,
      'source_uuid' => 'f6be4a3e-6d93-40c8-a47a-379d170dc852',
      'source_revision_id' => 12,
      'recipient_uid' => 7,
      'outcome' => 'accepted',
    ];
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('getListeners')->with(OrganizationContributionOutcomeRecordedEvent::NAME)->willReturn([static fn() => NULL]);
    $dispatcher->expects(self::once())->method('dispatch')->with(
      self::callback(static fn($event): bool => $event instanceof OrganizationContributionOutcomeRecordedEvent
        && $event->source === 'events_organization'),
      OrganizationContributionOutcomeRecordedEvent::NAME,
    );
    (new OrganizationContributionEventWorker([], 'aculta_events_contribution_events', [], $dispatcher))->processItem($data);
  }

}
