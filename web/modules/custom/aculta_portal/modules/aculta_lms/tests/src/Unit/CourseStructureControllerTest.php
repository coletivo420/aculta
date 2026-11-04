<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Controller\CourseStructureController;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
 *
 * @coversDefaultClass \Drupal\aculta_lms\Controller\CourseStructureController */
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
   * @covers ::structure
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

    $build = (new CourseStructureController($manager, $this->getStringTranslationStub()))->structure($course);

    self::assertSame('course-structure', $build['#attributes']['data-aculta-lms']);
    self::assertSame(['aculta_lms/course_structure'], $build['#attached']['library']);
    self::assertSame('Curso de exemplo', $build['course']['#value']);
    self::assertCount(2, $build['lessons']['#rows']);
    self::assertSame('aculta_lms.lesson_edit', $build['lessons']['#rows'][0]['data'][0]['data']['#url']->getRouteName());
    self::assertSame('Publicada', (string) $build['lessons']['#rows'][0]['data'][1]['data']['#plain_text']);
    self::assertCount(2, $build['lessons']['#rows'][0]['data'][3]['data']['#rows']);
    self::assertSame('Explique a ideia.', $build['lessons']['#rows'][0]['data'][3]['data']['#rows'][0]['data'][1]['data']['#plain_text']);
    self::assertSame('aculta_lms.activity_edit', $build['lessons']['#rows'][0]['data'][3]['data']['#rows'][0]['data'][0]['data']['#url']->getRouteName());
    self::assertSame('aculta_lms.activity_delete', $build['lessons']['#rows'][0]['data'][3]['data']['#rows'][0]['data'][4]['data']['delete']['#url']->getRouteName());
    self::assertSame('aculta_lms.lesson_delete', $build['lessons']['#rows'][0]['data'][4]['data']['delete']['#url']->getRouteName());
    self::assertSame('aculta_lms.lesson_move', $build['lessons']['#rows'][0]['data'][4]['data']['move_down']['#url']->getRouteName());
    self::assertSame('abaixo', $build['lessons']['#rows'][0]['data'][4]['data']['move_down']['#url']->getRouteParameters()['direcao']);
    self::assertSame('aculta_lms.lesson_move', $build['lessons']['#rows'][1]['data'][4]['data']['move_up']['#url']->getRouteName());
    self::assertSame('acima', $build['lessons']['#rows'][1]['data'][4]['data']['move_up']['#url']->getRouteParameters()['direcao']);
    self::assertSame('aculta_lms.activity_move', $build['lessons']['#rows'][0]['data'][3]['data']['#rows'][0]['data'][4]['data']['move_down']['#url']->getRouteName());
    self::assertSame('abaixo', $build['lessons']['#rows'][0]['data'][3]['data']['#rows'][0]['data'][4]['data']['move_down']['#url']->getRouteParameters()['direcao']);
    self::assertSame('aculta_lms.activity_move', $build['lessons']['#rows'][0]['data'][3]['data']['#rows'][1]['data'][4]['data']['move_up']['#url']->getRouteName());
    foreach ($build['lessons']['#rows'] as $lesson_row) {
      foreach ($lesson_row['data'] as $cell) {
        self::assertArrayHasKey('data', $cell, 'Render arrays in Drupal table cells must use the cell data wrapper.');
      }
      foreach ($lesson_row['data'][3]['data']['#rows'] as $activity_row) {
        foreach ($activity_row['data'] as $cell) {
          self::assertArrayHasKey('data', $cell, 'Render arrays in nested table cells must use the cell data wrapper.');
        }
      }
    }
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
