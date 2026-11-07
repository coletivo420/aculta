<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

/**
 * ACULTA contract for free-text answer behavior and formative feedback. */
interface FreeTextAnswerPluginInterface {

  /**
   *
   */
  public function activityType(): string;

  /**
   * @return array<string, mixed> */
  public function decodeConfiguration(string $json): array;

  /**
   * Returns a formative score from 0 to 100; it is never a final grade. */
  public function formativeScore(string $answer, array $configuration): float;

  /**
   * Builds escaped formative feedback for the submitted answer. */
  public function formativeFeedback(string $answer, array $configuration): array;

  /**
   * Returns an error message when the answer is too short, otherwise NULL. */
  public function validate(string $answer, array $configuration): ?string;

}
