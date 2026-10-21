<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * A due date was created or changed for an activity in a class.
 */
final readonly class ActivityDeadlineAssignedEvent {

  public const NAME = self::class;

  /**
   * Builds the opaque version for the persisted state of a deadline.
   */
  public static function versionFor(string $uuid, string $dueAt, int $changedTime): string {
    return substr(hash('sha256', implode('|', [
      $uuid,
      $dueAt,
      (string) $changedTime,
    ])), 0, 32);
  }

  public function __construct(
    public string $deadlineUuid,
    public string $publicationVersion,
  ) {
    if (trim($deadlineUuid) === '' || !preg_match('/^[a-f0-9]{32}$/', $publicationVersion)) {
      throw new \InvalidArgumentException('An activity deadline event requires an entity UUID and opaque version.');
    }
  }

}
