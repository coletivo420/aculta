<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Garante que o card da listagem traz imagem, resumo e categorias.
 */
#[Group('aculta_wiki')]
final class WikiTeaserConfigTest extends UnitTestCase {

  /**
   * Loads the exported teaser view mode of the Wiki entry.
   *
   * @return array<string, mixed>
   *   The decoded view display configuration.
   */
  private function teaser(): array {
    $root = dirname(__DIR__, 3);
    $repositoryRoot = dirname($root, 6);

    return Yaml::parseFile($repositoryRoot . '/config/sync/core.entity_view_display.node.wiki_entry.teaser.yml');
  }

  /**
   * The lead image renders through the media entity, without a link.
   */
  public function testTeaserRendersTheLeadImage(): void {
    $teaser = $this->teaser();
    $image = $teaser['content']['field_wiki_image'];

    self::assertSame('entity_reference_entity_view', $image['type']);
    self::assertSame('hidden', $image['label']);
    self::assertSame('default', $image['settings']['view_mode']);
    self::assertFalse($image['settings']['link']);
    self::assertArrayNotHasKey('field_wiki_image', $teaser['hidden']);
  }

  /**
   * Image, summary, and categories render in that order.
   */
  public function testTeaserOrderAndHiddenFields(): void {
    $teaser = $this->teaser();
    $image = $teaser['content']['field_wiki_image']['weight'];
    $summary = $teaser['content']['field_wiki_summary']['weight'];
    $categories = $teaser['content']['field_wiki_categories']['weight'];

    self::assertLessThan($summary, $image);
    self::assertLessThan($categories, $summary);
    self::assertTrue($teaser['hidden']['body']);
    self::assertTrue($teaser['hidden']['field_wiki_references']);
    self::assertTrue($teaser['hidden']['field_wiki_related']);
  }

}
