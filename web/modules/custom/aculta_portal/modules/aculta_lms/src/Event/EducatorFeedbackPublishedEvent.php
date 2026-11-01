<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * An educator published feedback on a learner's answer. */
final readonly class EducatorFeedbackPublishedEvent {

  public const NAME = self::class;

  public function __construct(
    public string $commentUuid,
    public string $publicationVersion,
  ) {
    if (trim($commentUuid) === '' || !preg_match('/^[a-f0-9]{32}$/', $publicationVersion)) {
      throw new \InvalidArgumentException('Educator feedback events require a comment UUID and opaque version.');
    }
  }

}
