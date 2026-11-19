<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Derived LMS slugs are Portuguese, ASCII, lowercase and unique within scope.
 */
#[Group('aculta_lms')]
final class LmsFriendlySlugsTest extends UnitTestCase {

  /**
   * Slug service under test.
   *
   * @var \Drupal\aculta_lms\Routing\LmsFriendlySlugs
   */
  private LmsFriendlySlugs $slugs;

  /**
   * Entity manager double used by the slug service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private EntityTypeManagerInterface&MockObject $entityTypeManager;

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $transliteration = $this->createMock(PhpTransliteration::class);
    $transliteration->method('transliterate')->willReturnCallback(
      static fn(string $string): string => strtr($string, [
        'ç' => 'c',
        'Ç' => 'C',
        'ã' => 'a',
        'á' => 'a',
        'â' => 'a',
        'à' => 'a',
        'é' => 'e',
        'ê' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ú' => 'u',
        'ü' => 'u',
      ]),
    );
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->slugs = new LmsFriendlySlugs($this->entityTypeManager, $transliteration);
  }

  /**
 * Tests transliteration and normalization of slug text. */
  #[DataProvider('titles')]
  public function testSlugifyProducesPortugueseAsciiSlugs(string $title, string $expected): void {
    $this->assertSame($expected, $this->slugs->slugify($title));
  }

  /**
   * Provides representative Portuguese slug inputs. */
  public static function titles(): array {
    return [
      'accents and spaces' => ['Proibicionismo e antiproibicionismo', 'proibicionismo-e-antiproibicionismo'],
      'cedilla and tilde' => ['Ação e direitos', 'acao-e-direitos'],
      'punctuation collapses' => ['Ação & Direitos!', 'acao-direitos'],
      'empty after cleanup falls back' => ['!!!', 'sem-titulo'],
      'empty input falls back' => ['', 'sem-titulo'],
    ];
  }

  /**
   * Tests suffixes for repeated labels. */
  public function testRepeatedTitlesGetSuffixesInStoredOrder(): void {
    $this->assertSame(
      ['a', 'a-2', 'a-3'],
      array_values($this->slugs->uniqueSlugs(['A', 'a', 'A'])),
    );
  }

  /**
   * Tests suffix selection when a slug is already taken. */
  public function testSuffixSkipsSlugsAlreadyTaken(): void {
    $this->assertSame(
      ['x-2', 'x', 'x-3'],
      array_values($this->slugs->uniqueSlugs(['X 2', 'x', 'x'])),
    );
  }

  /**
   * Tests that keys are preserved when slugs are generated. */
  public function testUniqueSlugsKeepPositionKeys(): void {
    $this->assertSame(
      [0 => 'introducao', 2 => 'fontes'],
      $this->slugs->uniqueSlugs([0 => 'Introdução', 2 => 'Fontes']),
    );
  }

  /**
   * Tests lesson slugs from own lesson entities. */
  public function testLessonSlugsReadOwnEntitiesInWeightOrder(): void {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('5');
    $first = $this->contentEntity('aculta_lms_lesson', 11, 'Aula Um');
    $second = $this->contentEntity('aculta_lms_lesson', 12, 'Aula 2');
    $storage = $this->storage([11, 12], [11 => $first, 12 => $second]);
    $this->entityTypeManager->method('getStorage')
      ->with('aculta_lms_lesson')
      ->willReturn($storage);

    self::assertSame($second, $this->slugs->lessonAt($course, 1));
    self::assertSame('aula-um', $this->slugs->lessonSlug($course, 0));
    self::assertSame(1, $this->slugs->lessonDeltaFromSlug($course, 'aula-2'));
  }

  /**
   * Tests activity slugs from stable ACULTA activity types. */
  public function testActivitySlugsUseStableOwnActivityTypes(): void {
    $lesson = $this->createMock(ContentEntityInterface::class);
    $lesson->method('id')->willReturn('9');
    $activities = [
      21 => $this->contentEntity('aculta_lms_activity', 21, 'true_false'),
      22 => $this->contentEntity('aculta_lms_activity', 22, 'select'),
    ];
    $storage = $this->storage([21, 22], $activities);
    $this->entityTypeManager->method('getStorage')
      ->with('aculta_lms_activity')
      ->willReturn($storage);

    self::assertSame('select', $this->slugs->activitySlug($lesson, 1));
    self::assertSame(0, $this->slugs->activityDeltaFromSlug($lesson, 'true-false'));
  }

  /**
   * Creates storage and query doubles for ordered entity results. */
  /**
   * @param list<int> $ids
   *   Ordered entity IDs.
   * @param array<int, ContentEntityInterface> $entities
   *   Entities keyed by ID.
   *
   * @return EntityStorageInterface&MockObject
   *   Storage and query test double.
   */
  private function storage(array $ids, array $entities): EntityStorageInterface&MockObject {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn($ids);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with($ids)->willReturn($entities);
    return $storage;
  }

  /**
   * Creates a content entity double with one scalar field value. */
  private function contentEntity(string $entityTypeId, int $id, string $value): ContentEntityInterface {
    $fieldName = $entityTypeId === 'aculta_lms_lesson' ? 'title' : 'type';
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['value' => $value]]);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entityTypeId);
    $entity->method('id')->willReturn((string) $id);
    $entity->method('get')->with($fieldName)->willReturn($field);
    return $entity;
  }

}
