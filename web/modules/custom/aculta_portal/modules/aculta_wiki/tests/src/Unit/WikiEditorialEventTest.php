<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\Event\WikiProposalOutcomeRecordedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the stable payloads for Wiki editorial event consumers. */
#[CoversClass(WikiEntryReviewRequestedEvent::class)]
#[CoversClass(WikiProposalOutcomeRecordedEvent::class)]
#[Group('aculta_wiki')]
final class WikiEditorialEventTest extends TestCase {

  private const ENTRY_UUID = 'b4a5e745-63b0-4f5d-94a1-57b25283db0e';

  private const PROPOSAL_UUID = 'e9d0ae94-9c81-4d09-9ab0-c3d2c9a579f5';

  /**
   * The review envelope contains only identity and source revision.
   */
  public function testReviewEventPayloadIsMinimalAndVersioned(): void {
    $event = new WikiEntryReviewRequestedEvent(self::ENTRY_UUID, 41);

    self::assertSame(WikiEntryReviewRequestedEvent::class, $event::NAME);
    self::assertSame([
      'schema_version' => 1,
      'event' => WikiEntryReviewRequestedEvent::class,
      'source_uuid' => self::ENTRY_UUID,
      'source_revision_id' => 41,
    ], $event->toQueuePayload());
  }

  /**
   * Only the proposal author and allowlisted outcome accompany its revision.
   */
  public function testProposalOutcomePayloadIsMinimalAndVersioned(): void {
    $event = new WikiProposalOutcomeRecordedEvent(self::PROPOSAL_UUID, 83, 7, 'accepted');

    self::assertSame([
      'schema_version' => 1,
      'event' => WikiProposalOutcomeRecordedEvent::class,
      'source_uuid' => self::PROPOSAL_UUID,
      'source_revision_id' => 83,
      'recipient_uid' => 7,
      'outcome' => 'accepted',
    ], $event->toQueuePayload());
  }

  /**
   * Invalid source identities and revisions cannot enter a queue envelope.
   */
  public function testInvalidReviewEventIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    new WikiEntryReviewRequestedEvent('not-a-uuid', 0);
  }

  /**
   * Invalid recipients and free-form outcomes cannot enter a queue envelope.
   */
  public function testInvalidProposalOutcomeEventIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    new WikiProposalOutcomeRecordedEvent(self::PROPOSAL_UUID, 83, 0, 'private-note');
  }

}
