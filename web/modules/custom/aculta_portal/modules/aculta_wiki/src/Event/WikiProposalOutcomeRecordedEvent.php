<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Event;

use Drupal\Component\Uuid\Uuid;

/**
 * A persisted Wiki proposal revision records an editorial outcome. */
final readonly class WikiProposalOutcomeRecordedEvent {

  public const NAME = self::class;

  public const SCHEMA_VERSION = 1;

  /**
   * Outcomes that may be published to the proposal author.
   *
   * @var list<string>
   */
  public const OUTCOMES = ['needs_changes', 'declined', 'accepted'];

  public function __construct(
    public string $proposalUuid,
    public int $proposalRevisionId,
    public int $recipientUid,
    public string $outcome,
  ) {
    if (!Uuid::isValid($proposalUuid)
      || $proposalRevisionId < 1
      || $recipientUid < 1
      || !in_array($outcome, self::OUTCOMES, TRUE)) {
      throw new \InvalidArgumentException('A Wiki proposal outcome event requires persisted identifiers and an allowlisted outcome.');
    }
  }

  /**
   * Returns the minimal, versioned Queue API payload.
   *
   * @return array{schema_version: int, event: string, source_uuid: string, source_revision_id: int, recipient_uid: int, outcome: string}
   *   Queue data without proposal text, notes, or personal contact details.
   */
  public function toQueuePayload(): array {
    return [
      'schema_version' => self::SCHEMA_VERSION,
      'event' => self::NAME,
      'source_uuid' => $this->proposalUuid,
      'source_revision_id' => $this->proposalRevisionId,
      'recipient_uid' => $this->recipientUid,
      'outcome' => $this->outcome,
    ];
  }

}
