<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_events\Hook\ActivityRetirementHooks;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the access hook for the retired activity bundle.
 */
#[Group('aculta_events')]
final class ActivityRetirementHooksTest extends TestCase {

  /**
   * Non-bundle create checks pass a NULL bundle and stay neutral.
   */
  public function testNullBundleIsNeutral(): void {
    $result = (new ActivityRetirementHooks())->entityCreateAccess(
      $this->createMock(AccountInterface::class),
      ['entity_type_id' => 'user'],
      NULL,
    );

    self::assertInstanceOf(AccessResult::class, $result);
    self::assertTrue($result->isNeutral());
  }

  /**
   * Creating a node in the retired activity bundle is forbidden.
   */
  public function testActivityBundleIsForbidden(): void {
    $result = (new ActivityRetirementHooks())->entityCreateAccess(
      $this->createMock(AccountInterface::class),
      ['entity_type_id' => 'node'],
      'activity',
    );

    self::assertInstanceOf(AccessResult::class, $result);
    self::assertTrue($result->isForbidden());
  }

}
