<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_lms\Event\CourseCompletionPublisherInterface;
use Drupal\lms\Entity\CourseStatusInterface;

/**
 * Publishes only a transition into the persisted passed state.
 */
final class CourseStatusEventHooks {

  public function __construct(
    private readonly CourseCompletionPublisherInterface $publisher,
  ) {}

  /**
 * Publishes a newly created status when it is already passed and finished. */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof CourseStatusInterface) {
      $this->publisher->publish($entity);
    }
  }

  /**
 * Publishes only when an existing status changes into passed and finished. */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof CourseStatusInterface) {
      return;
    }
    $original = $entity->getOriginal();
    if (!$original instanceof CourseStatusInterface
      || ($original->isFinished() && $original->getStatus() === CourseStatusInterface::STATUS_PASSED)
      || !$entity->isFinished()
      || $entity->getStatus() !== CourseStatusInterface::STATUS_PASSED) {
      return;
    }
    $this->publisher->publish($entity);
  }

}
