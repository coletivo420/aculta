<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal_sitemap\Unit;

use Drupal\aculta_portal_sitemap\Plugin\simple_sitemap\UrlGenerator\PurposeNodeUrlGenerator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Política de indexação por purpose (ADR-009), verificada pelas constantes do gerador.
 */
#[CoversClass(PurposeNodeUrlGenerator::class)]
#[Group('aculta_portal_sitemap')]
class IndexingPolicyTest extends UnitTestCase {

  /**
   * SHOP, FORUM e ACCOUNT nunca entram no sitemap.
   */
  public function testNonPublicPurposesAreNotIndexable(): void {
    foreach (['shop', 'forum', 'account'] as $purpose) {
      $this->assertNotContains($purpose, PurposeNodeUrlGenerator::INDEXABLE_PURPOSES, "$purpose não pode ser indexável.");
    }
  }

  /**
   * Toda raiz de purpose com URL própria pertence aos purposes indexáveis.
   */
  public function testRootPurposesAreIndexable(): void {
    $this->assertSame([], array_diff(PurposeNodeUrlGenerator::ROOT_PURPOSES, PurposeNodeUrlGenerator::INDEXABLE_PURPOSES));
  }

  /**
   * Caminhos públicos extras (como /contato) só são declarados para purposes indexáveis.
   */
  public function testPublicPathsBelongToIndexablePurposes(): void {
    $this->assertSame([], array_diff(array_keys(PurposeNodeUrlGenerator::PUBLIC_PATHS), PurposeNodeUrlGenerator::INDEXABLE_PURPOSES));
  }

  /**
   * A allowlist de bundles segue a matriz da ADR-009, sem bundles editoriais aposentados.
   */
  public function testBundleAllowlistMatchesPolicy(): void {
    $this->assertSame(['page', 'activity', 'document', 'wiki_entry', 'revista'], PurposeNodeUrlGenerator::BUNDLES);
    $this->assertNotContains('article', PurposeNodeUrlGenerator::BUNDLES);
    $this->assertNotContains('blog', PurposeNodeUrlGenerator::BUNDLES);
  }

  /**
   * O contato do MAIN é o único caminho extra, na paridade com o sitemap institucional anterior.
   */
  public function testMainContactPathIsTheOnlyExtraPath(): void {
    $this->assertSame(['main' => ['/contato']], PurposeNodeUrlGenerator::PUBLIC_PATHS);
  }

}
