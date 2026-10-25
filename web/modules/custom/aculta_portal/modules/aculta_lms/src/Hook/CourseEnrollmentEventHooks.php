<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Event\CourseEnrollmentEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes a minimal event after a learner joins an LMS class.
 */
final class CourseEnrollmentEventHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
 * Publishes only a new, persisted class membership. */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if (!$entity instanceof GroupMembership || $entity->getPluginId() !== 'group_membership') {
      return;
    }
    $class = $entity->getGroup();
    $student = $entity->getEntity();
    if ($class === NULL || $class->bundle() !== 'lms_class' || $student === NULL || !$student->id()) {
      return;
    }

    $parents = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $class->id(),
      'plugin_id' => 'lms_classes',
    ]);
    foreach ($parents as $parent) {
      $course = $parent instanceof GroupRelationshipInterface ? $parent->getGroup() : NULL;
      if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course') {
        continue;
      }
      $this->eventDispatcher->dispatch(new CourseEnrollmentEvent(
        (string) $entity->uuid(),
        (string) $course->id(),
        (string) $class->id(),
        (string) $student->id(),
      ));
      return;
    }
  }

}
