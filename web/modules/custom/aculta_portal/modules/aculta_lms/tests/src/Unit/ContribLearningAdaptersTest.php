<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\aculta_lms\Learning\ContribCourseActivityAdapter;
use Drupal\aculta_lms\Learning\ContribCourseAnswerAdapter;
use Drupal\aculta_lms\Learning\ContribCourseLessonAdapter;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\Tests\UnitTestCase;

final class ContribLearningAdaptersTest extends UnitTestCase {

  /** Tests mapping a contrib lesson. */
  public function testLessonMapping(): void {
    $lesson = $this->createMock('Drupal\\lms\\Entity\\LessonInterface');
    $statusItem = $this->createMock(FieldItemInterface::class);
    $statusItem->method('getValue')->willReturn(['value' => 1]);
    $status = $this->createMock(FieldItemListInterface::class);
    $status->method('first')->willReturn($statusItem);
    $lesson->method('get')->with('status')->willReturn($status);
    $lesson->method('id')->willReturn('10');
    $lesson->method('label')->willReturn('Introdução');

    $dto = ContribCourseLessonAdapter::fromContrib($lesson, 2, 3);

    self::assertSame(10, $dto->id);
    self::assertSame(2, $dto->courseId);
    self::assertSame('Introdução', $dto->title);
    self::assertTrue($dto->published);
    self::assertSame(3, $dto->weight);
  }

  /** Tests activity type normalization and grading mapping. */
  public function testActivityMapping(): void {
    $activity = $this->createMock('Drupal\\lms\\Entity\\ActivityInterface');
    $activity->method('id')->willReturn('5');
    $activity->method('bundle')->willReturn('Free Text Review Feedback');

    $dto = ContribCourseActivityAdapter::fromContrib($activity, 10, 100);

    self::assertSame(5, $dto->id);
    self::assertSame(10, $dto->lessonId);
    self::assertSame('free_text_review_feedback', $dto->type);
    self::assertTrue($dto->graded);
  }

  /** Tests that activities without a bundle type are rejected. */
  public function testActivityWithoutTypeIsRejected(): void {
    $activity = $this->createMock('Drupal\\lms\\Entity\\ActivityInterface');
    $activity->method('bundle')->willReturn('');

    $this->expectException(\UnexpectedValueException::class);
    ContribCourseActivityAdapter::fromContrib($activity, 10, 0);
  }

  /** Tests mapping an ungraded contrib answer with a null score. */
  public function testAnswerMappingWithNullScore(): void {
    $answer = $this->createMock('Drupal\\lms\\Entity\\AnswerInterface');
    $activity = $this->createMock('Drupal\\lms\\Entity\\ActivityInterface');
    $activity->method('id')->willReturn('5');
    $scoreField = $this->createMock(FieldItemListInterface::class);
    $scoreField->method('isEmpty')->willReturn(FALSE);
    $answer->method('get')->with('score')->willReturn($scoreField);
    $answer->method('id')->willReturn('7');
    $answer->method('getActivity')->willReturn($activity);
    $answer->method('getUserId')->willReturn('42');
    $answer->method('isEvaluated')->willReturn(FALSE);
    $answer->method('getScore')->willReturn(0);

    $adapter = new ContribCourseAnswerAdapter(
      $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class),
      $this->createMock('Drupal\\lms\\TrainingManager'),
    );
    $dto = $adapter->fromContrib($answer);

    self::assertSame(7, $dto->id);
    self::assertSame(5, $dto->activityId);
    self::assertSame(42, $dto->uid);
    self::assertSame(CourseAnswer::STATE_SUBMITTED, $dto->state);
    self::assertNull($dto->score);
  }

  /** Tests mapping a graded contrib answer. */
  public function testGradedAnswerMapping(): void {
    $answer = $this->createMock('Drupal\\lms\\Entity\\AnswerInterface');
    $activity = $this->createMock('Drupal\\lms\\Entity\\ActivityInterface');
    $activity->method('id')->willReturn('5');
    $scoreField = $this->createMock(FieldItemListInterface::class);
    $scoreField->method('isEmpty')->willReturn(FALSE);
    $answer->method('get')->with('score')->willReturn($scoreField);
    $answer->method('id')->willReturn('8');
    $answer->method('getActivity')->willReturn($activity);
    $answer->method('getUserId')->willReturn('42');
    $answer->method('isEvaluated')->willReturn(TRUE);
    $answer->method('getScore')->willReturn(4);
    $lesson = $this->createMock('Drupal\\lms\\Entity\\LessonInterface');
    $lessonStatus = $this->createMock('Drupal\\lms\\Entity\\LessonStatusInterface');
    $lessonStatus->method('getLesson')->willReturn($lesson);
    $answer->method('getLessonStatus')->willReturn($lessonStatus);
    $trainingManager = $this->createMock('Drupal\\lms\\TrainingManager');
    $trainingManager->expects(self::once())
      ->method('getActivityMaxScore')
      ->with($lesson, $activity)
      ->willReturn(5);
    $adapter = new ContribCourseAnswerAdapter(
      $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class),
      $trainingManager,
    );

    $dto = $adapter->fromContrib($answer);

    self::assertSame(CourseAnswer::STATE_GRADED, $dto->state);
    self::assertSame(80.0, $dto->score);
  }

  /** Tests that an unrecognized evaluation state is rejected. */
  public function testUnknownAnswerStateIsRejected(): void {
    $this->expectException(\UnexpectedValueException::class);
    ContribCourseAnswerAdapter::stateFromEvaluated(NULL);
  }

  /** Tests that evaluated scores without a configured maximum are rejected. */
  public function testAnswerWithoutMaximumScoreIsRejected(): void {
    $answer = $this->createMock('Drupal\\lms\\Entity\\AnswerInterface');
    $activity = $this->createMock('Drupal\\lms\\Entity\\ActivityInterface');
    $activity->method('id')->willReturn('5');
    $lesson = $this->createMock('Drupal\\lms\\Entity\\LessonInterface');
    $lessonStatus = $this->createMock('Drupal\\lms\\Entity\\LessonStatusInterface');
    $lessonStatus->method('getLesson')->willReturn($lesson);
    $scoreField = $this->createMock(FieldItemListInterface::class);
    $scoreField->method('isEmpty')->willReturn(FALSE);
    $answer->method('get')->with('score')->willReturn($scoreField);
    $answer->method('getActivity')->willReturn($activity);
    $answer->method('getLessonStatus')->willReturn($lessonStatus);
    $answer->method('isEvaluated')->willReturn(TRUE);
    $answer->method('getScore')->willReturn(4);
    $trainingManager = $this->createMock('Drupal\\lms\\TrainingManager');
    $trainingManager->method('getActivityMaxScore')->willReturn(0);
    $adapter = new ContribCourseAnswerAdapter(
      $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class),
      $trainingManager,
    );

    $this->expectException(\UnexpectedValueException::class);
    $adapter->fromContrib($answer);
  }

}
