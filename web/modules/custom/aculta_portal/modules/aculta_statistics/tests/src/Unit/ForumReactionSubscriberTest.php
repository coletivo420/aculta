<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\EventSubscriber\ForumReactionSubscriber;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Assinante de reações novas do Fórum.
 */
#[Group('aculta_statistics')]
final class ForumReactionSubscriberTest extends TestCase {

  /**
   * A reação é registrada como dimensão do tipo.
   */
  public function testReactionIsRecordedByType(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects(self::once())
      ->method('record')
      ->with('forum.reactions.added', 'heart', 1);
    $recorder = new StatisticsRecorder(new MetricCatalog(), $storage);

    $event = new class {

      public string $reaction = 'heart';

    };
    (new ForumReactionSubscriber($recorder))->onReactionAdded($event);
  }

  /**
   * Evento sem reação válida é ignorado.
   */
  public function testInvalidEventIsIgnored(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects(self::never())->method('record');
    $recorder = new StatisticsRecorder(new MetricCatalog(), $storage);

    $event = new class {

      public string $reaction = '';

    };
    (new ForumReactionSubscriber($recorder))->onReactionAdded($event);
  }

}
