<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Rebuild\ContentIndexEntityEnumerator;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests bounded, translation-aware keyset discovery.
 *
 * @group aculta_index
 */
final class ContentIndexEntityEnumeratorTest extends UnitTestCase {

  /**
   * Tests entity IDs are scanned by cursor and projected once per language.
   */
  public function testNextBatchProjectsEveryTranslation(): void {
    $provider = $this->createMock(ContentIndexProviderInterface::class);
    $provider->method('getProviderId')->willReturn('magazine');
    $provider->method('getSupportedEntityTypes')->willReturn(['node' => ['revista']]);
    $provider->method('getProjectionVersion')->willReturn(1);
    $provider->method('project')->willReturnCallback(static function (ContentEntityInterface $entity): ContentIndexProjection {
      $langcode = $entity->language()->getId();
      return new ContentIndexProjection('node', '42', $langcode, 'revista', 'uuid-' . $langcode, 'Label', NULL, TRUE, 1, 2);
    });
    $registry = new ContentIndexProviderRegistry([$provider]);

    $language = $this->createMock(LanguageInterface::class);
    $translations = [];
    foreach (['pt-br', 'en'] as $langcode) {
      $translatedLanguage = clone $language;
      $translatedLanguage->method('getId')->willReturn($langcode);
      $translation = $this->createMock(ContentEntityInterface::class);
      $translation->method('language')->willReturn($translatedLanguage);
      $translations[$langcode] = $translation;
    }
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn('42');
    $entity->method('bundle')->willReturn('revista');
    $entity->method('getTranslationLanguages')->willReturn(['pt-br' => $language, 'en' => $language]);
    $entity->method('hasTranslation')->willReturn(TRUE);
    $entity->method('getTranslation')->willReturnCallback(static fn (string $langcode): ContentEntityInterface => $translations[$langcode]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $conditions = [];
    $query->method('condition')->willReturnCallback(static function (string $field, mixed $value, ?string $operator = NULL) use (&$conditions, $query): QueryInterface {
      $conditions[] = [$field, $value, $operator];
      return $query;
    });
    $query->method('sort')->with('nid')->willReturnSelf();
    $query->method('range')->with(0, 2)->willReturnSelf();
    $query->method('execute')->willReturn(['42']);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with(['42'])->willReturn(['42' => $entity]);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getKey')->willReturnMap([['id', 'nid'], ['bundle', 'type']]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $manager->method('getDefinition')->with('node')->willReturn($entityType);
    $manager->method('getStorage')->with('node')->willReturn($storage);

    $batch = (new ContentIndexEntityEnumerator($registry, $manager))->nextBatch('magazine', 'node', '41', 2);

    self::assertSame(['pt-br', 'en'], array_map(static fn (ContentIndexProjection $projection): string => $projection->langcode, $batch['projections']));
    self::assertSame([
      ['type', ['revista'], 'IN'],
      ['nid', '41', '>'],
    ], $conditions);
    self::assertSame('42', $batch['next_cursor']);
    self::assertTrue($batch['exhausted']);
  }

  /**
   * Tests requested batches are bounded.
   *
   * @dataProvider invalidLimitProvider
   */
  public function testRejectsInvalidBatchLimit(int $limit): void {
    $registry = new ContentIndexProviderRegistry();
    $manager = $this->createMock(EntityTypeManagerInterface::class);

    $this->expectException(\InvalidArgumentException::class);
    (new ContentIndexEntityEnumerator($registry, $manager))->nextBatch('missing', 'node', NULL, $limit);
  }

  /**
   * Provides invalid batch limits.
   *
   * @return array<string, array{0: int}>
   *   Invalid values.
   */
  public static function invalidLimitProvider(): array {
    return [
      'zero' => [0],
      'too large' => [501],
    ];
  }

}
