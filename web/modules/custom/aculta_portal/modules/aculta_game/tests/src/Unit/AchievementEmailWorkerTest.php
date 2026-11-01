<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_game\Unit;

use Drupal\aculta_game\Plugin\QueueWorker\AchievementEmailWorker;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies award email retries reuse a stable Mailer idempotency correlation.
 */
#[CoversClass(AchievementEmailWorker::class)]
#[Group('aculta_game')]
final class AchievementEmailWorkerTest extends UnitTestCase {

  /**
   * Each award has one stable UUID v5, distinct from other award deliveries.
   */
  public function testCorrelationIsStableAndUniquePerAward(): void {
    $first = AchievementEmailWorker::correlationIdForAward(42);
    $retry = AchievementEmailWorker::correlationIdForAward(42);
    $other = AchievementEmailWorker::correlationIdForAward(43);

    self::assertSame($first, $retry);
    self::assertNotSame($first, $other);
    self::assertMatchesRegularExpression(
      '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
      $first,
    );
  }

  /**
   * Invalid awards cannot be assigned a delivery identity.
   */
  public function testCorrelationRejectsInvalidAwardId(): void {
    $this->expectException(\InvalidArgumentException::class);
    AchievementEmailWorker::correlationIdForAward(0);
  }

}
