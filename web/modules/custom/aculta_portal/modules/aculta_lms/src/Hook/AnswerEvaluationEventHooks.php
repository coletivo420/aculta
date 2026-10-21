<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\lms\Entity\AnswerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;

/**
 * Publishes when a learner's answer becomes evaluated. */
final class AnswerEvaluationEventHooks {

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Publishes an evaluated answer created in evaluated state.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof AnswerInterface && $entity->isEvaluated()) {
      $this->publish($entity);
    }
  }

  /**
   * Publishes evaluation transitions and changed scores on an evaluated answer.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof AnswerInterface || !$entity->isEvaluated()) {
      return;
    }
    $original = $entity->getOriginal();
    if (!$original instanceof AnswerInterface) {
      return;
    }
    if ($original->isEvaluated()
      && $original->getScore() === $entity->getScore()
      && $original->getChangedTime() === $entity->getChangedTime()) {
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
      substr(hash('sha256', $answer->uuid() . ':' . $answer->getChangedTime() . ':' . $answer->getScore()), 0, 32),
    ));
  }

}
