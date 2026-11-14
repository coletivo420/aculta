<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Form\ActivityDeleteForm;
use Drupal\aculta_lms\Form\LessonDeleteForm;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests safe deletion boundaries for course content. */
#[CoversClass(LessonDeleteForm::class)]
#[CoversClass(ActivityDeleteForm::class)]
final class CourseContentDeleteFormTest extends UnitTestCase {

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * A lesson with activities is kept intact. */
  public function testLessonWithActivitiesCannotBeDeleted(): void {
    [$form, $lesson, $activity_storage] = $this->lessonForm();
    $lesson->expects(self::never())->method('delete');
    $activity_storage->expects(self::once())->method('getQuery')->willReturn($this->query([10]));
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addError');
    $form->setMessenger($messenger);
    $state = $this->createMock(FormStateInterface::class);
    $state->expects(self::once())->method('setRedirect')->with('aculta_lms.course_structure', ['course' => '3']);
    $form_array = [];
    $form->submitForm($form_array, $state);
  }

  /**
   * An empty lesson can be removed. */
  public function testEmptyLessonCanBeDeleted(): void {
    [$form, $lesson, $activity_storage] = $this->lessonForm();
    $lesson->expects(self::once())->method('delete');
    $activity_storage->expects(self::once())->method('getQuery')->willReturn($this->query([]));
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addStatus');
    $form->setMessenger($messenger);
    $state = $this->createMock(FormStateInterface::class);
    $state->expects(self::once())->method('setRedirect');
    $form_array = [];
    $form->submitForm($form_array, $state);
  }

  /**
   * A lesson referenced by a preserved attempt snapshot cannot be deleted. */
  public function testLessonWithAttemptArchiveCannotBeDeleted(): void {
    [$form, $lesson, $activity_storage] = $this->lessonForm(['501']);
    $lesson->expects(self::never())->method('delete');
    $activity_storage->expects(self::once())->method('getQuery')->willReturn($this->query([]));
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addError');
    $form->setMessenger($messenger);
    $state = $this->createMock(FormStateInterface::class);
    $state->expects(self::once())->method('setRedirect');
    $form_array = [];
    $form->submitForm($form_array, $state);
  }

  /**
   * Activities with submitted answers cannot be removed. */
  public function testActivityWithAnswersCannotBeDeleted(): void {
    [$form, $activity] = $this->activityForm([20]);
    $activity->expects(self::never())->method('delete');
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addError');
    $form->setMessenger($messenger);
    $state = $this->createMock(FormStateInterface::class);
    $state->expects(self::once())->method('setRedirect');
    $form_array = [];
    $form->submitForm($form_array, $state);
  }

  /**
   * An unanswered activity can be removed. */
  public function testUnansweredActivityCanBeDeleted(): void {
    [$form, $activity] = $this->activityForm([]);
    $activity->expects(self::once())->method('delete');
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addStatus');
    $form->setMessenger($messenger);
    $state = $this->createMock(FormStateInterface::class);
    $state->expects(self::once())->method('setRedirect');
    $form_array = [];
    $form->submitForm($form_array, $state);
  }

  /**
   * An activity referenced by a preserved attempt snapshot cannot be deleted. */
  public function testActivityWithAttemptArchiveCannotBeDeleted(): void {
    [$form, $activity] = $this->activityForm([], ['501']);
    $activity->expects(self::never())->method('delete');
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addError');
    $form->setMessenger($messenger);
    $state = $this->createMock(FormStateInterface::class);
    $state->expects(self::once())->method('setRedirect');
    $form_array = [];
    $form->submitForm($form_array, $state);
  }

  /**
   * Builds a lesson deletion form and its storage fixtures.
   *
   * @param list<int|string> $archive_ids
   *   Attempt archive identifiers.
   *
   * @return array{0: \Drupal\aculta_lms\Form\LessonDeleteForm, 1: \Drupal\Core\Entity\ContentEntityInterface&\PHPUnit\Framework\MockObject\MockObject, 2: \Drupal\Core\Entity\EntityStorageInterface&\PHPUnit\Framework\MockObject\MockObject, 3: \Drupal\Core\Entity\EntityStorageInterface&\PHPUnit\Framework\MockObject\MockObject}
   *   The form, lesson and both entity storages.
   */
  private function lessonForm(array $archive_ids = []): array {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('3');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->willReturn(TRUE);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('id')->willReturn('11');
    $lesson->method('getEntityTypeId')->willReturn('aculta_lms_lesson');
    $lesson->method('get')->with('course_id')->willReturn($this->field('3'));
    $activity_storage = $this->createMock(EntityStorageInterface::class);
    $archive_storage = $this->createMock(EntityStorageInterface::class);
    $archive_storage->method('getQuery')->willReturn($this->query($archive_ids));
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([
      ['aculta_lms_activity', $activity_storage],
      ['aculta_lms_attempt_archive', $archive_storage],
    ]);
    $route = $this->routeMatch(['course' => $course, 'lesson' => $lesson]);
    $access = $this->accessCheck($manager);
    $form = new LessonDeleteForm($manager, $route, $access);
    $form->setStringTranslation($this->getStringTranslationStub());
    return [$form, $lesson, $activity_storage, $archive_storage];
  }

