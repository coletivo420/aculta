<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

use Drupal\lms\Entity\CourseStatusInterface;

/** Publishes a confirmed course pass to integration subscribers. */
interface CourseCompletionPublisherInterface {

  /**
   * Publishes a passed status after its saved transition is confirmed.
   *
   * The caller is responsible for invoking this only after the LMS status has
   * been saved and changed from a non-passed state. This service does not save
   * entities or retain a delivery/deduplication record.
   *
   * @return \Drupal\aculta_lms\Event\CourseCompletedEvent|null
   *   The event dispatched, or NULL when the current LMS status is not passed.
   */
  public function publish(CourseStatusInterface $status): ?CourseCompletedEvent;

}
