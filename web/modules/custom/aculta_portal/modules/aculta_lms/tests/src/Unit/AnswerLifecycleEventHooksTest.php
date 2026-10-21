<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;
use Drupal\aculta_lms\Event\AnswerSubmittedEvent;
use Drupal\aculta_lms\Hook\AnswerLifecycleEventHooks;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @coversDefaultClass \Drupal\aculta_lms\Hook\AnswerLifecycleEventHooks
 */
#[CoversClass(AnswerLifecycleEventHooks::class)]
#[Group('aculta_lms')]
final class AnswerLifecycleEventHooksTest extends UnitTestCase {

  /**
   * Publishes each evaluation, including a repeat with the same score.
   */
  public function testPublishesEvaluationTransitionsAndRegrades(): void {
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
    $hooks = new AnswerLifecycleEventHooks($dispatcher);
    $original = $this->answer(FALSE);
    $hooks->entityUpdate($this->answer(TRUE, 1, 100, $original));

    $hooks->entityUpdate($this->answer(TRUE, 1, 200, $this->answer(TRUE, 1, 100)));
    $hooks->entityUpdate($this->answer(TRUE, 2, 300, $this->answer(TRUE, 1, 200)));
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

    (new AnswerLifecycleEventHooks($dispatcher))->entityInsert($this->answer(TRUE));
  }

  /**
   * Publishes a pending response without including its content.
   */
  public function testPublishesPendingSubmissionWithoutResponseData(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(static fn(object $event): bool =>
        $event instanceof AnswerSubmittedEvent
        && $event->answerUuid === 'answer-uuid'
        && preg_match('/^[a-f0-9]{32}$/', $event->publicationVersion) === 1
      ));

    (new AnswerLifecycleEventHooks($dispatcher))->entityInsert($this->answer(
      FALSE,
      data: ['answer' => 'private response'],
    ));
  }

  /**
   * Publishes when a pending response is replaced by a new persisted response.
   */
  public function testPublishesChangedPendingSubmission(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(AnswerSubmittedEvent::class));
    $original = $this->answer(TRUE, data: ['answer' => 'previously evaluated response']);
    $updated = $this->answer(FALSE, changed: 200, original: $original, data: ['answer' => 'new response']);

    (new AnswerLifecycleEventHooks($dispatcher))->entityUpdate($updated);
  }

  /**
   * Creates an answer mock with minimal identity and state.
   */
  private function answer(bool $evaluated, int $score = 0, int $changed = 100, ?AnswerInterface $original = NULL, array $data = []): AnswerInterface {
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('isEvaluated')->willReturn($evaluated);
    $answer->method('uuid')->willReturn('answer-uuid');
    $answer->method('getUserId')->willReturn('27');
    $answer->method('getScore')->willReturn($score);
    $answer->method('getChangedTime')->willReturn($changed);
    $answer->method('getData')->willReturn($data);
    $answer->method('getOriginal')->willReturn($original);
    return $answer;
  }

}
