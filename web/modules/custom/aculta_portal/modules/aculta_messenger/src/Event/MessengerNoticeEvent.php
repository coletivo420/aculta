<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Immutable, minimal envelope for a personal notice event.
 */
final class MessengerNoticeEvent extends Event {

  public const EVENT_NAME = 'aculta_messenger.notice_event';

  /**
   * Constructs a validated notice event.
   *
   * @param string $eventType
   *   Stable, namespaced event identifier.
   * @param string $sourceModule
   *   Machine name of the producer module.
   * @param string $sourceEntityType
   *   Entity type of the source record.
   * @param string $sourceEntityUuid
   *   Opaque UUID of the source record.
   * @param string|null $sourceRevisionOrVersion
   *   Opaque source revision or event version.
   * @param int $recipientUid
   *   Intended recipient account ID.
   * @param string $purpose
   *   Domain purpose that owns the destination.
   * @param \DateTimeImmutable $occurredAt
   *   Time the source event occurred.
   * @param string $idempotencyKey
   *   Stable key consumers use to deduplicate delivery.
   * @param array<string, scalar|null> $metadata
   *   Flat, non-sensitive metadata; it must not contain message content,
   *   credentials or personal contact data.
   */
  public function __construct(
    private readonly string $eventType,
    private readonly string $sourceModule,
    private readonly string $sourceEntityType,
    private readonly string $sourceEntityUuid,
    private readonly ?string $sourceRevisionOrVersion,
    private readonly int $recipientUid,
    private readonly string $purpose,
    private readonly \DateTimeImmutable $occurredAt,
    private readonly string $idempotencyKey,
    private readonly array $metadata = [],
  ) {
    self::assertIdentifier($eventType, 'event type');
    self::assertIdentifier($sourceModule, 'source module');
    self::assertIdentifier($sourceEntityType, 'source entity type');
    self::assertIdentifier($purpose, 'purpose');
    if (self::containsControlCharacters($sourceEntityUuid)
      || trim($sourceEntityUuid) === ''
      || strlen($sourceEntityUuid) > 128
      || $recipientUid < 1
      || self::containsControlCharacters($idempotencyKey)
      || trim($idempotencyKey) === ''
      || strlen($idempotencyKey) > 255) {
      throw new \InvalidArgumentException('A source UUID, positive recipient UID and idempotency key are required.');
    }
    if ($sourceRevisionOrVersion !== NULL
      && (strlen($sourceRevisionOrVersion) > 128 || self::containsControlCharacters($sourceRevisionOrVersion))) {
      throw new \InvalidArgumentException('Source revision/version must not exceed 128 characters.');
    }
    self::assertMetadata($metadata);
  }

  /**
   * Returns the stable event type identifier.
   */
  public function eventType(): string {
    return $this->eventType;
  }

  /**
   * Returns the producer module machine name.
   */
  public function sourceModule(): string {
    return $this->sourceModule;
  }

  /**
   * Returns the source entity type.
   */
  public function sourceEntityType(): string {
    return $this->sourceEntityType;
  }

  /**
   * Returns the opaque source entity UUID.
   */
  public function sourceEntityUuid(): string {
    return $this->sourceEntityUuid;
  }

  /**
   * Returns the opaque source revision or version.
   */
  public function sourceRevisionOrVersion(): ?string {
    return $this->sourceRevisionOrVersion;
  }

  /**
   * Returns the intended recipient account ID.
   */
  public function recipientUid(): int {
    return $this->recipientUid;
  }

  /**
   * Returns the destination purpose.
   */
  public function purpose(): string {
    return $this->purpose;
  }

  /**
   * Returns the source event timestamp.
   */
  public function occurredAt(): \DateTimeImmutable {
    return $this->occurredAt;
  }

  /**
   * Returns the stable consumer deduplication key.
   */
  public function idempotencyKey(): string {
    return $this->idempotencyKey;
  }

  /**
   * Returns the validated flat metadata.
   *
   * @return array<string, scalar|null>
   *   Event metadata.
   */
  public function metadata(): array {
    return $this->metadata;
  }

  /**
   * Validates a machine identifier used in the event envelope.
   */
  private static function assertIdentifier(string $value, string $name): void {
    if (!preg_match('/^[a-z][a-z0-9_.-]{0,127}$/', $value)) {
      throw new \InvalidArgumentException(sprintf('Invalid %s identifier.', $name));
    }
  }

  /**
   * Validates bounded metadata without disclosing sensitive data.
   *
   * @param array<mixed> $metadata
   *   Metadata values to validate.
   */
  private static function assertMetadata(array $metadata): void {
    if (count($metadata) > 20) {
      throw new \InvalidArgumentException('Notice metadata may contain at most 20 values.');
    }
    foreach ($metadata as $key => $value) {
      if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)
        || preg_match('/email|body|message|content|answer|grade|note|password|token|phone|address/i', $key)
        || (!is_scalar($value) && $value !== NULL)
        || (is_string($value) && (strlen($value) > 255 || self::containsControlCharacters($value)))) {
        throw new \InvalidArgumentException('Notice metadata must be flat, small and non-sensitive.');
      }
    }
  }

  /**
   * Rejects ASCII control characters in values that cross module boundaries.
   */
  private static function containsControlCharacters(string $value): bool {
    return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
  }

}
