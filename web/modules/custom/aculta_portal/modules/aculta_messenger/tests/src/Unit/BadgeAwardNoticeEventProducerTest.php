<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Hook\BadgeAwardNoticeEventProducer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the minimal event envelope for Game award notices. */
#[CoversClass(BadgeAwardNoticeEventProducer::class)]
#[Group('aculta_messenger')]
final class BadgeAwardNoticeEventProducerTest extends UnitTestCase {

  /**
   * Builds the stable source identity and recipient key. */
  public function testAwardEventUsesStableSourceAndIdempotencyKey(): void {
    $event = BadgeAwardNoticeEventProducer::event('award-uuid', 31, 27, 1_798_000_000);

    self::assertSame('game.achievement_awarded', $event->eventType());
    self::assertSame('aculta_game', $event->sourceModule());
    self::assertSame('aculta_badge_award', $event->sourceEntityType());
    self::assertSame('award-uuid', $event->sourceEntityUuid());
    self::assertSame('31', $event->sourceRevisionOrVersion());
    self::assertSame(27, $event->recipientUid());
    self::assertSame('account', $event->purpose());
    self::assertSame(1_798_000_000, $event->occurredAt()->getTimestamp());
    self::assertSame('game:achievement_awarded:award-uuid:31:27', $event->idempotencyKey());
    self::assertSame(['notice_type' => 'achievement'], $event->metadata());
  }

}
