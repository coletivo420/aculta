<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

use DateTimeImmutable;
use Drupal\aculta_learning_contracts\Event\CourseCompletedEvent;
use Drupal\aculta_lms\Learning\CourseProgress;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/** Dispatches a confirmed ACULTA course pass to integration subscribers. */
final class CourseCompletionPublisher implements CourseCompletionPublisherInterface {

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /** {@inheritdoc} */
  public function publish(ContentEntityInterface $progress): ?CourseCompletedEvent {
    $finishedAt = (int) (self::fieldValue($progress, 'changed') ?? 0);
    if (
      ($progress instanceof SynchronizableInterface && $progress->isSyncing())
      || $progress->isNew()
      || !(bool) self::fieldValue($progress, 'finished')
      || $finishedAt < 1
      || (string) self::fieldValue($progress, 'status') !== CourseProgress::STATUS_PASSED
    ) {
      return NULL;
    }

    $event = new CourseCompletedEvent(
      (string) $progress->uuid(),
      (string) self::fieldValue($progress, 'course_id'),
      (string) self::fieldValue($progress, 'uid'),
      new DateTimeImmutable('@' . $finishedAt),
    );
    $this->eventDispatcher->dispatch($event);

    return $event;
  }

  private static function fieldValue(ContentEntityInterface $entity, string $field): mixed {
    $values = $entity->get($field)->getValue();
    return $values[0]['value'] ?? NULL;
  }

}
