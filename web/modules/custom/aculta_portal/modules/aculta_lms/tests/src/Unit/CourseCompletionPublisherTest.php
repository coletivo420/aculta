<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Event\CourseCompletedEvent;
use Drupal\aculta_lms\Event\CourseCompletionPublisher;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Testa a publicação do evento de curso concluído.
 */
#[CoversClass(CourseCompletionPublisher::class)]
#[Group('aculta_lms')]
final class CourseCompletionPublisherTest extends UnitTestCase {

  /**
   * Publica evento apenas para progresso aprovado e finalizado.
   */
  public function testPublishesOnlyPassedAndFinishedProgress(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(static fn(object $event): bool =>
        $event instanceof CourseCompletedEvent
        && $event->sourceUuid === 'progress-uuid'
        && $event->courseId === '42'
        && $event->accountId === '7'
        && $event->completedAt == new \DateTimeImmutable('@1760000000')
      ));

    $event = (new CourseCompletionPublisher($dispatcher))->publish($this->progressEntity(
      CourseProgress::STATUS_PASSED,
      TRUE,
    ));

    self::assertInstanceOf(CourseCompletedEvent::class, $event);
  }

  /**
   * Não publica evento para progresso reprovado, não finalizado ou novo.
   */
  public function testDoesNotPublishUnpassedUnfinishedOrNewProgress(): void {
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::never())->method('dispatch');
    $publisher = new CourseCompletionPublisher($dispatcher);

    self::assertNull($publisher->publish($this->progressEntity(CourseProgress::STATUS_FAILED, TRUE)));
    self::assertNull($publisher->publish($this->progressEntity(CourseProgress::STATUS_PROGRESS, FALSE)));
    self::assertNull($publisher->publish($this->progressEntity(CourseProgress::STATUS_PASSED, TRUE, TRUE)));
  }

  /**
   * Cria uma entidade de progresso simulada com os valores informados.
   *
   * @param string $status
   *   Status do progresso.
   * @param bool $finished
   *   Indica se o progresso está finalizado.
   * @param bool $new
   *   Indica se a entidade ainda não foi salva.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Entidade simulada.
   */
  private function progressEntity(string $status, bool $finished, bool $new = FALSE): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('uuid')->willReturn('progress-uuid');
    $entity->method('isNew')->willReturn($new);
    $entity->method('get')->willReturnCallback(function (string $field) use ($status, $finished): FieldItemListInterface {
      $value = match ($field) {
        'status' => $status,
        'finished' => $finished,
        'changed' => 1760000000,
        'course_id' => 42,
        'uid' => 7,
      };
      $field_list = $this->createMock(FieldItemListInterface::class);
      $field_list->method('getValue')->willReturn([['value' => $value]]);
      return $field_list;
    });
    return $entity;
  }

}
