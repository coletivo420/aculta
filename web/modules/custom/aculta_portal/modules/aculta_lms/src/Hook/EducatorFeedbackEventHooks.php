<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_lms\Event\EducatorFeedbackPublishedEvent;
use Drupal\lms\Entity\AnswerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes minimal events when educators publish answer feedback. */
final class EducatorFeedbackEventHooks {

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
 * Publishes feedback created already visible to the student. */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof CommentInterface && $entity->bundle() === 'lms_answer_comment' && $entity->isPublished()) {
      $this->publish($entity);
    }
  }

  /**
 * Publishes feedback when it becomes visible or its text changes. */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof CommentInterface || $entity->bundle() !== 'lms_answer_comment' || !$entity->isPublished()) {
      return;
    }
    $original = $entity->getOriginal();
    if (!$original instanceof CommentInterface || !$original->isPublished()
      || $original->get('comment_body')->getValue() !== $entity->get('comment_body')->getValue()) {
      $this->publish($entity);
    }
  }

  /**
   * Dispatches an event only for educator-authored feedback on an answer. */
  private function publish(CommentInterface $comment): void {
    $answer = $comment->getCommentedEntity();
    $educator = $comment->getOwner();
    if (!$answer instanceof AnswerInterface || !$educator || $answer->getUserId() === $educator->id()) {
      return;
    }
    $course = $answer->getLessonStatus()->getCourseStatus()->getCourse();
    if (!$course || !$course->hasPermission('grade students', $educator)) {
      return;
    }
    $this->eventDispatcher->dispatch(new EducatorFeedbackPublishedEvent(
      (string) $comment->uuid(),
      bin2hex(random_bytes(16)),
    ));
  }

}
