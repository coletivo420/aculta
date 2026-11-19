<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Unit;

use Drupal\aculta_cgu\Service\ContributionStateMachine;
use Drupal\Tests\UnitTestCase;

/**
 * Tests allowed and final proposal states. */
final class ContributionStateMachineTest extends UnitTestCase {

  /**
   * Reviewable states accept only the defined human outcomes. */
  public function testReviewTransitions(): void {
    $machine = new ContributionStateMachine();
    foreach (['submitted', 'needs_changes'] as $current) {
      foreach (['accepted', 'needs_changes', 'declined'] as $outcome) {
        self::assertTrue($machine->canDecide($current, $outcome));
      }
    }
    foreach (['accepted', 'declined', 'withdrawn', 'conflict', 'unknown'] as $current) {
      self::assertFalse($machine->canDecide($current, 'accepted'));
    }
    self::assertFalse($machine->canDecide('submitted', 'withdrawn'));
    self::assertTrue($machine->canWithdraw('submitted'));
    self::assertTrue($machine->canWithdraw('needs_changes'));
    self::assertFalse($machine->canWithdraw('accepted'));
    self::assertFalse($machine->canWithdraw('conflict'));
  }

}
