<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal_sitemap\Unit;

use Drupal\aculta_portal_sitemap\Plugin\simple_sitemap\UrlGenerator\PurposeNodeUrlGenerator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Checks purpose indexability against the generator constants (ADR-009).
 */
#[CoversClass(PurposeNodeUrlGenerator::class)]
#[Group('aculta_portal_sitemap')]
class IndexingPolicyTest extends UnitTestCase {

  /**
   * SHOP, FORUM e ACCOUNT nunca entram no sitemap.
   */
  public function testNonPublicPurposesAreNotIndexable(): void {
    foreach (['shop', 'forum', 'account'] as $purpose) {
      $this->assertNotContains(
        $purpose,
        PurposeNodeUrlGenerator::INDEXABLE_PURPOSES,
        "$purpose não pode ser indexável.",
      );
    }
  }

  /**
   * Toda raiz de purpose com URL própria pertence aos purposes indexáveis.
   */
  public function testRootPurposesAreIndexable(): void {
    $this->assertSame(
      [],
      array_diff(
        PurposeNodeUrlGenerator::ROOT_PURPOSES,
        PurposeNodeUrlGenerator::INDEXABLE_PURPOSES,
      ),
    );
  }

  /**
   * Extra public paths are declared only for indexable purposes.
   */
  public function testPublicPathsBelongToIndexablePurposes(): void {
    $this->assertSame(
      [],
      array_diff(
        array_keys(PurposeNodeUrlGenerator::PUBLIC_PATHS),
        PurposeNodeUrlGenerator::INDEXABLE_PURPOSES,
      ),
    );
  }

  /**
   * The bundle allowlist follows ADR-009 and excludes retired bundles.
   */
  public function testBundleAllowlistMatchesPolicy(): void {
    $this->assertSame(
      ['page', 'activity', 'document', 'wiki_entry', 'revista'],
      PurposeNodeUrlGenerator::BUNDLES,
    );
    $this->assertNotContains('article', PurposeNodeUrlGenerator::BUNDLES);
    $this->assertNotContains('blog', PurposeNodeUrlGenerator::BUNDLES);
  }

  /**
   * Extra MAIN and events paths match the public sitemaps.
   */
  public function testPublicPathsMatchTheirPurpose(): void {
    $this->assertSame(['/contato'], PurposeNodeUrlGenerator::PUBLIC_PATHS['main']);
    $this->assertSame(
      ['/eventos', '/calendario', '/locais', '/mapa'],
      PurposeNodeUrlGenerator::PUBLIC_PATHS['eventos'],
    );
  }

}
