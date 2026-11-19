<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Service;

/**
 * Defines the closed proposal decision graph. */
final class ContributionStateMachine {

  /**
   * States that may receive a reviewer decision. */
  private const REVIEWABLE = ['submitted', 'needs_changes'];

  /**
   * Reviewer outcomes that can be persisted by a human decision. */
  private const OUTCOMES = ['accepted', 'needs_changes', 'declined'];

  /**
   * Whether a reviewer may move the proposal between the given states. */
  public function canDecide(string $current, string $outcome): bool {
    return in_array($current, self::REVIEWABLE, TRUE)
      && in_array($outcome, self::OUTCOMES, TRUE);
  }

  /**
   * Whether the proposal author may withdraw in the current state. */
  public function canWithdraw(string $current): bool {
    return in_array($current, self::REVIEWABLE, TRUE);
  }

}
