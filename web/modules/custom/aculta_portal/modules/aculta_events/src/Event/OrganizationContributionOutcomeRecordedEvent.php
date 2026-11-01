<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Event;

use Drupal\Component\Uuid\Uuid;

/**
 * A persisted Events proposal revision records an editorial outcome.
 *
 * The historical class name remains stable for queued items; the contract is
 * shared by Organization, Location, and Event contribution adapters.
 */
final readonly class OrganizationContributionOutcomeRecordedEvent {

  public const NAME = self::class;

  public const SCHEMA_VERSION = 1;

  public const OUTCOMES = ['submitted', 'needs_changes', 'declined', 'accepted'];

  public function __construct(
    public string $source,
    public string $proposalUuid,
    public int $proposalRevisionId,
    public int $recipientUid,
    public string $outcome,
  ) {
    if (!in_array($source, ['events_organization', 'events_location', 'events_event'], TRUE)
      || !Uuid::isValid($proposalUuid) || $proposalRevisionId < 1 || $recipientUid < 1
      || !in_array($outcome, self::OUTCOMES, TRUE)) {
      throw new \InvalidArgumentException('An organization proposal outcome event requires persisted identifiers and an allowlisted outcome.');
    }
  }

  /**
   * Returns the minimal queue payload without proposal content or notes. */
  public function toQueuePayload(): array {
    return [
      'schema_version' => self::SCHEMA_VERSION,
      'event' => self::NAME,
      'source' => $this->source,
      'source_uuid' => $this->proposalUuid,
      'source_revision_id' => $this->proposalRevisionId,
      'recipient_uid' => $this->recipientUid,
      'outcome' => $this->outcome,
    ];
  }

}
