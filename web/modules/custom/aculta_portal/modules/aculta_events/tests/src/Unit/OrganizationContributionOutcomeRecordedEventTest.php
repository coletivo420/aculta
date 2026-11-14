<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the private-data-free proposal outcome event contract. */
#[CoversClass(OrganizationContributionOutcomeRecordedEvent::class)]
final class OrganizationContributionOutcomeRecordedEventTest extends UnitTestCase {

  /**
   * Verifies the event payload excludes proposal content and notes.
   */
  public function testQueuePayloadContainsOnlyStableIdentifiersAndOutcome(): void {
    $event = new OrganizationContributionOutcomeRecordedEvent(
      'events_organization',
      'f6be4a3e-6d93-40c8-a47a-379d170dc852',
      12,
      7,
      'needs_changes',
    );
    self::assertSame([
      'schema_version' => 1,
      'event' => OrganizationContributionOutcomeRecordedEvent::NAME,
      'source' => 'events_organization',
      'source_uuid' => 'f6be4a3e-6d93-40c8-a47a-379d170dc852',
      'source_revision_id' => 12,
      'recipient_uid' => 7,
      'outcome' => 'needs_changes',
    ], $event->toQueuePayload());
  }

  /**
   * Verifies outcomes are allowlisted.
   */
  public function testRejectsUnrecognizedOutcome(): void {
    $this->expectException(\InvalidArgumentException::class);
    new OrganizationContributionOutcomeRecordedEvent(
      'events_organization',
      'f6be4a3e-6d93-40c8-a47a-379d170dc852',
      12,
      7,
      'private_note',
    );
  }

  /**
   * Verifies unknown source adapters cannot enter the queue contract. */
  public function testRejectsUnrecognizedSource(): void {
    $this->expectException(\InvalidArgumentException::class);
    new OrganizationContributionOutcomeRecordedEvent(
      'events_private_payload',
      'f6be4a3e-6d93-40c8-a47a-379d170dc852',
      12,
      7,
      'accepted',
    );
  }

}
