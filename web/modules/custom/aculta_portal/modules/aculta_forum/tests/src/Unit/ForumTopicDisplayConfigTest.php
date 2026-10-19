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
   * Administrative entries expose Forum topics and their separate comments.
   */
  public function testForumViewsAreDedicatedContentTabs(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $topics = Yaml::parseFile($module_path . '/config/install/views.view.aculta_forum_admin_topics.yml');
    $comments = Yaml::parseFile($module_path . '/config/install/views.view.aculta_forum_admin_comments.yml');
    $tasks = Yaml::parseFile($module_path . '/aculta_forum.links.task.yml');

    self::assertSame('none', $topics['display']['page_forum']['display_options']['menu']['type']);
    self::assertSame('none', $comments['display']['page_forum_comments']['display_options']['menu']['type']);
    self::assertSame('view.aculta_forum_admin_topics.page_forum', $tasks['aculta_forum.admin_topics']['route_name']);
    self::assertSame('system.admin_content', $tasks['aculta_forum.admin_topics']['base_route']);
    self::assertSame('view.aculta_forum_admin_comments.page_forum_comments', $tasks['aculta_forum.admin_comments']['route_name']);
    self::assertSame('Comentários e moderação', $tasks['aculta_forum.admin_comments']['title']);
    self::assertSame('aculta_forum.admin_topics', $tasks['aculta_forum.admin_comments']['parent_id']);
    self::assertSame('system.admin_content', $tasks['aculta_forum.admin_comments']['base_route']);

    $content = Yaml::parseFile($repository_path . '/config/sync/views.view.content.yml');
    $filters = $content['display']['default']['display_options']['filters'];
    self::assertSame('not in', $filters['forum_bundle_exclusion']['operator']);
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], $filters['forum_bundle_exclusion']['value']);
    self::assertFalse($filters['forum_bundle_exclusion']['exposed']);
    self::assertFileDoesNotExist($module_path . '/aculta_forum.links.menu.yml');
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
   * Public topics keep native replies open and use the shared Comment type.
   */
  public function testTopicRepliesUseTheOpenSharedCommentField(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $field_filename = 'field.field.node.aculta_forum_topic.field_aculta_discussion.yml';
    $field = Yaml::parseFile($module_path . '/config/install/' . $field_filename);
    $field_sync = Yaml::parseFile($repository_path . '/config/sync/' . $field_filename);
    unset($field_sync['_core']);
    self::assertSame($field, $field_sync);
    $storage_filename = 'field.storage.node.field_aculta_discussion.yml';
    $storage = Yaml::parseFile($module_path . '/config/install/' . $storage_filename);
    $storage_sync = Yaml::parseFile($repository_path . '/config/sync/' . $storage_filename);
    unset($storage_sync['_core']);
    self::assertSame($storage, $storage_sync);

    self::assertSame('field_aculta_discussion', $field['field_name']);
    self::assertSame('aculta_discussion_post', $storage['settings']['comment_type']);
    self::assertSame(2, $field['default_value'][0]['status'], 'New public topics allow replies.');
    self::assertSame(1, (int) $field['settings']['form_location'], 'Reply form appears inline.');
    self::assertSame(0, $field['settings']['anonymous']);

    $role = Yaml::parseFile($repository_path . '/config/sync/user.role.email_confirmed.yml');
    self::assertContains('post comments', $role['permissions']);
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
    self::assertSame('full', $options['pager']['type']);
    self::assertSame(20, $options['pager']['options']['items_per_page']);
    self::assertFalse($options['pager']['options']['expose']['items_per_page']);
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
   * The Forum admin listing excludes unrelated and legacy node content.
   */
  public function testAdministrativeForumListingIsScopedAndAccessAware(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_admin_topics.yml';
    $view = Yaml::parseFile($module_path . '/config/install/' . $filename);
    $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
    self::assertSame($view, $sync);

    $options = $view['display']['default']['display_options'];
    self::assertSame('access content overview', $options['access']['options']['perm']);
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], $options['filters']['type']['value']);
    self::assertFalse($options['filters']['type']['exposed']);
    self::assertFalse($options['query']['options']['disable_sql_rewrite']);
    self::assertArrayNotHasKey('node_bulk_form', $options['fields']);
    self::assertSame('field', $options['fields']['field_aculta_categories']['plugin_id']);
    self::assertSame('field_aculta_categories', $options['fields']['field_aculta_categories']['field']);
    self::assertSame('field_aculta_categories', $options['style']['options']['columns']['field_aculta_categories']);
    self::assertSame('entity_operations', $options['fields']['operations']['plugin_id']);
    self::assertSame('admin/content/forum', $view['display']['page_forum']['display_options']['path']);
    self::assertSame('none', $view['display']['page_forum']['display_options']['menu']['type']);
    self::assertContains('user.node_grants:view', $view['display']['default']['cache_metadata']['contexts']);
  }

  /**
   * Administrative Forum comments exclude editorial and other contexts.
   */
  public function testAdministrativeForumCommentsAreScopedAndModerationAware(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_admin_comments.yml';
    $view = Yaml::parseFile($module_path . '/config/install/' . $filename);
    $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
    self::assertSame($view, $sync);

    $options = $view['display']['default']['display_options'];
    self::assertSame('administer comments', $options['access']['options']['perm']);
    self::assertSame(['aculta_discussion_post' => 'aculta_discussion_post'], $options['filters']['comment_type']['value']);
    self::assertSame('field_aculta_discussion', $options['filters']['field_name']['value']);
    self::assertSame('node', $options['filters']['entity_type']['value']);
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], $options['filters']['type']['value']);
    self::assertSame('node', $options['filters']['type']['relationship']);
    self::assertFalse($options['query']['options']['disable_sql_rewrite']);
    self::assertArrayNotHasKey('comment_bulk_form', $options['fields']);
    self::assertSame('admin/content/forum/comentarios', $view['display']['page_forum_comments']['display_options']['path']);
    self::assertSame('none', $view['display']['page_forum_comments']['display_options']['menu']['type']);
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
   * The public topic alias pattern selects the replacement bundle only.
   */
  public function testPathautoPatternTargetsPublicForumTopics(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'pathauto.pattern.aculta_forum_topic.yml';
    $install = Yaml::parseFile($module_path . '/config/install/' . $filename);
    $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
    unset($sync['_core']);
    self::assertSame($install, $sync);
    self::assertSame('/topico/[node:title]', $install['pattern']);
    self::assertSame(
      ['aculta_forum_topic' => 'aculta_forum_topic'],
      $install['selection_criteria']['09a3b0e7-9f1c-4a86-a200-2d7a2a22bd32']['bundles'],
    );
    self::assertSame(
      ['node.type.aculta_forum_topic'],
      $install['dependencies']['config'],
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

  /**
   * Email-confirmed participants receive only own-topic Node permissions.
   */
  public function testEmailConfirmedRoleCanCreateAndManageOwnTopics(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $role = Yaml::parseFile($repository_path . '/config/sync/user.role.email_confirmed.yml');

    foreach ([
      'create aculta_forum_topic content',
      'edit own aculta_forum_topic content',
      'delete own aculta_forum_topic content',
    ] as $permission) {
      self::assertContains($permission, $role['permissions']);
    }
    self::assertNotContains('edit any aculta_forum_topic content', $role['permissions']);
    self::assertNotContains('delete any aculta_forum_topic content', $role['permissions']);
  }

}
