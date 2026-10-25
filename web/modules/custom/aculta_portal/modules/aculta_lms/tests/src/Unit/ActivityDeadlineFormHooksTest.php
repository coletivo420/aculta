<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\Hook\ActivityDeadlineFormHooks;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests deadline display fails closed if the answer lost its LMS context.
 */
#[CoversClass(ActivityDeadlineFormHooks::class)]
#[Group('aculta_lms')]
final class ActivityDeadlineFormHooksTest extends UnitTestCase {

  /**
   * Missing LessonStatus omits the deadline label without throwing.
   */
  public function testAnswerFormWithMissingLessonStatusRemainsUsable(): void {
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('get')->with('lesson_status')->willReturn($this->referenceField(NULL));
    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($answer);
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);
    $deadlines = (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor();
    $form = ['existing' => ['#plain_text' => 'answer form']];

    (new ActivityDeadlineFormHooks($deadlines, $this->createMock(DateFormatterInterface::class), $this->getStringTranslationStub()))
      ->answerFormAlter($form, $formState);

    self::assertSame(['existing' => ['#plain_text' => 'answer form']], $form);
  }

  /**
   * Missing activity reference omits deadline without calling the typed getter.
   */
  public function testAnswerFormWithMissingActivityRemainsUsable(): void {
    $course = $this->createMock(CourseInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course_status = $this->createMock(CourseStatusInterface::class);
    $course_status->method('get')->with(CourseStatusInterface::COURSE_FIELD)
      ->willReturn($this->referenceField($course));
    $lesson_status = $this->createMock(LessonStatusInterface::class);
    $lesson_status->method('get')->with('course_status')->willReturn($this->referenceField($course_status));
    $learner = $this->createMock(UserInterface::class);
    $answer = $this->createMock(AnswerInterface::class);
    $answer->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => match ($field) {
      'lesson_status' => $this->referenceField($lesson_status),
      AnswerInterface::ACTIVITY_FIELD => $this->referenceField(NULL),
      default => throw new \InvalidArgumentException('Unexpected field: ' . $field),
    });
    $answer->method('getOwner')->willReturn($learner);
    $answer->expects(self::never())->method('getActivity');
    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($answer);
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);
    $deadlines = (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor();
    $form = ['existing' => ['#plain_text' => 'answer form']];

    (new ActivityDeadlineFormHooks($deadlines, $this->createMock(DateFormatterInterface::class), $this->getStringTranslationStub()))
      ->answerFormAlter($form, $formState);

    self::assertSame(['existing' => ['#plain_text' => 'answer form']], $form);
  }

  /**
   * Creates a field item list whose reference can be absent or available.
   */
  private function referenceField(?object $entity): FieldItemListInterface {
    return new class($entity) extends FieldItemList {

      public function __construct(private readonly ?object $referencedEntity) {}

      /**
       * Returns the synthetic referenced entity for the test.
       */
      public function __get($property_name) {
        if ($property_name === 'entity') {
          return $this->referencedEntity;
        }
        return parent::__get($property_name);
      }

    };
  }

}
