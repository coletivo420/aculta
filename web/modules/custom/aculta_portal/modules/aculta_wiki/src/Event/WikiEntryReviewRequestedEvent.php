<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Event;

use Drupal\Component\Uuid\Uuid;

/**
 * A persisted Wiki entry revision entered the editorial review state. */
final readonly class WikiEntryReviewRequestedEvent {

  public const NAME = self::class;

  public const SCHEMA_VERSION = 1;

  public function __construct(
    public string $entryUuid,
    public int $entryRevisionId,
  ) {
    if (!Uuid::isValid($entryUuid) || $entryRevisionId < 1) {
      throw new \InvalidArgumentException('A Wiki review event requires a valid entry UUID and persisted revision ID.');
    }
  }

  /**
   * Returns the minimal, versioned Queue API payload.
   *
   * @return array{schema_version: int, event: string, source_uuid: string, source_revision_id: int}
   *   Queue data without editorial content or personal data.
   */
  public function toQueuePayload(): array {
    return [
      'schema_version' => self::SCHEMA_VERSION,
      'event' => self::NAME,
      'source_uuid' => $this->entryUuid,
      'source_revision_id' => $this->entryRevisionId,
    ];
  }

}
