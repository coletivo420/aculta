<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies the public search configuration and its Config Sync copy.
 */
#[Group('aculta_forum')]
final class ForumSearchConfigTest extends UnitTestCase {

  /**
   * Search stays scoped to public Forum topics with access checks enabled.
   */
  public function testIndexAndViewRemainPublicForumOnly(): void {
    $root = dirname(__DIR__, 3);
    $installDirectory = $root . '/config/install';
    $repositoryRoot = dirname($root, 6);
    $indexFile = 'search_api.index.aculta_forum.yml';
    $viewFile = 'views.view.aculta_forum_search.yml';
    $index = Yaml::parseFile($installDirectory . '/' . $indexFile);
    $view = Yaml::parseFile($installDirectory . '/' . $viewFile);

    self::assertSame($index, Yaml::parseFile($repositoryRoot . '/config/sync/' . $indexFile));
    self::assertSame($view, Yaml::parseFile($repositoryRoot . '/config/sync/' . $viewFile));
    self::assertSame(['aculta_forum_topic'], $index['datasource_settings']['entity:node']['bundles']['selected']);
    self::assertSame(['aculta_forum_post'], $index['datasource_settings']['entity:aculta_forum_post']['bundles']['selected']);
    self::assertSame('forum_search_category', $index['field_settings']['forum_search_category']['property_path']);
    self::assertSame('integer', $index['field_settings']['forum_search_category']['type']);
    self::assertSame(['aculta_forum_topic', 'aculta_forum_post'], $view['display']['default']['display_options']['filters']['type']['value']);
    self::assertSame('forum_search_type', $view['display']['default']['display_options']['filters']['type']['field']);
    self::assertSame('search_api_numeric', $view['display']['default']['display_options']['filters']['forum_category']['plugin_id']);
    self::assertSame('categoria', $view['display']['default']['display_options']['filters']['forum_category']['expose']['identifier']);
    self::assertSame('text_custom', $view['display']['default']['display_options']['empty']['area_text_custom']['plugin_id']);
    self::assertStringContainsString('Nenhuma discussão corresponde', $view['display']['default']['display_options']['empty']['area_text_custom']['content']);
    self::assertStringContainsString('href="/categorias"', $view['display']['default']['display_options']['empty']['area_text_custom']['content']);
    self::assertArrayHasKey('content_access', $index['processor_settings']);
    self::assertArrayHasKey('forum_post_search_access', $index['processor_settings']);
    self::assertArrayHasKey('entity_status', $index['processor_settings']);
    self::assertTrue($index['options']['index_directly']);
    self::assertSame('access content', $view['display']['default']['display_options']['access']['options']['perm']);
    self::assertSame('search_api_none', $view['display']['default']['display_options']['cache']['type']);
    self::assertArrayNotHasKey('skip_access', $view['display']['default']['display_options']['query']['options']);
    self::assertSame('pesquisar', $view['display']['page_search']['display_options']['path']);
    self::assertSame('forum_search_result', $view['display']['default']['display_options']['row']['options']['view_modes']['entity:aculta_forum_post'][':default']);
  }

}
