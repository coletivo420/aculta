<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\aculta_lms\Routing\LmsFriendlyRouteProcessor;
use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests cache metadata on generated LMS links.
 */
#[CoversClass(LmsFriendlyRouteProcessor::class)]
#[Group('aculta_lms')]
final class LmsFriendlyRouteProcessorTest extends UnitTestCase {

  /**
   * Generated answer URLs vary when lessons or activities change.
   */
  public function testAnswerUrlBubblesLessonAndActivityListTags(): void {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('88');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('label')->willReturn('Curso de teste');
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn(['group:88']);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $lesson = $this->contentEntity('aculta_lms_lesson', 10, 'Introdução');
    $activity = $this->contentEntity('aculta_lms_activity', 20, 'free_text');
    $groupStorage = $this->storage([88], [88 => $course]);
    $lessonStorage = $this->storage([10], [10 => $lesson]);
    $activityStorage = $this->storage([20], [20 => $activity]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['group', $groupStorage],
      ['aculta_lms_lesson', $lessonStorage],
      ['aculta_lms_activity', $activityStorage],
    ]);

    $transliteration = $this->createMock(PhpTransliteration::class);
    $transliteration->method('transliterate')->willReturnCallback(
      static fn(string $value): string => strtolower(strtr($value, ['ç' => 'c', 'ã' => 'a'])),
    );
    $slugs = new LmsFriendlySlugs($entityTypeManager, $transliteration);
    $processor = new LmsFriendlyRouteProcessor($entityTypeManager, $slugs);
    $parameters = [
      'group' => $course,
      'lesson_delta' => 0,
      'activity_delta' => 0,
    ];
    $metadata = new BubbleableMetadata();

    $processor->processOutbound(
      'aculta_lms.answer_submission',
      new Route('/curso/{group}/{lesson_delta}/{activity_delta}'),
      $parameters,
      $metadata,
    );

    self::assertSame('curso-de-teste', $parameters['group']);
    self::assertSame('introducao', $parameters['lesson_delta']);
    self::assertSame('free-text', $parameters['activity_delta']);
    self::assertContains(LmsFriendlySlugs::LESSON_LIST_CACHE_TAG, $metadata->getCacheTags());
    self::assertContains(LmsFriendlySlugs::ACTIVITY_LIST_CACHE_TAG, $metadata->getCacheTags());
  }

  /**
   * Creates storage and query doubles for ordered entity results.
   *
   * @param array<int|string> $ids
   *   Entity IDs returned by the query.
   * @param array<int|string,object> $entities
   *   Entities returned by storage.
   */
  private function storage(array $ids, array $entities): EntityStorageInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn($ids);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn($entities);
    return $storage;
  }

  /**
   * Creates an entity double with the scalar field used by slug generation.
   */
  private function contentEntity(string $entityTypeId, int $id, string $value): ContentEntityInterface {
    $fieldName = $entityTypeId === 'aculta_lms_lesson' ? 'title' : 'type';
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['value' => $value]]);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entityTypeId);
    $entity->method('id')->willReturn((string) $id);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([$entityTypeId . ':' . $id]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    $entity->method('get')->with($fieldName)->willReturn($field);
    return $entity;
  }

}
