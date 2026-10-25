<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Game\EventSubscriber\GameAchievementNoticeEventConsumer;
use Drupal\aculta_messenger\Integration\Game\GameAchievementNoticeManagerInterface;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the strict source and purpose gate for achievement events. */
#[CoversClass(GameAchievementNoticeEventConsumer::class)]
#[Group('aculta_messenger')]
final class GameAchievementNoticeEventConsumerTest extends UnitTestCase {

  /**
   * Ignores events outside the Game producer contract. */
  public function testIgnoresEventsFromOtherSources(): void {
    $manager = $this->createMock(GameAchievementNoticeManagerInterface::class);
    $manager->expects(self::never())->method('record');
    $consumer = new GameAchievementNoticeEventConsumer($manager);

    $consumer->consume(new MessengerNoticeEvent(
      'game.achievement_awarded', 'other_module', 'aculta_badge_award', 'award-uuid', '31', 27,
      'account', new \DateTimeImmutable('@1798000000'), 'different:source:key', ['notice_type' => 'achievement'],
    ));
  }

  /**
   * Records award events that match the declared producer contract. */
  public function testRecordsAwardFromTheDeclaredContract(): void {
    $manager = $this->createMock(GameAchievementNoticeManagerInterface::class);
    $manager->expects(self::once())->method('record')->with(self::callback(
      static fn(MessengerNoticeEvent $event): bool => $event->sourceModule() === 'aculta_game'
        && $event->eventType() === 'game.achievement_awarded'
        && $event->recipientUid() === 27,
    ))->willReturn($this->createMock(MessageInterface::class));
    $consumer = new GameAchievementNoticeEventConsumer($manager);

    $consumer->consume(new MessengerNoticeEvent(
      'game.achievement_awarded', 'aculta_game', 'aculta_badge_award', 'award-uuid', '31', 27,
      'account', new \DateTimeImmutable('@1798000000'), 'game:achievement_awarded:award-uuid:31:27',
      ['notice_type' => 'achievement'],
    ));
  }

}
