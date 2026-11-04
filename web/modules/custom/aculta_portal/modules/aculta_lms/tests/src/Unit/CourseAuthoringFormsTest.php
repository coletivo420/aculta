<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Form\ActivityAuthoringForm;
use Drupal\aculta_lms\Form\LessonAuthoringForm;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests course lesson and activity authoring forms.
 *
 * @coversDefaultClass \Drupal\aculta_lms\Form\LessonAuthoringForm */
#[CoversClass(LessonAuthoringForm::class)]
#[CoversClass(ActivityAuthoringForm::class)]
final class CourseAuthoringFormsTest extends UnitTestCase {

  /**
   * @covers ::submitForm
   */
  public function testLessonCreatePersistsOnlyExistingEntityFields(): void {
    $course = $this->course();
    $route = $this->route(['course' => $course, 'lesson' => NULL]);
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->expects(self::once())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('create')->with([
      'course_id' => 3,
      'title' => 'Aula introdutória',
      'published' => FALSE,
      'weight' => 0,
    ])->willReturn($lesson);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_lesson')->willReturn($storage);
    $form = new LessonAuthoringForm($manager, $route, $this->access($manager));
    $this->setFormServices($form);
    $state = new FormState();
    $state->setValues(['title' => ' Aula introdutória ', 'published' => 0, 'weight' => '0']);

    $formArray = [];
    $form->submitForm($formArray, $state);

    self::assertSame('aculta_lms.course_structure', $state->getRedirect()->getRouteName());
    self::assertSame(['course' => '3'], $state->getRedirect()->getRouteParameters());
  }

  /**
   * @covers ::submitForm
   */
  public function testActivityCreatePersistsConfigurationConsumedByLearnerForm(): void {
    $course = $this->course();
    $lesson = $this->lesson(3);
    $route = $this->route(['course' => $course, 'lesson' => $lesson, 'activity' => NULL]);
    $activity = $this->createMock(ContentEntityInterface::class);
    $activity->expects(self::once())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('create')->with([
      'lesson_id' => 11,
      'type' => 'select',
      'graded' => TRUE,
      'weight' => 2,
      'answer_config' => '{"question":"Escolha as respostas.","options":{"A":"A","B":"B"},"correct":["B"]}',
    ])->willReturn($activity);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_activity')->willReturn($storage);
    $form = new ActivityAuthoringForm($manager, $route, $this->access($manager));
    $this->setFormServices($form);
    $state = new FormState();
    $state->setValues([
      'type' => 'select',
      'question' => 'Escolha as respostas.',
      'options' => "A\nB",
      'correct_values' => 'B',
      'weight' => '2',
      'graded' => 1,
    ]);

    $formArray = [];
    $form->submitForm($formArray, $state);

    self::assertSame('aculta_lms.course_structure', $state->getRedirect()->getRouteName());
  }

  /**
   * Creates an educator-owned course fixture.
   */
  private function course(): GroupInterface {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('3');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->willReturn(FALSE);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    return $course;
  }

  /**
   * Creates a lesson fixture belonging to the course.
   */
  private function lesson(int $courseId): ContentEntityInterface {
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('id')->willReturn('11');
    $lesson->method('getEntityTypeId')->willReturn('aculta_lms_lesson');
    $lesson->method('get')->with('course_id')->willReturn($this->field($courseId));
    $lesson->method('getCacheContexts')->willReturn([]);
    $lesson->method('getCacheTags')->willReturn([]);
    $lesson->method('getCacheMaxAge')->willReturn(-1);
    return $lesson;
  }

  /**
   * Creates a route match fixture.
   */
  private function route(array $parameters): CurrentRouteMatch {
    $route = $this->createMock(CurrentRouteMatch::class);
    $route->method('getParameter')->willReturnCallback(static fn(string $name): mixed => $parameters[$name] ?? NULL);
    return $route;
  }

  /**
   * Creates an access checker that grants educator administration.
   */
  private function access(EntityTypeManagerInterface $manager): CourseAuthoringAccessCheck {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->with('administer lms')->willReturn(TRUE);
    return new CourseAuthoringAccessCheck($account, $manager);
  }

  /**
   * Injects the form services used in unit tests.
   */
  private function setFormServices(LessonAuthoringForm|ActivityAuthoringForm $form): void {
    $form->setStringTranslation($this->getStringTranslationStub());
    $form->setMessenger($this->createMock(MessengerInterface::class));
  }

  /**
   * Creates a field item list fixture.
   */
  private function field(mixed $value): FieldItemListInterface {
    return new class($value) extends FieldItemList {

      public function __construct(private readonly mixed $fieldValue) {}

      /**
       * Returns the fixture field value.
       */
      public function __get($property_name) {
        return $property_name === 'value' ? $this->fieldValue : NULL;
      }

    };
  }

}
