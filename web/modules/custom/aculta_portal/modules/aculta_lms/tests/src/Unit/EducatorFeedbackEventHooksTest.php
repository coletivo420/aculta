<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Event\EducatorFeedbackPublishedEvent;
use Drupal\aculta_lms\Hook\EducatorFeedbackEventHooks;
use Drupal\comment\CommentInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Tests events for educator feedback published on LMS answers. */
#[CoversClass(EducatorFeedbackEventHooks::class)]
#[TestGroup('aculta_lms')]
final class EducatorFeedbackEventHooksTest extends UnitTestCase {

  /**
   * A published comment by a course grader produces a minimal event. */
  public function testPublishedEducatorFeedbackPublishesEvent(): void {
    $educator = $this->createMock(UserInterface::class);
    $educator->method('id')->willReturn('22');
    $course = $this->createMock(CourseInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->with('grade students', $educator)->willReturn(TRUE);
    $courseStatus = $this->createMock(CourseStatusInterface::class);
    $courseStatus->method('get')->with(CourseStatusInterface::COURSE_FIELD)->willReturn($this->referenceField($course));
    $lessonStatus = $this->createMock(LessonStatusInterface::class);
    $lessonStatus->method('get')->with('course_status')->willReturn($this->referenceField($courseStatus));
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('getUserId')->willReturn('21');
    $answer->method('get')->with('lesson_status')->willReturn($this->referenceField($lessonStatus));
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('lms_answer_comment');
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('getCommentedEntity')->willReturn($answer);
    $comment->method('getOwner')->willReturn($educator);
    $comment->method('uuid')->willReturn('feedback-comment-uuid');

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(static fn (object $event): bool =>
        $event instanceof EducatorFeedbackPublishedEvent
        && $event->commentUuid === 'feedback-comment-uuid'
        && preg_match('/^[a-f0-9]{32}$/', $event->publicationVersion) === 1
      ));

    (new EducatorFeedbackEventHooks($dispatcher))->entityInsert($comment);
  }

  /**
   * A stale answer reference suppresses feedback instead of throwing.
   */
  public function testPublishedFeedbackWithMissingLessonStatusDoesNotDispatch(): void {
    $educator = $this->createMock(UserInterface::class);
    $educator->method('id')->willReturn('22');
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('getUserId')->willReturn('21');
    $answer->method('get')->with('lesson_status')->willReturn($this->referenceField(NULL));
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('lms_answer_comment');
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('getCommentedEntity')->willReturn($answer);
    $comment->method('getOwner')->willReturn($educator);

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->never())->method('dispatch');

    (new EducatorFeedbackEventHooks($dispatcher))->entityInsert($comment);
  }

  /**
   * Student comments never create educator feedback notifications. */
  public function testLearnerAuthoredCommentDoesNotPublishFeedbackEvent(): void {
    $learner = $this->createMock(UserInterface::class);
    $learner->method('id')->willReturn('21');
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('getUserId')->willReturn('21');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('lms_answer_comment');
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('getCommentedEntity')->willReturn($answer);
    $comment->method('getOwner')->willReturn($learner);

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects($this->never())->method('dispatch');
    (new EducatorFeedbackEventHooks($dispatcher))->entityInsert($comment);
  }

  /**
   * Creates a field item list whose reference can be absent or available.
   */
  private function referenceField(?object $entity): FieldItemListInterface {
    return new class($entity) extends FieldItemList {

      public function __construct(private readonly ?object $referencedEntity) {}

      public function __get($property_name) {
        if ($property_name === 'entity') {
          return $this->referencedEntity;
        }
        return parent::__get($property_name);
      }

    };
  }

}
