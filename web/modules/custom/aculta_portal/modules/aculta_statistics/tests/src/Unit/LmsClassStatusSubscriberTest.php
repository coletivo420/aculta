<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\EventSubscriber\LmsClassStatusSubscriber;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Instantâneo de turma: substitui o dia, por curso/turma e situação, sem dado individual.
 */
#[CoversClass(LmsClassStatusSubscriber::class)]
#[Group('aculta_statistics')]
final class LmsClassStatusSubscriberTest extends TestCase {

  /**
   * As linhas viram dimensão `curso/turma|situação` e o dia é substituído (não somado).
   */
  public function testSnapshotReplacesTheDay(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('currentDay')->willReturn(20000);
    $storage->expects($this->once())->method('replaceDay')->with(
      'lms.students.by_status',
      20000,
      ['5/9|students' => 12, '5/9|passed' => 3, '5/9|failed' => 0],
    );

    $event = new class {
      public array $rows = [
        ['course' => 5, 'class' => 9, 'situation' => 'students', 'count' => 12],
        ['course' => 5, 'class' => 9, 'situation' => 'passed', 'count' => 3],
        ['course' => 5, 'class' => 9, 'situation' => 'failed', 'count' => 0],
      ];
    };
    (new LmsClassStatusSubscriber($storage))->onSnapshot($event);
  }

  /**
   * Nome do evento assinado é o publicado pelo aculta_lms.
   */
  public function testSubscribedEventName(): void {
    $this->assertSame(['aculta_lms.class_status_snapshot' => 'onSnapshot'], LmsClassStatusSubscriber::getSubscribedEvents());
  }

}
