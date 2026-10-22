<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_magazine\ContentIndex\MagazineContentIndexProvider;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Magazine projection allowlist and revision boundary.
 *
 * @group aculta_magazine
 */
final class MagazineContentIndexProviderTest extends UnitTestCase {

  /**
   * Tests the provider projects current Revista metadata only.
   */
  public function testProjectsCurrentRevistaMetadata(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('isDefaultRevision')->willReturn(TRUE);
    $node->method('id')->willReturn('41');
    $node->method('language')->willReturn($language);
    $node->method('uuid')->willReturn('magazine-uuid');
    $node->method('label')->willReturn('Publicação de teste');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn(100);
    $node->method('getChangedTime')->willReturn(200);
    $node->method('hasField')->willReturn(FALSE);

    $projection = (new MagazineContentIndexProvider())->project($node);

    self::assertNotNull($projection);
    self::assertSame('node', $projection->entityTypeId);
    self::assertSame('41', $projection->entityId);
    self::assertSame('pt-br', $projection->langcode);
    self::assertSame('revista', $projection->bundle);
    self::assertSame('Publicação de teste', $projection->label);
    self::assertTrue($projection->published);
    self::assertSame([], $projection->relations);
  }

  /**
   * Tests cross-purpose access is revalidated in the Magazine domain.
   */
  public function testUsesCanonicalMagazineAccessPurpose(): void {
    $provider = new MagazineContentIndexProvider();
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');

    self::assertSame('magazine', $provider->getAccessPurpose($node));
    self::assertNull($provider->getAccessPurpose($this->createMock(ContentEntityInterface::class)));
  }

  /**
   * Tests other bundles and non-default revisions are excluded.
   *
   * @dataProvider unsupportedEntityProvider
   */
  public function testDoesNotProjectUnsupportedEntity(string $bundle, bool $defaultRevision): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('isDefaultRevision')->willReturn($defaultRevision);

    self::assertNull((new MagazineContentIndexProvider())->project($node));
  }

  /**
   * Tests only explicit activity and media fields become typed relations.
   */
  public function testProjectsExplicitActivityAndMediaRelations(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $relatedActivity = $this->referenceField(['activity-1', 'activity-2']);
    $editorialImage = $this->referenceField(['media-9']);
    $domain = $this->createMock(FieldItemListInterface::class);
    $domain->method('isEmpty')->willReturn(FALSE);
    $domain->method('__get')->with('target_id')->willReturn('coletivo420_aculta_org');
    $publishedAt = $this->createMock(FieldItemListInterface::class);
    $publishedAt->method('isEmpty')->willReturn(FALSE);
    $publishedAt->method('__get')->with('value')->willReturn('2026-04-01T00:00:00');
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('isDefaultRevision')->willReturn(TRUE);
    $node->method('id')->willReturn('41');
    $node->method('language')->willReturn($language);
    $node->method('uuid')->willReturn('magazine-uuid');
    $node->method('label')->willReturn('Publicação');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn(100);
    $node->method('getChangedTime')->willReturn(200);
    $node->method('hasField')->willReturnMap([
      ['field_domain_source', TRUE],
      ['field_published_at', TRUE],
      ['field_related_activity', TRUE],
      ['field_media_image', TRUE],
    ]);
    $node->method('get')->willReturnMap([
      ['field_domain_source', $domain],
      ['field_published_at', $publishedAt],
      ['field_related_activity', $relatedActivity],
      ['field_media_image', $editorialImage],
    ]);

    $projection = (new MagazineContentIndexProvider())->project($node);

    self::assertNotNull($projection);
    self::assertSame('coletivo420_aculta_org', $projection->domainId);
    self::assertSame(strtotime('2026-04-01T00:00:00 UTC'), $projection->publishedAt);
    self::assertSame([
      ['related_activity', 'node', 'activity-1'],
      ['related_activity', 'node', 'activity-2'],
      ['editorial_image', 'media', 'media-9'],
    ], array_map(
      static fn ($relation): array => [$relation->type, $relation->targetEntityTypeId, $relation->targetEntityId],
      $projection->relations,
    ));
  }

  /**
   * Provides unsupported entity conditions.
   *
   * @return array<string, array{0: string, 1: bool}>
   *   Bundle and default revision state.
   */
  public static function unsupportedEntityProvider(): array {
    return [
      'other bundle' => ['page', TRUE],
      'non-default revision' => ['revista', FALSE],
    ];
  }

  /**
   * Creates a simple reference field list for the provider test.
   *
   * @param list<string> $targetIds
   *   The referenced IDs.
   */
  private function referenceField(array $targetIds): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $items = array_map(static fn (string $id): object => (object) ['target_id' => $id], $targetIds);
    $field->method('getValue')->willReturn(array_map(static fn (object $item): array => ['target_id' => $item->target_id], $items));
    return $field;
  }

}
