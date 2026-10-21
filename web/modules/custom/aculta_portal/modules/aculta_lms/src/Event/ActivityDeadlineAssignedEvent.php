<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * A due date was created or changed for an activity in a class. */
final readonly class ActivityDeadlineAssignedEvent {

  public const NAME = self::class;

  public function __construct(
    public string $deadlineUuid,
    public string $publicationVersion,
  ) {
    if (trim($deadlineUuid) === '' || !preg_match('/^[a-f0-9]{32}$/', $publicationVersion)) {
      throw new \InvalidArgumentException('An activity deadline event requires an entity UUID and opaque version.');
    }
  }

}
