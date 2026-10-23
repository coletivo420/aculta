<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Rebuild\ContentIndexEntityEnumerator;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

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
      return new ContentIndexProjection(
        'node',
        '42',
        $langcode,
        'revista',
        'uuid-' . $langcode,
        'Label',
        NULL,
        TRUE,
        1,
        2,
        NULL,
        [new ContentIndexRelation('editorial_image', 'media', 'media-9')],
      );
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
    $storage->expects(self::once())->method('resetCache')->with(['42']);
    $relatedStorage = $this->createMock(EntityStorageInterface::class);
    $relatedStorage->expects(self::once())->method('resetCache')->with(['media-9']);
    $nonContentStorage = $this->createMock(EntityStorageInterface::class);
    $nonContentStorage->expects(self::never())->method('resetCache');
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getKey')->willReturnMap([['id', 'nid'], ['bundle', 'type']]);
    $entityType->method('entityClassImplements')->with(ContentEntityInterface::class)->willReturn(TRUE);
    $relatedEntityType = $this->createMock(EntityTypeInterface::class);
    $relatedEntityType->method('entityClassImplements')->with(ContentEntityInterface::class)->willReturn(TRUE);
    $nonContentEntityType = $this->createMock(EntityTypeInterface::class);
    $nonContentEntityType->method('entityClassImplements')->with(ContentEntityInterface::class)->willReturn(FALSE);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->willReturnCallback(
      static fn (string $entityTypeId): bool => in_array($entityTypeId, ['node', 'media', 'file'], TRUE),
    );
    $manager->method('getDefinition')->willReturnCallback(static function (string $entityTypeId) use ($entityType, $relatedEntityType, $nonContentEntityType): EntityTypeInterface {
      return match ($entityTypeId) {
        'node' => $entityType,
        'media' => $relatedEntityType,
        'file' => $nonContentEntityType,
      };
    });
    $manager->method('getStorage')->willReturnCallback(static function (string $entityTypeId) use ($storage, $relatedStorage, $nonContentStorage): EntityStorageInterface {
      return match ($entityTypeId) {
        'node' => $storage,
        'media' => $relatedStorage,
        'file' => $nonContentStorage,
      };
    });
    $manager->method('getDefinitions')->willReturn([
      'node' => $entityType,
      'media' => $relatedEntityType,
      'file' => $nonContentEntityType,
    ]);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('debug')
      ->with(
        self::stringContains('Content Index enumeration batch completed.'),
        self::callback(static function (array $context): bool {
          self::assertSame('magazine', $context['@provider']);
          self::assertSame('node', $context['@entity_type']);
          self::assertSame(2, $context['@limit']);
          self::assertSame(1, $context['@source_count']);
          self::assertSame(2, $context['@projection_count']);
          self::assertArrayHasKey('@query_ms', $context);
          self::assertArrayHasKey('@load_ms', $context);
          self::assertArrayHasKey('@project_ms', $context);
          self::assertArrayHasKey('@cache_ms', $context);
          self::assertArrayHasKey('@total_ms', $context);
          self::assertFalse(array_key_exists('@entity_id', $context));
          self::assertFalse(array_key_exists('@title', $context));
          return TRUE;
        }),
      );
    $batch = (new ContentIndexEntityEnumerator($registry, $manager, $logger))->nextBatch('magazine', 'node', '41', 2);

    self::assertSame(['pt-br', 'en'], array_map(static fn (ContentIndexProjection $projection): string => $projection->langcode, $batch['projections']));
    self::assertSame([
      ['type', ['revista'], 'IN'],
      ['nid', '41', '>'],
    ], $conditions);
    self::assertSame('42', $batch['next_cursor']);
    self::assertTrue($batch['exhausted']);
  }

  /**
   * Fixed-bundle entity types without a bundle key use their entity ID.
   */
  public function testNextBatchSupportsProviderDeclaredFixedBundle(): void {
    $provider = $this->createMock(ContentIndexProviderInterface::class);
    $provider->method('getProviderId')->willReturn('forum');
    $provider->method('getProjectionVersion')->willReturn(1);
    $provider->method('getSupportedEntityTypes')->willReturn([
      'aculta_forum_post' => ['aculta_forum_post'],
    ]);
    $provider->method('project')->willReturn(new ContentIndexProjection(
      'aculta_forum_post',
      '42',
      'pt-br',
      'aculta_forum_post',
      'forum-post-42',
      'Reply',
      NULL,
      TRUE,
      1,
      2,
    ));
    $registry = new ContentIndexProviderRegistry([$provider]);

    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn('42');
    $entity->method('bundle')->willReturn('aculta_forum_post');
    $entity->method('language')->willReturn($language);
    $entity->method('getTranslationLanguages')->willReturn(['pt-br' => $language]);
    $entity->method('hasTranslation')->with('pt-br')->willReturn(TRUE);
    $entity->method('getTranslation')->with('pt-br')->willReturn($entity);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->expects(self::never())->method('condition');
    $query->method('sort')->with('id')->willReturnSelf();
    $query->method('range')->with(0, 10)->willReturnSelf();
    $query->method('execute')->willReturn(['42']);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with(['42'])->willReturn(['42' => $entity]);
    $storage->expects(self::once())->method('resetCache')->with(['42']);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getKey')->willReturnMap([['id', 'id'], ['bundle', '']]);
    $entityType->method('entityClassImplements')->with(ContentEntityInterface::class)->willReturn(TRUE);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with('aculta_forum_post')->willReturn(TRUE);
    $manager->method('getDefinition')->with('aculta_forum_post')->willReturn($entityType);
    $manager->method('getStorage')->with('aculta_forum_post')->willReturn($storage);
    $manager->method('getDefinitions')->willReturn(['aculta_forum_post' => $entityType]);

    $batch = (new ContentIndexEntityEnumerator($registry, $manager))
      ->nextBatch('forum', 'aculta_forum_post', NULL, 10);

    self::assertSame(['42'], array_map(
      static fn (ContentIndexProjection $projection): string => $projection->entityId,
      $batch['projections'],
    ));
    self::assertSame('42', $batch['next_cursor']);
  }

  /**
   * A keyless entity type cannot claim a different or multiple bundle IDs.
   */
  public function testKeylessEntityTypeRejectsNonCanonicalBundleDeclarations(): void {
    $provider = $this->createMock(ContentIndexProviderInterface::class);
    $provider->method('getProviderId')->willReturn('forum');
    $provider->method('getProjectionVersion')->willReturn(1);
    $provider->method('getSupportedEntityTypes')->willReturn([
      'aculta_forum_post' => ['other_bundle'],
    ]);
    $registry = new ContentIndexProviderRegistry([$provider]);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getKey')->willReturnMap([['id', 'id'], ['bundle', '']]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with('aculta_forum_post')->willReturn(TRUE);
    $manager->method('getDefinition')->with('aculta_forum_post')->willReturn($entityType);
    $manager->expects(self::never())->method('getStorage');

    $this->expectException(\LogicException::class);
    (new ContentIndexEntityEnumerator($registry, $manager))
      ->nextBatch('forum', 'aculta_forum_post');
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
   * Cache reset thresholds are positive and remain bounded by the default.
   *
   * @dataProvider invalidCacheResetEntityLimitProvider
   */
  public function testRejectsInvalidCacheResetEntityLimit(int $limit): void {
    $registry = new ContentIndexProviderRegistry();
    $manager = $this->createMock(EntityTypeManagerInterface::class);

    $this->expectException(\InvalidArgumentException::class);
    new ContentIndexEntityEnumerator($registry, $manager, NULL, $limit);
  }

  /**
   * Provides invalid cache reset thresholds.
   *
   * @return array<string, array{0: int}>
   *   Invalid values.
   */
  public static function invalidCacheResetEntityLimitProvider(): array {
    return [
      'zero' => [0],
      'above default maximum' => [5_001],
    ];
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
