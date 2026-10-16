<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use DateTimeImmutable;
use Drupal\aculta_lms\Event\CourseCompletedEvent;
use Drupal\aculta_lms\Event\CourseCompletionPublisher;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;

/** @coversDefaultClass \Drupal\aculta_lms\Event\CourseCompletionPublisher */
#[CoversClass(CourseCompletionPublisher::class)]
#[Group('aculta_lms')]
final class CourseCompletionPublisherTest extends UnitTestCase {

  public function testPublishesOnlyPassedAndFinishedStatuses(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(static fn(object $event): bool =>
        $event instanceof CourseCompletedEvent
        && $event->sourceStatusUuid === 'status-uuid'
        && $event->courseId === '42'
        && $event->accountId === '7'
        && $event->completedAt == new DateTimeImmutable('@1760000000')
      ));

    $publisher = new CourseCompletionPublisher($dispatcher);
    $event = $publisher->publish($this->courseStatus(
      CourseStatusInterface::STATUS_PASSED,
      TRUE,
    ));

    $this->assertInstanceOf(CourseCompletedEvent::class, $event);
  }

  public function testDoesNotPublishUnpassedOrUnfinishedStatuses(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->never())->method('dispatch');
    $publisher = new CourseCompletionPublisher($dispatcher);

    $this->assertNull($publisher->publish($this->courseStatus(CourseStatusInterface::STATUS_FAILED, TRUE)));
    $this->assertNull($publisher->publish($this->courseStatus(CourseStatusInterface::STATUS_PROGRESS, FALSE)));
    $this->assertNull($publisher->publish($this->courseStatus(CourseStatusInterface::STATUS_PASSED, TRUE, TRUE)));
  }

  private function courseStatus(string $status, bool $finished, bool $new = FALSE): CourseStatusInterface {
    $entity = $this->createMock(CourseStatusInterface::class);
    $entity->method('uuid')->willReturn('status-uuid');
    $entity->method('getCourseId')->willReturn('42');
    $entity->method('getUserId')->willReturn('7');
    $entity->method('getFinished')->willReturn($finished ? 1760000000 : 0);
    $entity->method('isFinished')->willReturn($finished);
    $entity->method('isNew')->willReturn($new);
    $entity->method('getStatus')->willReturn($status);
    return $entity;
  }

}
