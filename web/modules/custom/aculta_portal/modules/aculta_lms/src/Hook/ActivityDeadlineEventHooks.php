<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_lms\Event\ActivityDeadlineAssignedEvent;
use Drupal\group\Entity\GroupRelationshipInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes events after a class activity deadline is persisted. */
final class ActivityDeadlineEventHooks {

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
 * Publishes a newly assigned deadline. */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($this->isDeadline($entity) && !$entity->get('field_activity_due_at')->isEmpty()) {
      $this->publish($entity);
    }
  }

  /**
 * Publishes a deadline only when its persisted date changes. */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$this->isDeadline($entity) || $entity->get('field_activity_due_at')->isEmpty()) {
      return;
    }
    $original = $entity->getOriginal();
    if (!$original instanceof GroupRelationshipInterface
      || $original->get('field_activity_due_at')->value !== $entity->get('field_activity_due_at')->value) {
      $this->publish($entity);
    }
  }

  /**
   * Checks for the class-to-activity deadline relation. */
  private function isDeadline(EntityInterface $entity): bool {
    return $entity instanceof GroupRelationshipInterface
      && $entity->getPluginId() === 'lms_class_activity_due';
  }

  /**
   * Dispatches only the deadline relation UUID and a dedupe version. */
  private function publish(GroupRelationshipInterface $relationship): void {
    $version = substr(hash(
      'sha256',
      (string) $relationship->uuid() . '|' . (string) $relationship->get('field_activity_due_at')->value,
    ), 0, 32);
    $this->eventDispatcher->dispatch(new ActivityDeadlineAssignedEvent(
      (string) $relationship->uuid(),
      $version,
    ));
  }

}
