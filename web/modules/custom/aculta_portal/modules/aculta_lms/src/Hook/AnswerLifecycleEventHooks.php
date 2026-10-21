<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\lms\Entity\AnswerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;
use Drupal\aculta_lms\Event\AnswerSubmittedEvent;

/**
 * Publishes learner answer submissions and evaluation publications.
 */
final class AnswerLifecycleEventHooks {

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Publishes an evaluated answer created in evaluated state.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if (!$entity instanceof AnswerInterface) {
      return;
    }
    if ($entity->isEvaluated()) {
      $this->publish($entity);
    }
    elseif ($entity->getData() !== []) {
      $this->publishSubmission($entity);
    }
  }

  /**
   * Publishes evaluation transitions and changed scores on an evaluated answer.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof AnswerInterface) {
      return;
    }
    $original = $entity->getOriginal();
    if (!$original instanceof AnswerInterface) {
      return;
    }
    if (!$entity->isEvaluated()) {
      if ($entity->getData() !== [] && $original->getData() !== $entity->getData()) {
        $this->publishSubmission($entity);
      }
      return;
    }
    $this->publish($entity);
  }

  /**
   * Dispatches the learner-only evaluation event.
   */
  private function publish(AnswerInterface $answer): void {
    $this->eventDispatcher->dispatch(new AnswerEvaluatedEvent(
      (string) $answer->uuid(),
      $answer->getUserId(),
      $this->newPublicationVersion(),
    ));
  }

  /**
   * Dispatches a response without including its content.
   */
  private function publishSubmission(AnswerInterface $answer): void {
    $this->eventDispatcher->dispatch(new AnswerSubmittedEvent(
      (string) $answer->uuid(),
      $this->newPublicationVersion(),
    ));
  }

  /**
   * Creates an opaque identity for this persisted publication event.
   */
  private function newPublicationVersion(): string {
    return bin2hex(random_bytes(16));
  }

}
