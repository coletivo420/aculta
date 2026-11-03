<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;
use Drupal\aculta_lms\Event\AnswerSubmittedEvent;
use Drupal\aculta_lms\Hook\AnswerLifecycleEventHooks;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;

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
    $original = $this->answer(CourseAnswer::STATE_SUBMITTED);
    $hooks->entityUpdate($this->answer(CourseAnswer::STATE_GRADED, original: $original));

    $hooks->entityUpdate($this->answer(CourseAnswer::STATE_GRADED, original: $this->answer(CourseAnswer::STATE_GRADED)));
    $hooks->entityUpdate($this->answer(CourseAnswer::STATE_GRADED, original: $this->answer(CourseAnswer::STATE_GRADED)));
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

    (new AnswerLifecycleEventHooks($dispatcher))->entityInsert($this->answer(CourseAnswer::STATE_GRADED));
  }

  /**
   * Publishes a pending response without including its content.
   */
  public function testPublishesSubmittedAnswerWithoutResponsePayload(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(static fn(object $event): bool =>
        $event instanceof AnswerSubmittedEvent
        && $event->answerUuid === 'answer-uuid'
        && preg_match('/^[a-f0-9]{32}$/', $event->publicationVersion) === 1
      ));

    (new AnswerLifecycleEventHooks($dispatcher))->entityInsert($this->answer(CourseAnswer::STATE_SUBMITTED));
  }

  /**
   * Publishes when a pending response is replaced by a new persisted response.
   */
  public function testPublishesChangedPendingSubmission(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(AnswerSubmittedEvent::class));
    $original = $this->answer(CourseAnswer::STATE_GRADED);
    $updated = $this->answer(CourseAnswer::STATE_SUBMITTED, original: $original);

    (new AnswerLifecycleEventHooks($dispatcher))->entityUpdate($updated);
  }

  /**
   * Creates an answer mock with minimal identity and state.
   */
  private function answer(string $state, int $uid = 27, ?ContentEntityBase $original = NULL): ContentEntityBase {
    $answer = $this->createMock(ContentEntityBase::class);
    $answer->method('getEntityTypeId')->willReturn('aculta_lms_answer');
    $answer->method('uuid')->willReturn('answer-uuid');
    $answer->method('get')->willReturnCallback(static function (string $name) use ($state, $uid): object {
      return (object) ['value' => $name === 'state' ? $state : $uid];
    });
    $answer->method('getOriginal')->willReturn($original);
    return $answer;
  }

}
