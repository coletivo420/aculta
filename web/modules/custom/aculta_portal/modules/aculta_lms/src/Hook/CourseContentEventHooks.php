<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\LessonInterface;
use Drupal\aculta_lms\Event\CourseContentReleasedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes learner notices for content release transitions and additions.
 */
final class CourseContentEventHooks {

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Publishes content that is created already available to learners.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($this->isSupportedContent($entity) && $entity instanceof EntityPublishedInterface && $entity->isPublished()) {
      $this->publish($entity);
    }
  }

  /**
   * Publishes content when it becomes available or is added to a course.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$this->isSupportedContent($entity) || !$entity instanceof EntityPublishedInterface || !$entity->isPublished()) {
      return;
    }
    $original = $entity->getOriginal();
    if (!$original instanceof EntityPublishedInterface || !$original->isPublished()) {
      $this->publish($entity);
      return;
    }

    $field = match (TRUE) {
      $entity instanceof GroupInterface && $entity->bundle() === 'lms_course' => 'lessons',
      $entity instanceof LessonInterface => LessonInterface::ACTIVITIES,
      default => NULL,
    };
    if ($field === NULL || !$entity->hasField($field) || !$original->hasField($field)) {
      return;
    }

    $previousIds = [];
    foreach ($original->get($field)->referencedEntities() as $previous) {
      $previousIds[(string) $previous->id()] = TRUE;
    }
    foreach ($entity->get($field)->referencedEntities() as $referenced) {
      if (isset($previousIds[(string) $referenced->id()]) || !$this->isSupportedChild($entity, $referenced)
        || !$referenced instanceof EntityPublishedInterface || !$referenced->isPublished()) {
        continue;
      }
      $this->publish($referenced);
    }
  }

  /**
   * Checks whether the entity is a supported LMS content source.
   */
  private function isSupportedContent(EntityInterface $entity): bool {
    return ($entity instanceof GroupInterface && $entity->bundle() === 'lms_course')
      || $entity instanceof LessonInterface
      || $entity instanceof ActivityInterface;
  }

  /**
   * Checks that a newly referenced lesson or activity belongs to its field.
   */
  private function isSupportedChild(EntityInterface $parent, EntityInterface $child): bool {
    return ($parent instanceof GroupInterface && $child instanceof LessonInterface)
      || ($parent instanceof LessonInterface && $child instanceof ActivityInterface);
  }

  /**
   * Dispatches a minimal event with no learner data or content body.
   */
  private function publish(EntityInterface $entity): void {
    $this->eventDispatcher->dispatch(new CourseContentReleasedEvent(
      $entity->getEntityTypeId(),
      (string) $entity->uuid(),
      bin2hex(random_bytes(16)),
    ));
  }

}
