<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

use DateTimeImmutable;
use Drupal\aculta_learning_contracts\Event\CourseCompletedEvent;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/** Dispatches a confirmed Drupal LMS pass to integration subscribers. */
final class CourseCompletionPublisher implements CourseCompletionPublisherInterface {

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /** {@inheritdoc} */
  public function publish(CourseStatusInterface $status): ?CourseCompletedEvent {
    $finishedAt = $status->getFinished();
    if (
      ($status instanceof SynchronizableInterface && $status->isSyncing())
      || $status->isNew()
      || !$status->isFinished()
      || $finishedAt < 1
      || $status->getStatus() !== CourseStatusInterface::STATUS_PASSED
    ) {
      return NULL;
    }

    $event = new CourseCompletedEvent(
      (string) $status->uuid(),
      $status->getCourseId(),
      $status->getUserId(),
      new DateTimeImmutable('@' . $finishedAt),
    );
    $this->eventDispatcher->dispatch($event);

    return $event;
  }

}
