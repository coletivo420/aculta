<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Form\ActivityAuthoringForm;
use Drupal\aculta_lms\Form\LessonAuthoringForm;
use Drupal\aculta_lms\Entity\LessonEntity;
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
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests course lesson and activity authoring forms.
 *
 * @coversDefaultClass \Drupal\aculta_lms\Form\LessonAuthoringForm */
#[CoversClass(LessonAuthoringForm::class)]
#[CoversClass(ActivityAuthoringForm::class)]
#[CoversClass(LessonEntity::class)]
final class CourseAuthoringFormsTest extends UnitTestCase {

  /**
   * @covers ::buildForm
   */
  public function testLessonAuthoringFormAttachesUnsavedChangeGuard(): void {
    $course = $this->course();
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_lesson')->willReturn($storage);
    $form = new LessonAuthoringForm(
      $manager,
      $this->route(['course' => $course, 'lesson' => NULL]),
      $this->access($manager),
    );
    $this->setFormServices($form);

    $build = $form->buildForm([], new FormState());

    self::assertTrue($build['#attributes']['data-aculta-unsaved-changes']);
    self::assertContains('aculta_lms/unsaved_changes', $build['#attached']['library']);
  }

  /**
   * @covers ::buildForm
   */
  public function testActivityAuthoringFormAttachesUnsavedChangeGuard(): void {
    $course = $this->course();
    $lesson = $this->lesson(3);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_activity')->willReturn($storage);
    $form = new ActivityAuthoringForm(
      $manager,
      $this->route(['course' => $course, 'lesson' => $lesson, 'activity' => NULL]),
      $this->access($manager),
    );
    $this->setFormServices($form);

    $build = $form->buildForm([], new FormState());

    self::assertTrue($build['#attributes']['data-aculta-unsaved-changes']);
    self::assertContains('aculta_lms/unsaved_changes', $build['#attached']['library']);
  }

  /**
   * @covers ::submitForm
   */
  public function testLessonCreateStoresRichTextInDedicatedFormat(): void {
    $course = $this->course();
    $route = $this->route(['course' => $course, 'lesson' => NULL]);
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->expects(self::once())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('create')->with([
      'course_id' => 3,
      'title' => 'Aula introdutória',
      'body' => ['value' => '<p><strong>Conteúdo seguro</strong></p>', 'format' => 'aculta_lms_lesson'],
      'published' => FALSE,
      'weight' => 0,
    ])->willReturn($lesson);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_lms_lesson')->willReturn($storage);
    $form = new LessonAuthoringForm($manager, $route, $this->access($manager));
    $this->setFormServices($form);
    $state = new FormState();
    $state->setValues([
      'title' => ' Aula introdutória ',
      'body' => ['value' => '<p><strong>Conteúdo seguro</strong></p>', 'format' => 'aculta_lms_lesson'],
      'published' => 0,
      'weight' => '0',
    ]);

    $formArray = [];
    $form->submitForm($formArray, $state);

    self::assertSame('aculta_lms.course_structure', $state->getRedirect()->getRouteName());
    self::assertSame(['course' => '3'], $state->getRedirect()->getRouteParameters());
  }

  /**
   * @covers ::validateForm
   */
  public function testPublishedLessonRequiresReadableContent(): void {
    $course = $this->course();
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $form = new LessonAuthoringForm(
      $manager,
      $this->route(['course' => $course, 'lesson' => NULL]),
      $this->access($manager),
    );
    $this->setFormServices($form);
    $state = new FormState();
    $state->setValues([
      'title' => 'Aula vazia',
      'published' => 1,
      'body' => ['value' => '<p> &nbsp; </p>', 'format' => 'aculta_lms_lesson'],
    ]);

    $form_array = [];
    $form->validateForm($form_array, $state);

    self::assertArrayHasKey('body', $state->getErrors());
  }

  /**
   * @covers ::prepareBodyForEditor
   */
  public function testLegacyTextIsEscapedAndRichTextIsPreservedForEditor(): void {
    self::assertSame(
      '&lt;script&gt;alert(1)&lt;/script&gt;',
      LessonAuthoringForm::prepareBodyForEditor('<script>alert(1)</script>', 'plain_text'),
    );
    self::assertSame(
      '<p><strong>Conteúdo</strong></p>',
      LessonAuthoringForm::prepareBodyForEditor('<p><strong>Conteúdo</strong></p>', 'aculta_lms_lesson'),
    );
  }

  /**
   * @covers ::assertBodyFormat
   */
  public function testLessonRejectsUnapprovedBodyFormat(): void {
    $this->expectException(\InvalidArgumentException::class);
    LessonEntity::assertBodyFormat('full_html');
  }

  /**
   * @covers ::assertBodyFormat
   */
  public function testLessonAcceptsSupportedBodyFormats(): void {
    LessonEntity::assertBodyFormat('plain_text');
    LessonEntity::assertBodyFormat('aculta_lms_lesson');
    self::assertTrue(TRUE);
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
    $container = new ContainerBuilder();
    $container->set('current_user', $this->createMock(AccountProxyInterface::class));
    \Drupal::setContainer($container);
  }

  /**
   * Creates a field item list fixture.
   */
  private function field(mixed $value): FieldItemListInterface {
    return new class($value) extends FieldItemList {

      public function __construct(protected readonly mixed $fieldValue) {}

      /**
       * Returns the fixture field value.
       */
      public function __get($property_name) {
        return is_array($this->fieldValue)
          ? ($this->fieldValue[$property_name] ?? NULL)
          : ($property_name === 'value' ? $this->fieldValue : NULL);
      }

    };
  }

}
