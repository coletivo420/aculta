<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_wiki\ContentIndex\WikiContentIndexProvider;
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
   * Projects only selected metadata and leaves Wiki relations as deferred work.
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
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn(100);
    $node->method('getChangedTime')->willReturn(200);
    $node->method('hasField')->with('field_domain_source')->willReturn(TRUE);
    $node->method('get')->with('field_domain_source')->willReturn($domain);

    $projection = (new WikiContentIndexProvider())->project($node);

    self::assertNotNull($projection);
    self::assertSame('wiki', (new WikiContentIndexProvider())->getProviderId());
    self::assertSame(['node' => ['wiki_entry']], (new WikiContentIndexProvider())->getSupportedEntityTypes());
    self::assertSame('51', $projection->entityId);
    self::assertSame('pt-br', $projection->langcode);
    self::assertSame('wiki420_aculta_org', $projection->domainId);
    self::assertSame('Cannabis medicinal', $projection->label);
    self::assertTrue($projection->published);
    self::assertSame([], $projection->relations);
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
