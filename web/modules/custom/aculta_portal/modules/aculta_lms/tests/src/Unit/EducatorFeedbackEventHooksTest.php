<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Event\EducatorFeedbackPublishedEvent;
use Drupal\aculta_lms\Hook\EducatorFeedbackEventHooks;
use Drupal\comment\CommentInterface;
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
    $course->method('hasPermission')->with('grade students', $educator)->willReturn(TRUE);
    $courseStatus = $this->createMock(CourseStatusInterface::class);
    $courseStatus->method('getCourse')->willReturn($course);
    $lessonStatus = $this->createMock(LessonStatusInterface::class);
    $lessonStatus->method('getCourseStatus')->willReturn($courseStatus);
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('getUserId')->willReturn('21');
    $answer->method('getLessonStatus')->willReturn($lessonStatus);
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

}
