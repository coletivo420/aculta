<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Garante que a listagem pública conta e pagina todos os verbetes.
 */
#[Group('aculta_wiki')]
final class WikiListingConfigTest extends UnitTestCase {

  /**
   * Loads the exported Verbetes view.
   *
   * @return array<string, mixed>
   *   The decoded view configuration.
   */
  private function view(): array {
    $root = dirname(__DIR__, 3);
    $repositoryRoot = dirname($root, 6);

    return Yaml::parseFile($repositoryRoot . '/config/sync/views.view.wiki_entries.yml');
  }

  /**
   * The page display paginates every entry instead of truncating at twelve.
   */
  public function testListingPagePaginatesAndCountsAllEntries(): void {
    $view = $this->view();
    $page = $view['display']['page_1']['display_options'];

    self::assertSame('wiki/verbetes', $page['path']);
    self::assertSame('full', $page['pager']['type']);
    self::assertSame(12, $page['pager']['options']['items_per_page']);
    self::assertFalse($page['pager']['options']['expose']['items_per_page']);
    self::assertSame('result', $page['header']['result']['plugin_id']);
    self::assertSame('@total verbetes publicados', $page['header']['result']['content']);
    self::assertTrue($page['header']['result']['empty']);
    self::assertContains('url.query_args', $view['display']['page_1']['cache_metadata']['contexts']);
  }

  /**
   * The blocks keep their fixed sizes and do not inherit the page pager.
   */
  public function testBlocksKeepTheirFixedSizes(): void {
    $view = $this->view();

    self::assertSame('some', $view['display']['default']['display_options']['pager']['type']);
    self::assertSame(12, $view['display']['default']['display_options']['pager']['options']['items_per_page']);
    self::assertSame(5, $view['display']['block_changes']['display_options']['pager']['options']['items_per_page']);
    self::assertArrayNotHasKey('pager', $view['display']['block_recent']['display_options']);
    self::assertArrayNotHasKey('header', $view['display']['default']['display_options']);
  }

}
