<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\EducatorFeedbackPolicy;
use Drupal\aculta_lms\Event\EducatorFeedbackPublishedEvent;
use Drupal\aculta_lms\Hook\EducatorFeedbackEventHooks;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use Psr\EventDispatcher\EventDispatcherInterface;

/** Tests events for educator feedback published on ACULTA answers. */
#[CoversClass(EducatorFeedbackEventHooks::class)]
#[TestGroup('aculta_lms')]
final class EducatorFeedbackEventHooksTest extends UnitTestCase {

  public function testPublishedEducatorFeedbackPublishesEvent(): void {
    $educator = $this->createMock(UserInterface::class);
    $educator->method('id')->willReturn('22');
    $course = $this->course(TRUE, $educator);
    $answer = $this->answer(21);
    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->method('courseForAnswer')->with($answer)->willReturn($course);
    $comment = $this->comment($answer, $educator);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('dispatch')->with($this->callback(static fn(object $event): bool =>
      $event instanceof EducatorFeedbackPublishedEvent
      && $event->commentUuid === 'feedback-comment-uuid'
      && preg_match('/^[a-f0-9]{32}$/', $event->publicationVersion) === 1
    ));

    (new EducatorFeedbackEventHooks($dispatcher, new EducatorFeedbackPolicy($eligibility)))->entityInsert($comment);
  }

  public function testPublishedFeedbackWithMissingAnswerDoesNotDispatch(): void {
    $educator = $this->createMock(UserInterface::class);
    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->expects(self::never())->method('courseForAnswer');
    $comment = $this->comment(NULL, $educator);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::never())->method('dispatch');

    (new EducatorFeedbackEventHooks($dispatcher, new EducatorFeedbackPolicy($eligibility)))->entityInsert($comment);
  }

  public function testLearnerAuthoredCommentDoesNotPublishFeedbackEvent(): void {
    $learner = $this->createMock(UserInterface::class);
    $learner->method('id')->willReturn('21');
    $answer = $this->answer(21);
    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->expects(self::never())->method('courseForAnswer');
    $comment = $this->comment($answer, $learner);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::never())->method('dispatch');

    (new EducatorFeedbackEventHooks($dispatcher, new EducatorFeedbackPolicy($eligibility)))->entityInsert($comment);
  }

  public function testCommentWithoutGradePermissionDoesNotPublishFeedbackEvent(): void {
    $member = $this->createMock(UserInterface::class);
    $member->method('id')->willReturn('22');
    $course = $this->course(FALSE, $member);
    $answer = $this->answer(21);
    $eligibility = $this->createMock(LearningEligibilityInterface::class);
    $eligibility->method('courseForAnswer')->with($answer)->willReturn($course);
    $comment = $this->comment($answer, $member);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::never())->method('dispatch');

    (new EducatorFeedbackEventHooks($dispatcher, new EducatorFeedbackPolicy($eligibility)))->entityInsert($comment);
  }

  private function course(bool $canGrade, UserInterface $user): GroupInterface {
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->with('grade students', $user)->willReturn($canGrade);
    return $course;
  }

  private function answer(int $uid): ContentEntityBase {
    $answer = $this->createMock(ContentEntityBase::class);
    $answer->method('getEntityTypeId')->willReturn('aculta_lms_answer');
    $answer->method('get')->with('uid')->willReturn((object) ['value' => $uid]);
    return $answer;
  }

  private function comment(?ContentEntityBase $answer, UserInterface $author): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('lms_answer_comment');
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('getCommentedEntity')->willReturn($answer);
    $comment->method('getOwner')->willReturn($author);
    $comment->method('uuid')->willReturn('feedback-comment-uuid');
    return $comment;
  }

}
