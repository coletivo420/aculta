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

    self::assertSame('component', $build['#type']);
    self::assertSame('aculta420_v2:lms-course-curriculum', $build['#component']);
    self::assertSame(['aculta_lms/course_structure'], $build['#attached']['library']);
    self::assertSame('user', $build['#cache']['contexts'][0]);
    self::assertSame('Curso de exemplo', $build['#slots']['header']['title']['#value']);
    self::assertSame('Estrutura do curso', (string) $build['#slots']['lessons_heading']['#value']);
    self::assertCount(2, $build['#slots']['lessons']['#items']);
    self::assertSame('lessons', $build['#slots']['lessons']['#attributes']['data-aculta-sort-list']);
    $firstLesson = $build['#slots']['lessons']['#items'][0];
    $secondLesson = $build['#slots']['lessons']['#items'][1];
    self::assertSame('11', $firstLesson['#attributes']['data-aculta-sort-item']);
    self::assertTrue($firstLesson['heading']['sort_handle']['#attributes']['data-aculta-sort-handle']);
    self::assertSame('true', $firstLesson['heading']['sort_handle']['#attributes']['aria-hidden']);
    self::assertSame('Primeira aula', (string) $firstLesson['heading']['title']['#title']);
    self::assertSame('aculta_lms.lesson_edit', $firstLesson['heading']['title']['#url']->getRouteName());
    self::assertSame('aculta_lms.lesson_preview', $firstLesson['heading']['preview']['#url']->getRouteName());
    self::assertSame('Publicada', (string) $firstLesson['heading']['state']['#value']);
    self::assertCount(2, $firstLesson['activities']['#items']);
    self::assertSame('activities', $firstLesson['activities']['#attributes']['data-aculta-sort-list']);
    self::assertSame('21', $firstLesson['activities']['#items'][0]['#attributes']['data-aculta-sort-item']);
    self::assertTrue($firstLesson['activities']['#items'][0]['sort_handle']['#attributes']['data-aculta-sort-handle']);
    self::assertSame('Explique a ideia.', (string) $firstLesson['activities']['#items'][0]['question']['#plain_text']);
    self::assertSame('aculta_lms.activity_edit', $firstLesson['activities']['#items'][0]['title']['#url']->getRouteName());
    self::assertSame('aculta_lms.activity_delete', $firstLesson['activities']['#items'][0]['actions']['delete']['#url']->getRouteName());
    self::assertSame('aculta_lms.lesson_delete', $firstLesson['actions']['delete']['#url']->getRouteName());
    self::assertSame('aculta_lms.lesson_move', $firstLesson['actions']['move_down']['#url']->getRouteName());
    self::assertSame('abaixo', $firstLesson['actions']['move_down']['#url']->getRouteParameters()['direcao']);
    self::assertSame('aculta_lms.lesson_move', $secondLesson['actions']['move_up']['#url']->getRouteName());
    self::assertSame('acima', $secondLesson['actions']['move_up']['#url']->getRouteParameters()['direcao']);
    self::assertSame('aculta_lms.activity_move', $firstLesson['activities']['#items'][0]['actions']['move_down']['#url']->getRouteName());
    self::assertSame('abaixo', $firstLesson['activities']['#items'][0]['actions']['move_down']['#url']->getRouteParameters()['direcao']);
    self::assertSame('aculta_lms.activity_move', $firstLesson['activities']['#items'][1]['actions']['move_up']['#url']->getRouteName());
    self::assertSame('2 aulas', (string) $build['#slots']['summary']['lessons']['#plain_text']);
  }

  /**
   * Creates an entity query fixture.
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
   */
  private function storage(QueryInterface $query, array $entities): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn($entities);
    return $storage;
  }

  /**
   * Creates a field item list fixture.
   */
  private static function field(mixed $value): FieldItemListInterface {
    return new class($value) extends FieldItemList {

      public function __construct(protected readonly mixed $fieldValue) {}

      /**
       * Returns the fixture field value.
       */
      public function __get($property_name) {
        return $property_name === 'value' ? $this->fieldValue : NULL;
      }

    };
  }

}
