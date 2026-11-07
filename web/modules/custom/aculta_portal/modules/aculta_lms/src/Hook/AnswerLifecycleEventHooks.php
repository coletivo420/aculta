<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Psr\EventDispatcher\EventDispatcherInterface;
use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;
use Drupal\aculta_lms\Event\AnswerSubmittedEvent;
use Drupal\aculta_lms\Learning\CourseAnswer;

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
    if ($entity->getEntityTypeId() !== 'aculta_lms_answer') {
      return;
    }
    if ($this->state($entity) === CourseAnswer::STATE_GRADED) {
      $this->publishEvaluation($entity);
    }
    elseif ($this->state($entity) === CourseAnswer::STATE_SUBMITTED) {
      $this->publishSubmission($entity);
    }
  }

  /**
   * Publishes evaluation transitions and changed scores on an evaluated answer.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() !== 'aculta_lms_answer') {
      return;
    }
    $original = $entity->getOriginal();
    if ($original === NULL || $original->getEntityTypeId() !== 'aculta_lms_answer') {
      return;
    }
    if ($this->state($entity) === CourseAnswer::STATE_SUBMITTED
      && $this->state($original) !== CourseAnswer::STATE_SUBMITTED) {
      $this->publishSubmission($entity);
      return;
    }
    if ($this->state($entity) === CourseAnswer::STATE_GRADED) {
      $this->publishEvaluation($entity);
    }
  }

  /**
   * Dispatches the learner-only evaluation event.
   */
  private function publishEvaluation(EntityInterface $answer): void {
    $this->eventDispatcher->dispatch(new AnswerEvaluatedEvent(
      (string) $answer->uuid(),
      (string) $answer->get('uid')->value,
      $this->newPublicationVersion(),
    ));
  }

  /**
   * Dispatches a response without including its content.
   */
  private function publishSubmission(EntityInterface $answer): void {
    $this->eventDispatcher->dispatch(new AnswerSubmittedEvent(
      (string) $answer->uuid(),
      $this->newPublicationVersion(),
    ));
  }

  /**
   * Retorna o estado atual da resposta.
   *
   * @param \Drupal\Core\Entity\EntityInterface $answer
   *   Entidade de resposta.
   *
   * @return string
   *   Estado persistido da resposta.
   */
  private function state(EntityInterface $answer): string {
    return (string) $answer->get('state')->value;
  }

  /**
   * Creates an opaque identity for this persisted publication event.
   */
  private function newPublicationVersion(): string {
    return bin2hex(random_bytes(16));
  }

}
