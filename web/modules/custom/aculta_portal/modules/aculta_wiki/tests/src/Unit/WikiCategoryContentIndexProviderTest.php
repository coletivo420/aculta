<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_wiki\ContentIndex\WikiCategoryContentIndexProvider;
use Drupal\Core\Language\LanguageInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the allowlisted Wiki category term projection and access context.
 *
 * @group aculta_wiki
 */
final class WikiCategoryContentIndexProviderTest extends UnitTestCase {

  /**
   * Tests that only the Wiki vocabulary uses the canonical Wiki purpose.
   */
  public function testSupportsWikiCategoryTermsAndCanonicalPurpose(): void {
    $provider = new WikiCategoryContentIndexProvider();
    $wikiTerm = $this->createMock(TermInterface::class);
    $wikiTerm->method('bundle')->willReturn('wiki_category');
    $otherTerm = $this->createMock(TermInterface::class);
    $otherTerm->method('bundle')->willReturn('editorial_category');

    self::assertSame('wiki_category', $provider->getProviderId());
    self::assertSame(['taxonomy_term' => ['wiki_category']], $provider->getSupportedEntityTypes());
    self::assertSame('wiki', $provider->getAccessPurpose($wikiTerm));
    self::assertNull($provider->getAccessPurpose($otherTerm));
  }

  /**
   * Projects only the minimal identity and labels for a default Wiki term.
   */
  public function testProjectsMinimalDefaultRevision(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('wiki_category');
    $term->method('isDefaultRevision')->willReturn(TRUE);
    $term->method('id')->willReturn('31');
    $term->method('language')->willReturn($language);
    $term->method('uuid')->willReturn('wiki-category-uuid');
    $term->method('getName')->willReturn('Plantas medicinais');
    $term->method('isPublished')->willReturn(TRUE);
    $term->method('getRevisionCreationTime')->willReturn(100);
    $term->method('getChangedTime')->willReturn(200);

    $projection = (new WikiCategoryContentIndexProvider())->project($term);

    self::assertNotNull($projection);
    self::assertSame('taxonomy_term', $projection->entityTypeId);
    self::assertSame('31', $projection->entityId);
    self::assertSame('wiki_category', $projection->bundle);
    self::assertSame('pt-br', $projection->langcode);
    self::assertSame('Plantas medicinais', $projection->label);
    self::assertNull($projection->domainId);
    self::assertTrue($projection->published);
    self::assertSame(100, $projection->created);
    self::assertSame(200, $projection->changed);
    self::assertSame([], $projection->relations);
  }

  /**
   * Does not index other vocabularies or non-default revisions.
   */
  public function testDoesNotProjectUnsupportedOrNonDefaultTerms(): void {
    $provider = new WikiCategoryContentIndexProvider();
    $otherTerm = $this->createMock(TermInterface::class);
    $otherTerm->method('bundle')->willReturn('editorial_category');
    $wikiDraft = $this->createMock(TermInterface::class);
    $wikiDraft->method('bundle')->willReturn('wiki_category');
    $wikiDraft->method('isDefaultRevision')->willReturn(FALSE);

    self::assertNull($provider->project($otherTerm));
    self::assertNull($provider->project($wikiDraft));
  }

}
