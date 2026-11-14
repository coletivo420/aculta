<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

use Drupal\aculta_lms\Event\CourseCompletedEvent;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Publishes a confirmed course pass to integration subscribers. */
interface CourseCompletionPublisherInterface {

  /**
   * Publishes an ACULTA progress entity after its saved transition is confirmed.
   *
   * The caller is responsible for invoking this only after progress has been
   * saved and changed from a non-passed state. This service does not save
   * entities or retain a delivery/deduplication record.
   *
   * @return \Drupal\aculta_lms\Event\CourseCompletedEvent|null
   *   The event dispatched, or NULL when progress is not marked as passed.
   */
  public function publish(ContentEntityInterface $progress): ?CourseCompletedEvent;

}
