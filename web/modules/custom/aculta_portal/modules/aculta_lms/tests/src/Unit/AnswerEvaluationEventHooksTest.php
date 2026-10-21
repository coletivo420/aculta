<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;
use Drupal\aculta_lms\Hook\AnswerEvaluationEventHooks;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @coversDefaultClass \Drupal\aculta_lms\Hook\AnswerEvaluationEventHooks
 */
#[CoversClass(AnswerEvaluationEventHooks::class)]
#[Group('aculta_lms')]
final class AnswerEvaluationEventHooksTest extends UnitTestCase {

  /**
   * Publishes one event when evaluation changes to true.
   */
  public function testPublishesOnlyNewEvaluationTransitions(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $versions = [];
    $dispatcher->expects($this->exactly(3))
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$versions): object {
        self::assertInstanceOf(AnswerEvaluatedEvent::class, $event);
        self::assertSame('answer-uuid', $event->answerUuid);
        self::assertSame('27', $event->accountId);
        $versions[] = $event->publicationVersion;
        return $event;
      });
    $hooks = new AnswerEvaluationEventHooks($dispatcher);
    $original = $this->answer(FALSE);
    $hooks->entityUpdate($this->answer(TRUE, 1, 100, $original));

    $hooks->entityUpdate($this->answer(TRUE, 1, 200, $this->answer(TRUE, 1, 100)));
    $hooks->entityUpdate($this->answer(TRUE, 2, 300, $this->answer(TRUE, 1, 200)));
    $hooks->entityUpdate($this->answer(TRUE, 2, 300, $this->answer(TRUE, 2, 300)));
    $hooks->entityInsert($this->answer(FALSE));

    self::assertCount(3, $versions);
    self::assertNotSame($versions[0], $versions[1]);
    self::assertNotSame($versions[1], $versions[2]);
  }

  /**
   * Publishes an evaluated answer created with its score.
   */
  public function testPublishesEvaluatedAnswerOnInsert(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(AnswerEvaluatedEvent::class));

    (new AnswerEvaluationEventHooks($dispatcher))->entityInsert($this->answer(TRUE));
  }

  /**
   * Creates an answer mock with minimal identity and state.
   */
  private function answer(bool $evaluated, int $score = 0, int $changed = 100, ?AnswerInterface $original = NULL): AnswerInterface {
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('isEvaluated')->willReturn($evaluated);
    $answer->method('uuid')->willReturn('answer-uuid');
    $answer->method('getUserId')->willReturn('27');
    $answer->method('getScore')->willReturn($score);
    $answer->method('getChangedTime')->willReturn($changed);
    $answer->method('getOriginal')->willReturn($original);
    return $answer;
  }

}
