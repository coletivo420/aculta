<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * A persisted answer evaluation published to its learner. */
final readonly class AnswerEvaluatedEvent {

  public const NAME = self::class;

  public function __construct(
    public string $answerUuid,
    public string $accountId,
    public string $publicationVersion,
  ) {
    if (trim($answerUuid) === '' || !ctype_digit($accountId) || (int) $accountId < 1
      || !preg_match('/^[a-f0-9]{32}$/', $publicationVersion)) {
      throw new \InvalidArgumentException('An evaluation event requires a persisted answer and learner identifier.');
    }
  }

}
