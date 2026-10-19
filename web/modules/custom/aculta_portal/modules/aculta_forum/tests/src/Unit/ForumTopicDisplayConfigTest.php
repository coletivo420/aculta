<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the native form and display configuration for public forum topics.
 */
#[Group('aculta_forum')]
final class ForumTopicDisplayConfigTest extends UnitTestCase {

  /**
   * Install defaults and Config Sync stay byte-semantically equivalent.
   */
  public function testInstallAndSyncDisplaysAreEquivalent(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    foreach ([
      'core.entity_form_display.node.aculta_forum_topic.default.yml',
      'core.entity_view_display.node.aculta_forum_topic.full.yml',
      'core.entity_view_display.node.aculta_forum_topic.teaser.yml',
    ] as $filename) {
      $install = Yaml::parseFile($module_path . '/config/install/' . $filename);
      $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
      self::assertSame($install, $sync, $filename);
    }
  }

  /**
   * The field display does not expose comment settings or legacy author data.
   */
  public function testFormDoesNotExposeCommentStatusOrHistoricalAuthor(): void {
    $config = Yaml::parseFile(
      dirname(__DIR__, 3) . '/config/install/core.entity_form_display.node.aculta_forum_topic.default.yml',
    );

    self::assertSame(['body', 'field_aculta_categories', 'title'], array_keys($config['content']));
    self::assertArrayHasKey('field_aculta_discussion', $config['hidden']);
    self::assertArrayHasKey('field_autor_legado', $config['hidden']);
    foreach (['co_authors', 'moderation_state', 'path', 'simple_sitemap', 'url_redirects'] as $field) {
      self::assertArrayHasKey($field, $config['hidden'], $field);
    }
  }

  /**
   * Full topics render comments and teaser rows do not expose the thread.
   */
  public function testFullAndTeaserDisplaysKeepDiscussionContextual(): void {
    $module_path = dirname(__DIR__, 3);
    $full = Yaml::parseFile($module_path . '/config/install/core.entity_view_display.node.aculta_forum_topic.full.yml');
    $teaser = Yaml::parseFile($module_path . '/config/install/core.entity_view_display.node.aculta_forum_topic.teaser.yml');

    self::assertSame('comment_default', $full['content']['field_aculta_discussion']['type']);
    self::assertContains('comment', $full['dependencies']['module']);
    self::assertArrayHasKey('field_aculta_discussion', $teaser['hidden']);
    self::assertArrayHasKey('field_autor_legado', $teaser['hidden']);
  }

  /**
   * The public listing keeps bundle, domain and access filters fixed.
   */
  public function testPublicListingUsesFixedAccessAwareFilters(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $install_path = $module_path . '/config/install/';
    $filename = 'views.view.aculta_forum_public_topics.yml';
    $view = Yaml::parseFile($install_path . $filename);
    $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
    self::assertSame($view, $sync);

    $options = $view['display']['default']['display_options'];
    self::assertSame('access content', $options['access']['options']['perm']);
    self::assertSame('1', $options['filters']['status']['value']);
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], $options['filters']['type']['value']);
    self::assertSame('domain_source', $options['filters']['field_domain_source_target_id']['plugin_id']);
    self::assertSame(['_active' => '_active'], $options['filters']['field_domain_source_target_id']['value']);
    $category_filter = $options['filters']['field_aculta_categories_target_id'];
    self::assertSame('node__field_aculta_categories', $category_filter['table']);
    self::assertSame('taxonomy_index_tid', $category_filter['plugin_id']);
    self::assertTrue($category_filter['exposed']);
    self::assertSame('categoria', $category_filter['expose']['identifier']);
    self::assertSame('aculta_forum_categories', $category_filter['vid']);
    self::assertTrue($category_filter['hierarchy']);
    self::assertFalse($options['query']['options']['disable_sql_rewrite']);
    self::assertContains('user.node_grants:view', $view['display']['default']['cache_metadata']['contexts']);
    self::assertSame(
      ['items_per_page' => TRUE, 'pager' => 'pager'],
      $view['display']['block_recent']['display_options']['allow'],
    );
    self::assertSame(
      'text_custom',
      $options['empty']['area_text_custom']['plugin_id'],
    );
    self::assertTrue($options['empty']['area_text_custom']['empty']);
    self::assertSame(
      'Ainda não há tópicos publicados nesta lista.',
      $options['empty']['area_text_custom']['content'],
    );
    self::assertSame('discussoes', $view['display']['page_discussions']['display_options']['path']);
    self::assertSame('none', $view['display']['page_discussions']['display_options']['menu']['type']);
    self::assertFalse($view['display']['page_discussions']['display_options']['defaults']['title']);
    self::assertContains('url.query_args', $view['display']['page_discussions']['cache_metadata']['contexts']);
  }

  /**
   * Domain Source field configuration is shipped by the Forum module.
   */
  public function testDomainSourceFieldIsDeclaredForPublicTopics(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'field.field.node.aculta_forum_topic.field_domain_source.yml';
    self::assertSame(
      Yaml::parseFile($module_path . '/config/install/' . $filename),
      Yaml::parseFile($repository_path . '/config/sync/' . $filename),
    );
  }

  /**
   * Friendly topic creation uses Core's Node form and create access.
   */
  public function testFriendlyTopicAddRouteUsesCoreEntityFormAccess(): void {
    $route = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_forum.routing.yml')['aculta_forum.topic_add'];

    self::assertSame('/criar-topico', $route['path']);
    self::assertSame('node.default', $route['defaults']['_entity_form']);
    self::assertSame('aculta_forum_topic', $route['defaults']['node_type']);
    self::assertSame('node:{node_type}', $route['requirements']['_entity_create_access']);
  }

}
