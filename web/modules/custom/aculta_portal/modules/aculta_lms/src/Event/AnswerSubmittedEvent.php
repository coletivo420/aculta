<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * A learner's persisted response that needs educator review.
 */
final readonly class AnswerSubmittedEvent {

  public const NAME = self::class;

  public function __construct(
    public string $answerUuid,
    public string $publicationVersion,
  ) {
    if (trim($answerUuid) === '' || !preg_match('/^[a-f0-9]{32}$/', $publicationVersion)) {
      throw new \InvalidArgumentException('A submission event requires an answer identifier and opaque version.');
    }
  }

}