  /**
   * Builds an activity deletion form and its storage fixtures.
   *
   * @param list<int|string> $answer_ids
   *   Answer identifiers for the activity.
   * @param list<int|string> $archive_ids
   *   Attempt archive identifiers.
   *
   * @return array{0: \Drupal\aculta_lms\Form\ActivityDeleteForm, 1: \Drupal\Core\Entity\ContentEntityInterface&\PHPUnit\Framework\MockObject\MockObject, 2: \Drupal\Core\Entity\EntityStorageInterface&\PHPUnit\Framework\MockObject\MockObject, 3: \Drupal\Core\Entity\EntityStorageInterface&\PHPUnit\Framework\MockObject\MockObject}
   *   The form, activity and both entity storages.
   */
  private function activityForm(array $answer_ids, array $archive_ids = []): array {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('3');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->willReturn(TRUE);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('id')->willReturn('11');
    $lesson->method('getEntityTypeId')->willReturn('aculta_lms_lesson');
    $lesson->method('get')->with('course_id')->willReturn($this->field('3'));
    $activity = $this->createMock(ContentEntityInterface::class);
    $activity->method('id')->willReturn('21');
    $activity->method('getEntityTypeId')->willReturn('aculta_lms_activity');
    $activity->method('get')->with('lesson_id')->willReturn($this->field('11'));
    $answer_storage = $this->createMock(EntityStorageInterface::class);
    $query_call = 0;
    $answer_storage->method('getQuery')->willReturnCallback(function () use (&$query_call, $answer_ids): QueryInterface {
      return $this->query(++$query_call === 1 ? 0 : $answer_ids);
    });
    $archive_storage = $this->createMock(EntityStorageInterface::class);
    $archive_storage->method('getQuery')->willReturn($this->query($archive_ids));
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([
      ['aculta_lms_answer', $answer_storage],
      ['aculta_lms_attempt_archive', $archive_storage],
    ]);
    $route = $this->routeMatch(['course' => $course, 'lesson' => $lesson, 'activity' => $activity]);
    $access = $this->accessCheck($manager);
    $form = new ActivityDeleteForm($manager, $route, $access);
    $form->setStringTranslation($this->getStringTranslationStub());
    return [$form, $activity, $answer_storage, $archive_storage];
  }

  /**
   * Builds the actual contextual course authoring access checker. */
  private function accessCheck(EntityTypeManagerInterface $manager): CourseAuthoringAccessCheck {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    return new CourseAuthoringAccessCheck($account, $manager);
  }

  /**
   * Creates a simple field item fixture.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   *   The field item list.
   */
  private function field(string $value): FieldItemListInterface {
    return new class($value) extends FieldItemList {

      public function __construct(protected readonly string $fieldValue) {}

      /**
       * Returns the fixture field value.
       */
      public function __get(mixed $property_name): mixed {
        return $property_name === 'value' ? $this->fieldValue : NULL;
      }

    };
  }

  /**
   * Creates a route match fixture.
   *
   * @param array<string, \Drupal\group\Entity\GroupInterface|\Drupal\Core\Entity\ContentEntityInterface> $parameters
   *   Route parameters used by the deletion forms.
   */
  private function routeMatch(array $parameters): CurrentRouteMatch {
    $route = $this->createMock(CurrentRouteMatch::class);
    $route->method('getParameter')->willReturnCallback(static fn(string $name): mixed => $parameters[$name] ?? NULL);
    return $route;
  }

  /**
   * Creates a query returning IDs.
   *
   * @param list<int|string>|int $ids
   *   Result identifiers, or the count result used by answer checks.
   */
  private function query(array|int $ids): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($ids);
    return $query;
  }

}
