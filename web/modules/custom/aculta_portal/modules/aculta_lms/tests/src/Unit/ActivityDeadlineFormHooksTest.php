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
use Drupal\Tests\UnitTestCase;
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

    (new ActivityDeadlineFormHooks($deadlines, $this->createMock(DateFormatterInterface::class)))
      ->answerFormAlter($form, $formState);

    self::assertSame(['existing' => ['#plain_text' => 'answer form']], $form);
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
