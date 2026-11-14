<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Controller\CourseStructureController;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Url;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the educator course structure page.
 */
#[CoversClass(CourseStructureController::class)]
final class CourseStructureControllerTest extends UnitTestCase {

  /**
   * Sets up Drupal URL generation for render-link assertions.
   */
  protected function setUp(): void {
    parent::setUp();
    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generateFromRoute')->willReturnCallback(
      static fn(string $name, array $parameters, array $options, bool $collect): string|GeneratedUrl => $collect
        ? (new GeneratedUrl())->setGeneratedUrl('/ganjalab/educador')
        : '/ganjalab/educador',
    );
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);
  }

  /**
   */
  public function testCourseStructureListsLessonsAndActivitiesFromOwnedEntities(): void {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('3');
    $course->method('label')->willReturn('Curso de exemplo');
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('id')->willReturn('11');
    $lesson->method('get')->willReturnCallback(static fn(string $field): FieldItemListInterface => self::field(match ($field) {
        'title' => 'Primeira aula',
        'published' => TRUE,
        'weight' => 0,
        default => NULL,
    }));
    $secondLesson = $this->createMock(ContentEntityInterface::class);
    $secondLesson->method('id')->willReturn('12');
    $secondLesson->method('get')->willReturnCallback(static fn(string $field): FieldItemListInterface => self::field(match ($field) {
        'title' => 'Segunda aula',
        'published' => FALSE,
        'weight' => 1,
        default => NULL,
    }));
    $activity = $this->createMock(ContentEntityInterface::class);
    $activity->method('id')->willReturn('21');
    $activity->method('get')->willReturnCallback(static fn(string $field): FieldItemListInterface => self::field(match ($field) {
        'type' => 'free_text',
        'weight' => 0,
        'answer_config' => '{"question":"Explique a ideia."}',
        default => NULL,
    }));
    $secondActivity = $this->createMock(ContentEntityInterface::class);
    $secondActivity->method('id')->willReturn('22');
    $secondActivity->method('get')->willReturnCallback(static fn(string $field): FieldItemListInterface => self::field(match ($field) {
        'type' => 'select',
        'weight' => 1,
        'answer_config' => '{"question":"Selecione"}',
        default => NULL,
    }));

    $lessonQuery = $this->query([11, 12]);
    $activityQuery = $this->query([21, 22]);
    $answerQuery = $this->query(0);
    $lessonStorage = $this->storage($lessonQuery, [11 => $lesson, 12 => $secondLesson]);
    $activityStorage = $this->storage($activityQuery, [21 => $activity, 22 => $secondActivity]);
    $answerStorage = $this->createMock(EntityStorageInterface::class);
    $answerStorage->method('getQuery')->willReturn($answerQuery);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([
      ['aculta_lms_lesson', $lessonStorage],
      ['aculta_lms_activity', $activityStorage],
      ['aculta_lms_answer', $answerStorage],
    ]);

    $formBuilder = $this->createMock(FormBuilderInterface::class);
    $formBuilder->method('getForm')->willReturn(['#type' => 'form']);
    $build = (new CourseStructureController($manager, $this->getStringTranslationStub(), $formBuilder))->structure($course);

    self::assertSame('component', self::renderValue($build, '#type'));
    self::assertSame('aculta420_v2:lms-course-curriculum', self::renderValue($build, '#component'));
    self::assertSame(['aculta_lms/course_structure'], self::renderValue($build, '#attached', 'library'));
    self::assertSame('user', self::renderValue($build, '#cache', 'contexts', 0));
    self::assertSame('Curso de exemplo', self::renderValue($build, '#slots', 'header', 'title', '#value'));
    self::assertSame('Estrutura do curso', self::renderText($build, '#slots', 'lessons_heading', '#value'));
    self::assertCount(2, self::renderArray($build, '#slots', 'lessons', '#items'));
    self::assertSame('lessons', self::renderValue($build, '#slots', 'lessons', '#attributes', 'data-aculta-sort-list'));
    $firstLesson = self::renderArray($build, '#slots', 'lessons', '#items', 0);
    $secondLesson = self::renderArray($build, '#slots', 'lessons', '#items', 1);
    self::assertSame('11', self::renderValue($firstLesson, '#attributes', 'data-aculta-sort-item'));
    self::assertTrue(self::renderValue($firstLesson, 'heading', 'sort_handle', '#attributes', 'data-aculta-sort-handle'));
    self::assertSame('true', self::renderValue($firstLesson, 'heading', 'sort_handle', '#attributes', 'aria-hidden'));
    self::assertSame('Primeira aula', self::renderText($firstLesson, 'heading', 'title', '#title'));
    self::assertSame('aculta_lms.lesson_edit', self::renderUrl($firstLesson, 'heading', 'title', '#url')->getRouteName());
    self::assertSame('aculta_lms.lesson_preview', self::renderUrl($firstLesson, 'heading', 'preview', '#url')->getRouteName());
    self::assertSame('Publicada', self::renderText($firstLesson, 'heading', 'state', '#value'));
    self::assertCount(2, self::renderArray($firstLesson, 'activities', '#items'));
    self::assertSame('activities', self::renderValue($firstLesson, 'activities', '#attributes', 'data-aculta-sort-list'));
    self::assertSame('21', self::renderValue($firstLesson, 'activities', '#items', 0, '#attributes', 'data-aculta-sort-item'));
    self::assertTrue(self::renderValue($firstLesson, 'activities', '#items', 0, 'sort_handle', '#attributes', 'data-aculta-sort-handle'));
    self::assertSame('Explique a ideia.', self::renderText($firstLesson, 'activities', '#items', 0, 'question', '#plain_text'));
    self::assertSame('aculta_lms.activity_edit', self::renderUrl($firstLesson, 'activities', '#items', 0, 'title', '#url')->getRouteName());
    self::assertSame('aculta_lms.activity_delete', self::renderUrl($firstLesson, 'activities', '#items', 0, 'actions', 'delete', '#url')->getRouteName());
    self::assertSame('aculta_lms.lesson_delete', self::renderUrl($firstLesson, 'actions', 'delete', '#url')->getRouteName());
    self::assertSame('aculta_lms.lesson_move', self::renderUrl($firstLesson, 'actions', 'move_down', '#url')->getRouteName());
    self::assertSame('abaixo', self::renderUrl($firstLesson, 'actions', 'move_down', '#url')->getRouteParameters()['direcao']);
    self::assertSame('aculta_lms.lesson_move', self::renderUrl($secondLesson, 'actions', 'move_up', '#url')->getRouteName());
    self::assertSame('acima', self::renderUrl($secondLesson, 'actions', 'move_up', '#url')->getRouteParameters()['direcao']);
    self::assertSame('aculta_lms.activity_move', self::renderUrl($firstLesson, 'activities', '#items', 0, 'actions', 'move_down', '#url')->getRouteName());
    self::assertSame('abaixo', self::renderUrl($firstLesson, 'activities', '#items', 0, 'actions', 'move_down', '#url')->getRouteParameters()['direcao']);
    self::assertSame('aculta_lms.activity_move', self::renderUrl($firstLesson, 'activities', '#items', 1, 'actions', 'move_up', '#url')->getRouteName());
    self::assertSame('2 aulas', self::renderText($build, '#slots', 'summary', 'lessons', '#plain_text'));
  }

  /**
   * Creates an entity query fixture.
   *
   * @param list<int|string>|int $result
   *   Query result IDs, or an integer count result.
   */
  private function query(array|int $result): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($result);
    return $query;
  }

  /**
   * Creates entity storage around a query fixture.
   *
   * @param array<int|string, ContentEntityInterface> $entities
   *   Entities keyed by their storage IDs.
   */
  private function storage(QueryInterface $query, array $entities): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn($entities);
    return $storage;
  }

  /**
   * Creates a generic field item list fixture.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   *   Field item list exposing the supplied test value.
   */
  private static function field(mixed $value): FieldItemListInterface {
    return new class($value) extends FieldItemList {

      public function __construct(protected readonly mixed $fieldValue) {}

      /**
       * Returns the fixture field value.
       */
      public function __get(mixed $property_name): mixed {
        return $property_name === 'value' ? $this->fieldValue : NULL;
      }

    };
  }

  /**
   * Reads a render-array value after validating every container in its path.
   *
   * @param array<array-key, mixed> $build
   *   Render array at the root of the path.
   * @param int|string ...$path
   *   Sequence of render-array keys.
   *
   * @return mixed
   *   Value at the requested path.
   */
  private static function renderValue(array $build, string|int ...$path): mixed {
    $value = $build;
    foreach ($path as $key) {
      self::assertIsArray($value);
      self::assertArrayHasKey($key, $value);
      $value = $value[$key];
    }
    return $value;
  }

  /**
   * Reads a nested render array after checking its runtime type.
   *
   * @param array<array-key, mixed> $build
   *   Render array at the root of the path.
   * @param int|string ...$path
   *   Sequence of render-array keys.
   *
   * @return array<array-key, mixed>
   *   Render array at the requested path.
   */
  private static function renderArray(array $build, string|int ...$path): array {
    $value = self::renderValue($build, ...$path);
    if (!is_array($value)) {
      self::fail('Expected a nested render array.');
    }
    return $value;
  }

  /**
   * Reads a Drupal URL from a nested render array.
   *
   * @param array<array-key, mixed> $build
   *   Render array at the root of the path.
   * @param int|string ...$path
   *   Sequence of render-array keys.
   */
  private static function renderUrl(array $build, string|int ...$path): Url {
    $value = self::renderValue($build, ...$path);
    self::assertInstanceOf(Url::class, $value);
    return $value;
  }

  /**
   * Reads a stringable display value from a nested render array.
   *
   * @param array<array-key, mixed> $build
   *   Render array at the root of the path.
   * @param int|string ...$path
   *   Sequence of render-array keys.
   */
  private static function renderText(array $build, string|int ...$path): string {
    $value = self::renderValue($build, ...$path);
    if (!is_string($value) && !$value instanceof \Stringable) {
      self::fail('Expected a stringable render value.');
    }
    return (string) $value;
  }

}
