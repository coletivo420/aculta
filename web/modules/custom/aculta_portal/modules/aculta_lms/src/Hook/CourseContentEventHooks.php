<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\group\Entity\GroupInterface;
use Drupal\aculta_lms\Event\CourseContentReleasedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes learner notices for content release transitions and additions.
 */
final class CourseContentEventHooks {

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Publishes content that is created already available to learners.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($this->isContentAvailable($entity)) {
      $this->publish($entity);
    }
  }

  /**
   * Publishes content when it becomes available or is added to a course.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$this->isSupportedContent($entity) || !$this->isContentAvailable($entity)) {
      return;
    }
    $original = $entity->getOriginal();
    if ($original === NULL || !$this->isContentAvailable($original)) {
      $this->publish($entity);
    }
  }

  /**
   * Checks whether the entity is a supported LMS content source.
   */
  private function isSupportedContent(EntityInterface $entity): bool {
    return ($entity instanceof GroupInterface && $entity->bundle() === 'lms_course')
      || in_array($entity->getEntityTypeId(), ['aculta_lms_lesson', 'aculta_lms_activity'], TRUE);
  }

  /**
   * Checks availability through ACULTA's lesson publication state.
   */
  private function isContentAvailable(EntityInterface $entity): bool {
    if ($entity instanceof GroupInterface && $entity->bundle() === 'lms_course') {
      return $entity->isPublished();
    }
    if (!$entity instanceof ContentEntityInterface) {
      return FALSE;
    }
    if ($entity->getEntityTypeId() === 'aculta_lms_lesson') {
      return (bool) $entity->get('published')->value;
    }
    if ($entity->getEntityTypeId() === 'aculta_lms_activity') {
      $lesson = $this->entityTypeManager->getStorage('aculta_lms_lesson')->load((int) $entity->get('lesson_id')->value);
      return $lesson instanceof ContentEntityInterface && (bool) $lesson->get('published')->value;
    }
    return FALSE;
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
