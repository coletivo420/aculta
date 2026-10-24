<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_wiki\ContentIndex\WikiContentIndexProvider;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Wiki provider metadata allowlist and default-revision boundary.
 *
 * @group aculta_wiki
 */
final class WikiContentIndexProviderTest extends UnitTestCase {

  /**
   * Tests cross-purpose access is revalidated in the Wiki domain.
   */
  public function testUsesCanonicalWikiAccessPurpose(): void {
    $provider = new WikiContentIndexProvider();
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('wiki_entry');

    self::assertSame('wiki', $provider->getAccessPurpose($node));
    self::assertNull($provider->getAccessPurpose($this->createMock(ContentEntityInterface::class)));
  }

  /**
   * Projects selected metadata without a related-entry field value.
   */
  public function testProjectsAllowlistedEntryMetadataWithoutRelations(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $domain = $this->createMock(FieldItemListInterface::class);
    $domain->method('isEmpty')->willReturn(FALSE);
    $domain->method('__get')->with('target_id')->willReturn('wiki420_aculta_org');
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('wiki_entry');
    $node->method('isDefaultRevision')->willReturn(TRUE);
    $node->method('id')->willReturn('51');
    $node->method('language')->willReturn($language);
    $node->method('uuid')->willReturn('wiki-entry-uuid');
    $node->method('label')->willReturn('Cannabis medicinal');
    $node->method('getOwnerId')->willReturn('777');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn(100);
    $node->method('getChangedTime')->willReturn(200);
    $emptyRelations = $this->createMock(FieldItemListInterface::class);
    $emptyRelations->method('isEmpty')->willReturn(TRUE);
    $node->method('hasField')->willReturnMap([
      ['field_domain_source', TRUE],
      ['field_wiki_related', TRUE],
      ['field_wiki_categories', FALSE],
      ['field_wiki_image', FALSE],
    ]);
    $node->method('get')->willReturnMap([
      ['field_domain_source', $domain],
      ['field_wiki_related', $emptyRelations],
    ]);

    $projection = (new WikiContentIndexProvider())->project($node);

    self::assertNotNull($projection);
    self::assertSame('wiki', (new WikiContentIndexProvider())->getProviderId());
    self::assertSame(4, (new WikiContentIndexProvider())->getProjectionVersion());
    self::assertSame(['node' => ['wiki_entry']], (new WikiContentIndexProvider())->getSupportedEntityTypes());
    self::assertSame('51', $projection->entityId);
    self::assertSame('pt-br', $projection->langcode);
    self::assertSame('wiki420_aculta_org', $projection->domainId);
    self::assertSame('Cannabis medicinal', $projection->label);
    self::assertNull($projection->ownerUid, 'The source author is not part of the Wiki Index projection.');
    self::assertTrue($projection->published);
    self::assertSame([], $projection->relations);
  }

  /**
   * Projects only explicit Wiki entry references as outgoing links.
   */
  public function testProjectsNamedOutgoingRelatedEntryRelations(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $relations = $this->createMock(FieldItemListInterface::class);
    $relations->method('isEmpty')->willReturn(FALSE);
    $relations->method('getValue')->willReturn([
      ['target_id' => '71'],
      ['target_id' => '72'],
      ['target_id' => '71'],
    ]);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('wiki_entry');
    $node->method('isDefaultRevision')->willReturn(TRUE);
    $node->method('id')->willReturn('51');
    $node->method('language')->willReturn($language);
    $node->method('uuid')->willReturn('wiki-entry-uuid');
    $node->method('label')->willReturn('Cannabis medicinal');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn(100);
    $node->method('getChangedTime')->willReturn(200);
    $node->method('hasField')->willReturnMap([
      ['field_domain_source', FALSE],
      ['field_wiki_related', TRUE],
      ['field_wiki_categories', FALSE],
      ['field_wiki_image', FALSE],
    ]);
    $node->method('get')->with('field_wiki_related')->willReturn($relations);

    $projection = (new WikiContentIndexProvider())->project($node);

    self::assertNotNull($projection);
    self::assertSame([
      ['related_entry', 'node', '71'],
      ['related_entry', 'node', '72'],
    ], array_map(
      static fn ($relation): array => [$relation->type, $relation->targetEntityTypeId, $relation->targetEntityId],
      $projection->relations,
    ));
  }

  /**
   * Projects category and image fields as named outgoing relations.
   */
  public function testProjectsWikiCategoryAndImageRelations(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $categories = $this->createMock(FieldItemListInterface::class);
    $categories->method('isEmpty')->willReturn(FALSE);
    $categories->method('getValue')->willReturn([
      ['target_id' => '31'],
      ['target_id' => '32'],
      ['target_id' => '31'],
    ]);
    $image = $this->createMock(FieldItemListInterface::class);
    $image->method('isEmpty')->willReturn(FALSE);
    $image->method('getValue')->willReturn([['target_id' => '91']]);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('wiki_entry');
    $node->method('isDefaultRevision')->willReturn(TRUE);
    $node->method('id')->willReturn('51');
    $node->method('language')->willReturn($language);
    $node->method('uuid')->willReturn('wiki-entry-uuid');
    $node->method('label')->willReturn('Cannabis medicinal');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn(100);
    $node->method('getChangedTime')->willReturn(200);
    $node->method('hasField')->willReturnMap([
      ['field_domain_source', FALSE],
      ['field_wiki_related', FALSE],
      ['field_wiki_categories', TRUE],
      ['field_wiki_image', TRUE],
    ]);
    $node->method('get')->willReturnMap([
      ['field_wiki_categories', $categories],
      ['field_wiki_image', $image],
    ]);

    $projection = (new WikiContentIndexProvider())->project($node);

    self::assertNotNull($projection);
    self::assertSame([
      ['wiki_category', 'taxonomy_term', '31'],
      ['wiki_category', 'taxonomy_term', '32'],
      ['wiki_image', 'media', '91'],
    ], array_map(
      static fn ($relation): array => [$relation->type, $relation->targetEntityTypeId, $relation->targetEntityId],
      $projection->relations,
    ));
  }

  /**
   * Does not project unsupported bundles or non-default revisions.
   *
   * @dataProvider unsupportedEntityProvider
   */
  public function testDoesNotProjectUnsupportedEntity(string $bundle, bool $defaultRevision): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('isDefaultRevision')->willReturn($defaultRevision);

    self::assertNull((new WikiContentIndexProvider())->project($node));
  }

  /**
   * Provides unsupported entity cases.
   *
   * @return array<string, array{0: string, 1: bool}>
   *   Bundle and default-revision state.
   */
  public static function unsupportedEntityProvider(): array {
    return [
      'other bundle' => ['article', TRUE],
      'non-default revision' => ['wiki_entry', FALSE],
    ];
  }

}
