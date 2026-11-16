<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Plugin\Action\MoveForumTopicsAction;
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
      $install = self::parseConfigFile($module_path . '/config/install/' . $filename);
      $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
      self::assertConfigSame($install, $sync, $filename);
    }
  }

  /**
   * The Forum has its own admin section, separate from Content.
   */
  public function testForumAdminToolsAreFirstLevelPagesUnderCanonicalSection(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $topics = self::parseConfigFile($module_path . '/config/install/views.view.aculta_forum_admin_topics.yml');
    $comments = self::parseConfigFile($module_path . '/config/install/views.view.aculta_forum_admin_comments.yml');
    $tasks = self::parseConfigFile($module_path . '/aculta_forum.links.task.yml');
    $menu = self::parseConfigFile($module_path . '/aculta_forum.links.menu.yml');
    $routes = self::parseConfigFile($module_path . '/aculta_forum.routing.yml');

    self::assertSame(['default', 'page_forum'], array_keys(self::configArray($topics, 'display')));
    self::assertSame(
      ['default', 'page_forum_comments', 'page_pending', 'page_published', 'page_rejected'],
      array_keys(self::configArray($comments, 'display')),
    );
    self::assertSame('painel-administrativo/forum/conteudo', self::configValue($topics, 'display', 'page_forum', 'display_options', 'path'));
    self::assertSame('painel-administrativo/forum/comentarios', self::configValue($comments, 'display', 'page_forum_comments', 'display_options', 'path'));
    self::assertSame('painel-administrativo/forum/comentarios/publicados', self::configValue($comments, 'display', 'page_published', 'display_options', 'path'));
    self::assertSame('painel-administrativo/forum/comentarios/aguardando-moderacao', self::configValue($comments, 'display', 'page_pending', 'display_options', 'path'));
    self::assertSame('painel-administrativo/forum/comentarios/rejeitados', self::configValue($comments, 'display', 'page_rejected', 'display_options', 'path'));
    self::assertSame('aculta_forum.admin_comments_tab', self::configValue($tasks, 'aculta_forum.admin_comments_published', 'parent_id'));
    self::assertSame('view.aculta_forum_admin_comments.page_published', self::configValue($tasks, 'aculta_forum.admin_comments_published', 'route_name'));
    foreach ([
      'admin_overview_tab' => 'aculta_forum.admin',
      'admin_topics_tab' => 'view.aculta_forum_admin_topics.page_forum',
      'admin_comments_tab' => 'view.aculta_forum_admin_comments.page_forum_comments',
      'admin_settings_tab' => 'aculta_forum.settings',
    ] as $task => $route_name) {
      self::assertSame($route_name, self::configValue($tasks, "aculta_forum.$task", 'route_name'));
      self::assertSame('aculta_forum.admin', self::configValue($tasks, "aculta_forum.$task", 'base_route'));
    }
    self::assertSame('aculta_forum.admin', self::configValue($menu, 'aculta_forum.admin', 'route_name'));
    self::assertSame('system.admin', self::configValue($menu, 'aculta_forum.admin', 'parent'));
    self::assertSame(
      [
        'aculta_forum.admin',
        'aculta_forum.admin_topics',
        'aculta_forum.admin_comments',
        'aculta_forum.admin_revisionable_posts',
        'aculta_forum.admin_settings',
      ],
      array_keys($menu),
    );
    self::assertSame('aculta_forum.admin', self::configValue($menu, 'aculta_forum.admin_topics', 'parent'));
    self::assertSame('view.aculta_forum_admin_topics.page_forum', self::configValue($menu, 'aculta_forum.admin_topics', 'route_name'));
    self::assertSame('aculta_forum.admin', self::configValue($menu, 'aculta_forum.admin_comments', 'parent'));
    self::assertSame('view.aculta_forum_admin_comments.page_forum_comments', self::configValue($menu, 'aculta_forum.admin_comments', 'route_name'));
    self::assertSame('aculta_forum.admin', self::configValue($menu, 'aculta_forum.admin_settings', 'parent'));
    self::assertSame('aculta_forum.settings', self::configValue($menu, 'aculta_forum.admin_settings', 'route_name'));
    self::assertSame('/painel-administrativo/forum', self::configValue($routes, 'aculta_forum.admin', 'path'));
    self::assertSame('access content overview', self::configValue($routes, 'aculta_forum.admin', 'requirements', '_permission'));
    self::assertArrayNotHasKey('aculta_forum.admin_content_redirect', $routes);
    self::assertArrayNotHasKey('aculta_forum.admin_comments_redirect', $routes);
    // Moving a topic has a canonical confirmation form, linked from the topic
    // operations; the former placeholder redirect is gone.
    self::assertSame('/painel-administrativo/forum/{node}/mover-categoria', self::configValue($routes, 'aculta_forum.topic_move', 'path'));
    self::assertSame('Mover tópico de categoria', self::configValue($routes, 'aculta_forum.topic_move', 'defaults', '_title'));
    self::assertSame('\Drupal\aculta_forum\Form\ForumTopicMoveForm', self::configValue($routes, 'aculta_forum.topic_move', 'defaults', '_form'));
    self::assertSame('move aculta forum topics', self::configValue($routes, 'aculta_forum.topic_move', 'requirements', '_permission'));
    self::assertSame('entity:node', self::configValue($routes, 'aculta_forum.topic_move', 'options', 'parameters', 'node', 'type'));
    self::assertSame(['GET', 'HEAD', 'POST'], self::configValue($routes, 'aculta_forum.topic_move', 'methods'));
    self::assertSame('/painel-administrativo/forum/comentarios/{comment}/mover', self::configValue($routes, 'aculta_forum.comment_move', 'path'));
    self::assertSame('Mover comentário do fórum', self::configValue($routes, 'aculta_forum.comment_move', 'defaults', '_title'));
    self::assertSame('\Drupal\aculta_forum\Form\ForumCommentMoveForm', self::configValue($routes, 'aculta_forum.comment_move', 'defaults', '_form'));
    self::assertSame('entity:comment', self::configValue($routes, 'aculta_forum.comment_move', 'options', 'parameters', 'comment', 'type'));
    // Temporary legacy aliases were removed by the route cleanup; only the
    // canonical administrative paths remain.
    foreach ([
      'aculta_forum.comment_move_legacy_redirect',
      'aculta_forum.legacy_admin_redirect',
      'aculta_forum.legacy_admin_topic_move_redirect',
      'aculta_forum.legacy_admin_comment_move_redirect',
      'aculta_forum.legacy_admin_comments_redirect',
      'aculta_forum.legacy_admin_comments_published_redirect',
      'aculta_forum.legacy_admin_comments_rejected_redirect',
      'aculta_forum.legacy_admin_comments_pending_redirect',
    ] as $alias) {
      self::assertArrayNotHasKey($alias, $routes);
    }

    $content = self::parseConfigFile($repository_path . '/config/sync/views.view.content.yml');
    $filters = self::configValue($content, 'display', 'default', 'display_options', 'filters');
    self::assertSame('not in', self::configValue($filters, 'forum_bundle_exclusion', 'operator'));
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], self::configValue($filters, 'forum_bundle_exclusion', 'value'));
    self::assertFalse(self::configValue($filters, 'forum_bundle_exclusion', 'exposed'));
  }

  /**
   * Forum-only settings use a dedicated permission and remain fail-closed.
   */
  public function testForumSettingsAdministrationIsDedicatedAndFailClosed(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $routes = self::parseConfigFile($module_path . '/aculta_forum.routing.yml');
    $menu = self::parseConfigFile($module_path . '/aculta_forum.links.menu.yml');
    $permissions = self::parseConfigFile($module_path . '/aculta_forum.permissions.yml');
    $install = self::parseConfigFile($module_path . '/config/install/aculta_forum.settings.yml');
    $sync = self::parseConfigFile($repository_path . '/config/sync/aculta_forum.settings.yml');
    $editor = self::parseConfigFile($repository_path . '/config/sync/user.role.content_editor.yml');
    $form = self::readSource($module_path . '/src/Form/ForumSettingsForm.php');

    self::assertSame('/painel-administrativo/forum/configuracoes', self::configValue($routes, 'aculta_forum.settings', 'path'));
    self::assertSame('\Drupal\aculta_forum\Form\ForumSettingsForm', self::configValue($routes, 'aculta_forum.settings', 'defaults', '_form'));
    self::assertSame('administer aculta forum settings', self::configValue($routes, 'aculta_forum.settings', 'requirements', '_permission'));
    self::assertSame(['GET', 'HEAD', 'POST'], self::configValue($routes, 'aculta_forum.settings', 'methods'));
    self::assertTrue(self::configValue($permissions, 'administer aculta forum settings', 'restrict access'));
    self::assertSame('aculta_forum.admin', self::configValue($menu, 'aculta_forum.admin_settings', 'parent'));
    self::assertFalse(self::configValue($install, 'comment_move_enabled'));
    self::assertConfigSame($install, $sync);
    self::assertNotContains('administer aculta forum settings', self::configArray($editor, 'permissions'));
    self::assertStringContainsString("'#config_target' => 'aculta_forum.settings:comment_move_enabled'", $form);
    self::assertStringContainsString('acervo histórico nunca são movidos', $form);
    self::assertStringNotContainsString('\\Drupal::', $form);
  }

  /**
   * The public topic View consumes only the Core latest commenter account ID.
   */
  public function testPublicTopicViewUsesHiddenCoreLatestCommenterField(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $install = self::parseConfigFile($module_path . '/config/install/views.view.aculta_forum_public_topics.yml');
    $sync = self::parseConfigFile($repository_path . '/config/sync/views.view.aculta_forum_public_topics.yml');
    $field = self::configValue($install, 'display', 'default', 'display_options', 'fields', 'last_comment_uid');

    self::assertSame($field, self::configValue($sync, 'display', 'default', 'display_options', 'fields', 'last_comment_uid'));
    self::assertSame('comment_entity_statistics', self::configValue($field, 'table'));
    self::assertSame('last_comment_uid', self::configValue($field, 'field'));
    self::assertTrue(self::configValue($field, 'exclude'), 'The account ID is a render-only input, never a visible View field.');
  }

  /**
   * The field display does not expose comment settings or legacy author data.
   */
  public function testFormDoesNotExposeCommentStatusOrCoAuthorWidget(): void {
    $config = self::parseConfigFile(
      dirname(__DIR__, 3) . '/config/install/core.entity_form_display.node.aculta_forum_topic.default.yml',
    );

    self::assertSame(['body', 'field_aculta_categories', 'title'], array_keys(self::configArray($config, 'content')));
    self::assertArrayHasKey('field_aculta_discussion', self::configArray($config, 'hidden'));
    self::assertArrayHasKey('field_autor_legado', self::configArray($config, 'hidden'));
    self::assertArrayNotHasKey('co_authors', self::configArray($config, 'hidden'));
    self::assertTrue(self::configValue($config, 'hidden', 'aculta_coauthors'));
    foreach (['moderation_state', 'path', 'simple_sitemap', 'url_redirects'] as $field) {
      self::assertArrayHasKey($field, self::configArray($config, 'hidden'), $field);
    }
  }

  /**
   * Full topics render comments and teaser rows do not expose the thread.
   */
  public function testFullAndTeaserDisplaysKeepDiscussionContextual(): void {
    $module_path = dirname(__DIR__, 3);
    $full = self::parseConfigFile($module_path . '/config/install/core.entity_view_display.node.aculta_forum_topic.full.yml');
    $teaser = self::parseConfigFile($module_path . '/config/install/core.entity_view_display.node.aculta_forum_topic.teaser.yml');

    self::assertSame('comment_default', self::configValue($full, 'content', 'field_aculta_discussion', 'type'));
    self::assertContains('comment', self::configArray($full, 'dependencies', 'module'));
    self::assertArrayHasKey('field_aculta_discussion', self::configArray($teaser, 'hidden'));
    self::assertArrayHasKey('field_autor_legado', self::configArray($teaser, 'hidden'));
  }

  /**
   * Public topics keep native replies open and use the shared Comment type.
   */
  public function testTopicRepliesUseTheOpenSharedCommentField(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $field_filename = 'field.field.node.aculta_forum_topic.field_aculta_discussion.yml';
    $field = self::parseConfigFile($module_path . '/config/install/' . $field_filename);
    $field_sync = self::parseConfigFile($repository_path . '/config/sync/' . $field_filename);
    unset($field_sync['_core']);
    self::assertSame($field, $field_sync);
    $storage_filename = 'field.storage.node.field_aculta_discussion.yml';
    $storage = self::parseConfigFile($module_path . '/config/install/' . $storage_filename);
    $storage_sync = self::parseConfigFile($repository_path . '/config/sync/' . $storage_filename);
    unset($storage_sync['_core']);
    self::assertSame($storage, $storage_sync);

    self::assertSame('field_aculta_discussion', self::configValue($field, 'field_name'));
    self::assertSame('aculta_discussion_post', self::configValue($storage, 'settings', 'comment_type'));
    self::assertSame(2, self::configValue($field, 'default_value', 0, 'status'), 'New public topics allow replies.');
    self::assertTrue(self::configBoolean($field, 'settings', 'form_location'), 'Reply form appears inline.');
    self::assertSame(0, self::configValue($field, 'settings', 'anonymous'));

    $role = self::parseConfigFile($repository_path . '/config/sync/user.role.email_confirmed.yml');
    self::assertContains('post comments', self::configArray($role, 'permissions'));
  }

  /**
   * Forum replies use the dedicated Forum attachment format and Media policy.
   */
  public function testForumCommentAttachmentsUseDedicatedForumPolicies(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $field_filename = 'field.field.comment.aculta_discussion_post.comment_body.yml';
    $field = self::parseConfigFile($module_path . '/config/install/' . $field_filename);
    $field_sync = self::parseConfigFile($repository_path . '/config/sync/' . $field_filename);
    unset($field_sync['_core']);

    $format = self::parseConfigFile($module_path . '/config/install/filter.format.forum.yml');
    $format_sync = self::parseConfigFile($repository_path . '/config/sync/filter.format.forum.yml');
    $editor = self::parseConfigFile($module_path . '/config/install/editor.editor.forum.yml');
    $editor_sync = self::parseConfigFile($repository_path . '/config/sync/editor.editor.forum.yml');
    unset($format_sync['_core'], $editor_sync['_core']);

    self::assertConfigSame($field, $field_sync);
    self::assertConfigSame($format, $format_sync);
    self::assertConfigSame($editor, $editor_sync);
    self::assertSame(['forum' => 'forum'], self::configValue($field, 'settings', 'allowed_formats'));
    self::assertContains('filter.format.forum', self::configArray($field, 'dependencies', 'config'));
    self::assertSame('forum', self::configValue($format, 'format'));
    self::assertTrue(self::configValue($format, 'filters', 'media_embed', 'status'));
    self::assertSame(
      ['forum_attachment' => 'forum_attachment'],
      self::configValue($format, 'filters', 'media_embed', 'settings', 'allowed_media_types'),
      'Participant comments may embed only Forum attachment media.',
    );
    self::assertContains('acultaForumAttachment', self::configArray($editor, 'settings', 'toolbar', 'items'));
    self::assertContains('acultaForumGallery', self::configArray($editor, 'settings', 'toolbar', 'items'));
    self::assertFalse(self::configValue($editor, 'image_upload', 'status'), 'Forum uploads go through the centralized attachment policy.');
    self::assertSame(['status' => FALSE], self::configValue($editor, 'image_upload'), 'Disabled uploads keep only the schema-valid key.');
  }

  /**
   * Follow and save flags target only the current public topic bundle.
   */
  public function testTopicFlagsTargetCurrentPublicBundle(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    foreach (['seguir_topico', 'salvar_topico'] as $id) {
      $filename = 'flag.flag.' . $id . '.yml';
      $install = self::parseConfigFile($module_path . '/config/install/' . $filename);
      $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
      self::assertConfigSame($install, $sync, $filename);
      self::assertSame(['aculta_forum_topic'], self::configValue($install, 'bundles'));
      self::assertFalse(self::configValue($install, 'global'));
      self::assertSame('node', self::configValue($install, 'entity_type'));
    }
  }

  /**
   * Follow and save controls are placed only on the full topic page.
   */
  public function testTopicFullDisplayIncludesFollowAndSaveControls(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'core.entity_view_display.node.aculta_forum_topic.full.yml';
    $install = self::parseConfigFile($module_path . '/config/install/' . $filename);
    $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
    self::assertConfigSame($install, $sync);
    self::assertContains('flag', self::configArray($install, 'dependencies', 'module'));

    foreach (['flag_seguir_topico' => 20, 'flag_salvar_topico' => 21] as $key => $weight) {
      self::assertSame([
        'weight' => $weight,
        'region' => 'content',
        'settings' => [],
        'third_party_settings' => [],
      ], self::configValue($install, 'content', $key));
    }
    $teaser = self::parseConfigFile($module_path . '/config/install/core.entity_view_display.node.aculta_forum_topic.teaser.yml');
    $teaser_sync = self::parseConfigFile($repository_path . '/config/sync/core.entity_view_display.node.aculta_forum_topic.teaser.yml');
    self::assertSame($teaser, $teaser_sync);
    self::assertArrayNotHasKey('flag_seguir_topico', self::configArray($teaser, 'content'));
    self::assertArrayNotHasKey('flag_salvar_topico', self::configArray($teaser, 'content'));
    self::assertTrue(self::configValue($teaser, 'hidden', 'flag_seguir_topico'));
    self::assertTrue(self::configValue($teaser, 'hidden', 'flag_salvar_topico'));
  }

  /**
   * The public listing keeps bundle, domain and access filters fixed.
   */
  public function testPublicListingUsesFixedAccessAwareFilters(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $install_path = $module_path . '/config/install/';
    $filename = 'views.view.aculta_forum_public_topics.yml';
    $view = self::parseConfigFile($install_path . $filename);
    $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
    self::assertConfigSame($view, $sync);

    $options = self::configValue($view, 'display', 'default', 'display_options');
    self::assertSame('full', self::configValue($options, 'pager', 'type'));
    self::assertSame(20, self::configValue($options, 'pager', 'options', 'items_per_page'));
    self::assertFalse(self::configValue($options, 'pager', 'options', 'expose', 'items_per_page'));
    self::assertSame('access content', self::configValue($options, 'access', 'options', 'perm'));
    self::assertSame('1', self::configValue($options, 'filters', 'status', 'value'));
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], self::configValue($options, 'filters', 'type', 'value'));
    self::assertSame('domain_source', self::configValue($options, 'filters', 'field_domain_source_target_id', 'plugin_id'));
    self::assertSame(['_active' => '_active'], self::configValue($options, 'filters', 'field_domain_source_target_id', 'value'));
    $category_filter = self::configValue($options, 'filters', 'field_aculta_categories_target_id');
    self::assertSame('node__field_aculta_categories', self::configValue($category_filter, 'table'));
    self::assertSame('taxonomy_index_tid', self::configValue($category_filter, 'plugin_id'));
    self::assertTrue(self::configValue($category_filter, 'exposed'));
    self::assertSame('categoria', self::configValue($category_filter, 'expose', 'identifier'));
    self::assertSame('aculta_forum_categories', self::configValue($category_filter, 'vid'));
    self::assertTrue(self::configValue($category_filter, 'hierarchy'));
    self::assertFalse(self::configValue($options, 'query', 'options', 'disable_sql_rewrite'));
    self::assertTrue(self::configValue($options, 'style', 'options', 'uses_fields'));
    self::assertSame('ui_patterns', self::configValue($options, 'row', 'type'));
    $row_component = self::configValue($options, 'row', 'options', 'ui_patterns');
    self::assertSame('aculta420_v2:forum-topic-row', self::configValue($row_component, 'component_id'));
    self::assertSame('title', self::configValue($row_component, 'slots', 'title', 'sources', 0, 'source', 'ui_patterns_views_field'));
    self::assertSame('author_card', self::configValue($row_component, 'slots', 'meta', 'sources', 0, 'source', 'ui_patterns_views_field'));
    self::assertSame('field_aculta_categories', self::configValue($row_component, 'slots', 'meta', 'sources', 1, 'source', 'ui_patterns_views_field'));
    self::assertSame('comment_count', self::configValue($row_component, 'slots', 'replies_content', 'sources', 0, 'source', 'ui_patterns_views_field'));
    self::assertSame('last_updated', self::configValue($row_component, 'slots', 'activity_content', 'sources', 0, 'source', 'ui_patterns_views_field'));
    self::assertSame('aculta_user_author_card', self::configValue($options, 'fields', 'author_card', 'plugin_id'));
    self::assertSame('uid', self::configValue($options, 'fields', 'author_card', 'relationship'));
    self::assertSame('author_forum', self::configValue($options, 'fields', 'author_card', 'variant'));
    self::assertSame('Fixo', self::configValue($options, 'fields', 'sticky', 'settings', 'format_custom_true'));
    self::assertSame('', self::configValue($options, 'fields', 'sticky', 'settings', 'format_custom_false'));
    self::assertArrayNotHasKey('user_picture', self::configArray($options, 'fields'), 'Forum rows must use the public author-card policy, not read user_picture directly.');
    self::assertArrayNotHasKey('rendered_entity', self::configArray($options, 'fields'), 'The SDC row consumes selected View fields and does not render an unused node teaser.');
    self::assertSame('comment_entity_statistics', self::configValue($options, 'fields', 'comment_count', 'table'));
    self::assertFalse(self::configValue($options, 'fields', 'comment_count', 'empty_zero'));
    self::assertFalse(self::configValue($options, 'fields', 'comment_count', 'format_plural'));
    self::assertSame('comment_entity_statistics', self::configValue($options, 'fields', 'last_updated', 'table'));
    self::assertSame('aculta_forum_last_activity', self::configValue($options, 'fields', 'last_updated', 'plugin_id'));
    self::assertSame('comment_entity_statistics', self::configValue($options, 'sorts', 'last_updated', 'table'));
    self::assertSame('aculta_forum_last_activity', self::configValue($options, 'sorts', 'last_updated', 'field'));
    self::assertSame('aculta_forum_last_activity', self::configValue($options, 'sorts', 'last_updated', 'plugin_id'));
    self::assertSame('aculta_forum_last_activity', self::configValue($options, 'sorts', 'last_updated', 'field'));
    self::assertSame('DESC', self::configValue($options, 'sorts', 'last_updated', 'order'));
    self::assertArrayNotHasKey('discussion_field_name', self::configArray($options, 'filters'));
    self::assertContains('user.node_grants:view', self::configArray($view, 'display', 'default', 'cache_metadata', 'contexts'));
    self::assertSame(
      ['items_per_page' => TRUE, 'pager' => 'pager'],
      self::configValue($view, 'display', 'block_recent', 'display_options', 'allow'),
    );
    self::assertSame(
      'text_custom',
      self::configValue($options, 'empty', 'area_text_custom', 'plugin_id'),
    );
    self::assertTrue(self::configValue($options, 'empty', 'area_text_custom', 'empty'));
    self::assertSame(
      'Ainda não há tópicos publicados nesta lista.',
      self::configValue($options, 'empty', 'area_text_custom', 'content'),
    );
    self::assertSame('discussoes', self::configValue($view, 'display', 'page_discussions', 'display_options', 'path'));
    self::assertSame('none', self::configValue($view, 'display', 'page_discussions', 'display_options', 'menu', 'type'));
    self::assertFalse(self::configValue($view, 'display', 'page_discussions', 'display_options', 'defaults', 'title'));
    self::assertContains('url.query_args', self::configArray($view, 'display', 'page_discussions', 'cache_metadata', 'contexts'));
  }

  /**
   * Category discovery stays in Forum taxonomy and links into its filter.
   */
  public function testPublicCategoryIndexUsesOnlyForumVocabulary(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_categories.yml';
    $install = self::parseConfigFile($module_path . '/config/install/' . $filename);
    $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
    self::assertConfigSame($install, $sync);
    self::assertSame('taxonomy_term_field_data', self::configValue($install, 'base_table'));
    self::assertSame('categorias', self::configValue($install, 'display', 'page_categories', 'display_options', 'path'));
    self::assertSame(
      ['aculta_forum_categories' => 'aculta_forum_categories'],
      self::configValue($install, 'display', 'default', 'display_options', 'filters', 'vid', 'value'),
    );
    self::assertSame('fields', self::configValue($install, 'display', 'default', 'display_options', 'row', 'type'));
    self::assertSame('ui_patterns', self::configValue($install, 'display', 'page_categories', 'display_options', 'row', 'type'));
    self::assertSame('ui_patterns', self::configValue($install, 'display', 'block_landing', 'display_options', 'row', 'type'));
    foreach (['page_categories' => 'h3', 'block_landing' => 'h2'] as $display_id => $title_level) {
      $display = self::configValue($install, 'display', $display_id, 'display_options');
      self::assertSame(
        'aculta420_v2:forum-category',
        self::configValue($display, 'row', 'options', 'ui_patterns', 'component_id'),
      );
      self::assertSame(
        $title_level,
        self::configValue($display, 'row', 'options', 'ui_patterns', 'props', 'title_level', 'source', 'value'),
      );
      self::assertSame('view_field', self::configValue($display, 'row', 'options', 'ui_patterns', 'slots', 'title', 'sources', 0, 'source_id'));
      self::assertSame('name', self::configValue($display, 'row', 'options', 'ui_patterns', 'slots', 'title', 'sources', 0, 'source', 'ui_patterns_views_field'));
    }
    self::assertTrue(self::configValue($install, 'display', 'page_categories', 'display_options', 'fields', 'parent_name', 'exclude'));
    self::assertFalse(self::configValue($install, 'display', 'page_categories', 'display_options', 'fields', 'parent_card', 'exclude'));
    self::assertSame(
      'aculta_forum_category_topic_count',
      self::configValue($install, 'display', 'page_categories', 'display_options', 'row', 'options', 'ui_patterns', 'slots', 'count', 'sources', 0, 'source', 'ui_patterns_views_field'),
    );
    self::assertSame('some', self::configValue($install, 'display', 'default', 'display_options', 'pager', 'type'));
    self::assertSame('perm', self::configValue($install, 'display', 'default', 'display_options', 'access', 'type'));
    $category_fields = self::configValue($install, 'display', 'default', 'display_options', 'fields');
    self::assertTrue(self::configValue($category_fields, 'tid', 'exclude'));
    self::assertSame('tid', self::configValue($category_fields, 'tid', 'field'));
    self::assertTrue(self::configValue($category_fields, 'name', 'alter', 'make_link'));
    self::assertSame('/?categoria={{ tid }}', self::configValue($category_fields, 'name', 'alter', 'path'));
    self::assertSame(
      'taxonomy_term__parent',
      self::configValue($install, 'display', 'default', 'display_options', 'relationships', 'parent', 'table'),
    );
    self::assertSame(
      'parent_target_id',
      self::configValue($install, 'display', 'default', 'display_options', 'relationships', 'parent', 'field'),
    );
    self::assertSame('parent', self::configValue($install, 'display', 'default', 'display_options', 'fields', 'parent_name', 'relationship'));
    self::assertSame('Categoria principal', self::configValue($install, 'display', 'default', 'display_options', 'fields', 'parent_name', 'label'));
    self::assertSame('parent', self::configValue($install, 'display', 'default', 'display_options', 'sorts', 'parent_name', 'relationship'));
    self::assertSame('ASC', self::configValue($install, 'display', 'default', 'display_options', 'sorts', 'parent_name', 'order'));
    self::assertSame('block', self::configValue($install, 'display', 'block_landing', 'display_plugin'));
    self::assertSame(
      ['items_per_page' => TRUE, 'pager' => 'pager'],
      self::configValue($install, 'display', 'block_landing', 'display_options', 'allow'),
    );
    self::assertSame(
      ['min' => '', 'max' => '', 'value' => '0'],
      self::configValue($install, 'display', 'block_landing', 'display_options', 'filters', 'parent_target_id', 'value'),
    );
    self::assertFalse(self::configValue($install, 'display', 'block_landing', 'display_options', 'filters', 'parent_target_id', 'exposed'));
    self::assertSame(
      '<p><a href="/categorias">Ver todas as categorias</a></p>',
      self::configValue($install, 'display', 'block_landing', 'display_options', 'header', 'area_text_custom', 'content'),
    );
    $category_block = self::parseConfigFile($repository_path . '/config/sync/block.block.aculta420_v2_aculta_forum_categories_home.yml');
    self::assertSame('views_block:aculta_forum_categories-block_landing', self::configValue($category_block, 'plugin'));
    self::assertSame('sidebar_first', self::configValue($category_block, 'region'));
    self::assertSame("/\n/discussoes", self::configValue($category_block, 'visibility', 'request_path', 'pages'));
    self::assertSame('forum', self::configValue($category_block, 'visibility', 'aculta_domain_site', 'site_key'));
    self::assertSame(
      '<p><a href="/">Discussões recentes</a></p>',
      self::configValue($install, 'display', 'page_categories', 'display_options', 'header', 'area_text_custom', 'content'),
    );
    $topics = self::parseConfigFile($module_path . '/config/install/views.view.aculta_forum_public_topics.yml');
    $topics_sync = self::parseConfigFile($repository_path . '/config/sync/views.view.aculta_forum_public_topics.yml');
    self::assertConfigSame($topics, $topics_sync);
    self::assertSame(
      '<p><a href="/categorias">Ver categorias</a></p>',
      self::configValue($topics, 'display', 'page_discussions', 'display_options', 'header', 'area_text_custom', 'content'),
    );

    $tasks = self::parseConfigFile($module_path . '/aculta_forum.links.task.yml');
    self::assertSame('view.aculta_forum_categories.page_categories', self::configValue($tasks, 'aculta_forum.public_categories', 'route_name'));
    self::assertSame('view.aculta_forum_public_topics.page_discussions', self::configValue($tasks, 'aculta_forum.public_categories', 'base_route'));
  }

  /**
   * The unread listing is private and retains public-list scoping.
   */
  public function testUnreadListingUsesHistoryAndKeepsForumScope(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_unread_topics.yml';
    $view = self::parseConfigFile($module_path . '/config/install/' . $filename);
    $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
    $public = self::parseConfigFile($module_path . '/config/install/views.view.aculta_forum_public_topics.yml');
    $module_info = self::parseConfigFile($module_path . '/aculta_forum.info.yml');
    $composer = json_decode((string) file_get_contents($repository_path . '/composer.json'), TRUE, flags: JSON_THROW_ON_ERROR);
    self::assertConfigSame($view, $sync);
    self::assertContains('history', self::configArray($view, 'dependencies', 'module'));
    self::assertContains('drupal:history', self::configArray($module_info, 'dependencies'));
    self::assertSame('^1.0', self::configValue($composer, 'require', 'drupal/history'));

    $options = self::configValue($view, 'display', 'default', 'display_options');
    self::assertSame('role', self::configValue($options, 'access', 'type'));
    self::assertSame(['authenticated' => 'authenticated'], self::configValue($options, 'access', 'options', 'role'));
    self::assertSame('none', self::configValue($options, 'cache', 'type'), 'Per-user read state must not share a cached result.');
    foreach (['status', 'type', 'field_domain_source_target_id', 'field_aculta_categories_target_id'] as $filter) {
      self::assertSame(
        self::configValue($public, 'display', 'default', 'display_options', 'filters', $filter),
        self::configValue($options, 'filters', $filter),
        sprintf('The unread View preserves the fixed public-list filter %s.', $filter),
      );
    }
    self::assertFalse(self::configValue($options, 'query', 'options', 'disable_sql_rewrite'));
    self::assertArrayNotHasKey('timestamp', self::configArray($options, 'filters'), 'Unread state includes Forum posts, not only Node history.');
    self::assertSame('aculta_forum_unread_content', self::configValue($options, 'filters', 'forum_unread_content', 'plugin_id'));
    self::assertSame('history', self::configValue($options, 'filters', 'forum_unread_content', 'table'));
    self::assertFalse(self::configValue($options, 'filters', 'forum_unread_content', 'exposed'));
    self::assertSame('history_user_timestamp', self::configValue($options, 'fields', 'timestamp', 'plugin_id'));
    self::assertTrue(self::configValue($options, 'fields', 'timestamp', 'comments'));
    self::assertSame('aculta_forum_unread_posts', self::configValue($options, 'fields', 'forum_unread_posts', 'plugin_id'));
    self::assertSame('node', self::configValue($options, 'fields', 'forum_unread_posts', 'table'));
    self::assertSame('Publicações novas', self::configValue($options, 'fields', 'forum_unread_posts', 'label'));
    self::assertTrue(self::configValue($options, 'fields', 'forum_unread_posts', 'hide_empty'));
    self::assertSame('node_new_comments', self::configValue($options, 'fields', 'new_comments', 'plugin_id'));
    self::assertSame('node', self::configValue($options, 'fields', 'new_comments', 'table'));
    self::assertTrue(self::configValue($options, 'fields', 'new_comments', 'link_to_comment'));
    self::assertTrue(self::configValue($options, 'fields', 'new_comments', 'hide_empty'));
    self::assertTrue(self::configValue($options, 'fields', 'new_comments', 'empty_zero'));
    self::assertSame('discussoes/nao-lidas', self::configValue($view, 'display', 'page_unread', 'display_options', 'path'));

    $tasks = self::parseConfigFile($module_path . '/aculta_forum.links.task.yml');
    self::assertSame('view.aculta_forum_unread_topics.page_unread', self::configValue($tasks, 'aculta_forum.unread_topics', 'route_name'));
    self::assertSame('view.aculta_forum_public_topics.page_discussions', self::configValue($tasks, 'aculta_forum.unread_topics', 'base_route'));
  }

  /**
   * Discovery displays reuse the public View's fixed access scope.
   */
  public function testDiscoveryPagesPreservePublicFiltersAndUseCoreStatistics(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_public_topics.yml';
    $view = self::parseConfigFile($module_path . '/config/install/' . $filename);
    $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
    self::assertConfigSame($view, $sync);

    $default = self::configValue($view, 'display', 'default', 'display_options');
    $new = self::configValue($view, 'display', 'page_new_topics', 'display_options');
    self::assertSame('discussoes/novas', self::configValue($new, 'path'));
    self::assertFalse(self::configValue($new, 'defaults', 'sorts'));
    self::assertSame('created', self::configValue($new, 'sorts', 'created', 'field'));
    self::assertSame('DESC', self::configValue($new, 'sorts', 'created', 'order'));

    $unanswered = self::configValue($view, 'display', 'page_unanswered', 'display_options');
    self::assertSame('discussoes/sem-resposta', self::configValue($unanswered, 'path'));
    foreach (['status', 'type', 'field_domain_source_target_id', 'field_aculta_categories_target_id'] as $filter) {
      self::assertSame(self::configValue($default, 'filters', $filter), self::configValue($unanswered, 'filters', $filter), $filter);
    }
    self::assertSame('aculta_forum_no_replies', self::configValue($unanswered, 'filters', 'comment_count', 'plugin_id'));
    self::assertSame('comment_entity_statistics', self::configValue($unanswered, 'filters', 'comment_count', 'table'));
    self::assertSame('0', self::configValue($unanswered, 'filters', 'comment_count', 'value', 'value'));
    self::assertFalse(self::configValue($view, 'display', 'default', 'display_options', 'query', 'options', 'disable_sql_rewrite'));

    $popular = self::configValue($view, 'display', 'page_most_commented', 'display_options');
    self::assertSame('discussoes/mais-comentadas', self::configValue($popular, 'path'));
    self::assertSame('comment_entity_statistics', self::configValue($popular, 'sorts', 'comment_count', 'table'));
    self::assertSame('comment_count', self::configValue($popular, 'sorts', 'comment_count', 'field'));
    self::assertSame('DESC', self::configValue($popular, 'sorts', 'comment_count', 'order'));

    $tasks = self::parseConfigFile($module_path . '/aculta_forum.links.task.yml');
    foreach ([
      'new_topics' => 'page_new_topics',
      'unanswered_topics' => 'page_unanswered',
      'most_commented_topics' => 'page_most_commented',
    ] as $task => $display) {
      self::assertSame("view.aculta_forum_public_topics.$display", self::configValue($tasks, "aculta_forum.$task", 'route_name'));
      self::assertSame('view.aculta_forum_public_topics.page_discussions', self::configValue($tasks, "aculta_forum.$task", 'base_route'));
    }
  }

  /**
   * The Forum admin listing excludes unrelated and legacy node content.
   */
  public function testAdministrativeForumListingIsScopedAndAccessAware(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_admin_topics.yml';
    $view = self::parseConfigFile($module_path . '/config/install/' . $filename);
    $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
    self::assertConfigSame($view, $sync);

    $options = self::configValue($view, 'display', 'default', 'display_options');
    self::assertSame('access content overview', self::configValue($options, 'access', 'options', 'perm'));
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], self::configValue($options, 'filters', 'type', 'value'));
    self::assertFalse(self::configValue($options, 'filters', 'type', 'exposed'));
    self::assertFalse(self::configValue($options, 'query', 'options', 'disable_sql_rewrite'));
    self::assertArrayNotHasKey('node_bulk_form', self::configArray($options, 'fields'));
    self::assertSame('views_bulk_operations_bulk_form', self::configValue($options, 'fields', 'views_bulk_operations_bulk_form', 'plugin_id'));
    self::assertTrue(self::configValue($options, 'fields', 'views_bulk_operations_bulk_form', 'batch'));
    self::assertSame(10, self::configValue($options, 'fields', 'views_bulk_operations_bulk_form', 'batch_size'));
    self::assertSame(50, (new \ReflectionClassConstant(MoveForumTopicsAction::class, 'MAX_SELECTION'))->getValue());
    self::assertSame('aculta_forum_move_topics', self::configValue($options, 'fields', 'views_bulk_operations_bulk_form', 'selected_actions', 0, 'action_id'));
    self::assertSame('field', self::configValue($options, 'fields', 'field_aculta_categories', 'plugin_id'));
    self::assertSame('field_aculta_categories', self::configValue($options, 'fields', 'field_aculta_categories', 'field'));
    self::assertSame('field_aculta_categories', self::configValue($options, 'style', 'options', 'columns', 'field_aculta_categories'));
    self::assertSame('entity_operations', self::configValue($options, 'fields', 'operations', 'plugin_id'));
    self::assertSame('painel-administrativo/forum/conteudo', self::configValue($view, 'display', 'page_forum', 'display_options', 'path'));
    self::assertSame('domain_source', self::configValue($options, 'filters', 'field_domain_source_target_id', 'plugin_id'));
    self::assertSame(['_active' => '_active'], self::configValue($options, 'filters', 'field_domain_source_target_id', 'value'));
    self::assertFalse(self::configValue($options, 'filters', 'field_domain_source_target_id', 'exposed'));
    self::assertContains('user.node_grants:view', self::configArray($view, 'display', 'default', 'cache_metadata', 'contexts'));
  }

  /**
   * Administrative Forum comments exclude editorial and other contexts.
   */
  public function testAdministrativeForumCommentsAreScopedAndModerationAware(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_admin_comments.yml';
    $view = self::parseConfigFile($module_path . '/config/install/' . $filename);
    $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
    self::assertConfigSame($view, $sync);

    $options = self::configValue($view, 'display', 'default', 'display_options');
    self::assertSame('Atualizado', self::configValue($options, 'fields', 'changed', 'label'));
    self::assertSame('Operações', self::configValue($options, 'fields', 'operations', 'label'));
    self::assertSame('Não há comentários disponíveis.', self::configValue($options, 'empty', 'area_text_custom', 'content'));
    self::assertSame('administer comments', self::configValue($options, 'access', 'options', 'perm'));
    self::assertSame(['aculta_discussion_post' => 'aculta_discussion_post'], self::configValue($options, 'filters', 'comment_type', 'value'));
    self::assertSame('field_aculta_discussion', self::configValue($options, 'filters', 'field_name', 'value'));
    self::assertSame('node', self::configValue($options, 'filters', 'entity_type', 'value'));
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], self::configValue($options, 'filters', 'type', 'value'));
    self::assertSame('node', self::configValue($options, 'filters', 'type', 'relationship'));
    self::assertFalse(self::configValue($options, 'query', 'options', 'disable_sql_rewrite'));
    self::assertArrayNotHasKey('comment_bulk_form', self::configArray($options, 'fields'));
    self::assertSame(
      ['default', 'page_forum_comments', 'page_pending', 'page_published', 'page_rejected'],
      array_keys(self::configArray($view, 'display')),
    );
    self::assertSame('node', self::configValue($options, 'filters', 'field_domain_source_target_id', 'relationship'));
    self::assertSame(['_active' => '_active'], self::configValue($options, 'filters', 'field_domain_source_target_id', 'value'));
    self::assertFalse(self::configValue($options, 'filters', 'field_domain_source_target_id', 'exposed'));
    $tasks = self::parseConfigFile($module_path . '/aculta_forum.links.task.yml');
    self::assertSame('view.aculta_forum_admin_comments.page_rejected', self::configValue($tasks, 'aculta_forum.admin_comments_rejected', 'route_name'));
    self::assertSame('view.aculta_forum_admin_comments.page_pending', self::configValue($tasks, 'aculta_forum.admin_comments_pending', 'route_name'));

    $storage_filename = 'field.storage.comment.field_aculta_rejected.yml';
    self::assertSame(
      self::parseConfigFile($module_path . '/config/install/' . $storage_filename),
      self::parseConfigFile($repository_path . '/config/sync/' . $storage_filename),
    );
    $field_filename = 'field.field.comment.aculta_discussion_post.field_aculta_rejected.yml';
    self::assertSame(
      self::parseConfigFile($module_path . '/config/install/' . $field_filename),
      self::parseConfigFile($repository_path . '/config/sync/' . $field_filename),
    );
    $display_filename = 'core.entity_view_display.comment.aculta_discussion_post.default.yml';
    $display = self::parseConfigFile($module_path . '/config/install/' . $display_filename);
    self::assertSame($display, self::parseConfigFile($repository_path . '/config/sync/' . $display_filename));
    self::assertArrayHasKey('field_aculta_rejected', self::configArray($display, 'hidden'));
  }

  /**
   * Domain Source field configuration is shipped by the Forum module.
   */
  public function testDomainSourceFieldIsDeclaredForPublicTopics(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'field.field.node.aculta_forum_topic.field_domain_source.yml';
    self::assertSame(
      self::parseConfigFile($module_path . '/config/install/' . $filename),
      self::parseConfigFile($repository_path . '/config/sync/' . $filename),
    );
  }

  /**
   * The public topic alias pattern selects the replacement bundle only.
   */
  public function testPathautoPatternTargetsPublicForumTopics(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'pathauto.pattern.aculta_forum_topic.yml';
    $install = self::parseConfigFile($module_path . '/config/install/' . $filename);
    $sync = self::parseConfigFile($repository_path . '/config/sync/' . $filename);
    unset($sync['_core']);
    self::assertConfigSame($install, $sync);
    self::assertSame('/topico/[node:title]', self::configValue($install, 'pattern'));
    self::assertSame(
      ['aculta_forum_topic' => 'aculta_forum_topic'],
      self::configValue($install, 'selection_criteria', '09a3b0e7-9f1c-4a86-a200-2d7a2a22bd32', 'bundles'),
    );
    self::assertSame(
      ['node.type.aculta_forum_topic'],
      self::configValue($install, 'dependencies', 'config'),
    );
  }

  /**
   * Friendly topic creation uses Core's Node form and create access.
   */
  public function testTopicComposerRouteUsesCoreEntityFormAccess(): void {
    $route = self::configValue(self::parseConfigFile(dirname(__DIR__, 3) . '/aculta_forum.routing.yml'), 'aculta_forum.topic_composer');

    self::assertSame('/discussoes/novo-topico', self::configValue($route, 'path'));
    self::assertSame('node.default', self::configValue($route, 'defaults', '_entity_form'));
    self::assertSame('aculta_forum_topic', self::configValue($route, 'defaults', 'node_type'));
    self::assertSame('node:{node_type}', self::configValue($route, 'requirements', '_entity_create_access'));
  }

  /**
   * Email-confirmed participants receive only own-topic Node permissions.
   */
  public function testEmailConfirmedRoleCanCreateAndManageOwnTopics(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $role = self::parseConfigFile($repository_path . '/config/sync/user.role.email_confirmed.yml');

    foreach ([
      'create aculta_forum_topic content',
      'edit own aculta_forum_topic content',
      'delete own aculta_forum_topic content',
    ] as $permission) {
      self::assertContains($permission, self::configArray($role, 'permissions'));
    }
    self::assertNotContains('edit any aculta_forum_topic content', self::configArray($role, 'permissions'));
    self::assertNotContains('delete any aculta_forum_topic content', self::configArray($role, 'permissions'));
  }

  /**
   * Compares YAML config maps without treating key order as significant.
   *
   * Drupal configuration is a mapping; only YAML lists have meaningful order.
   */
  /**
   * @param array<array-key, mixed> $expected
   * @param array<array-key, mixed> $actual
   */
  private static function assertConfigSame(array $expected, array $actual, string $message = ''): void {
    self::assertSame(self::normalizeConfigMap($expected), self::normalizeConfigMap($actual), $message);
  }

  /**
   * Recursively sorts associative maps while preserving sequence order.
   */
  /**
   * @param array<array-key, mixed> $value
   *
   * @return array<array-key, mixed>
   */
  private static function normalizeConfigMap(array $value): array {
    foreach ($value as &$item) {
      if (is_array($item)) {
        $item = self::normalizeConfigMap($item);
      }
    }
    unset($item);
    if (!array_is_list($value)) {
      ksort($value);
    }
    return $value;
  }

  /** @return array<array-key, mixed> */
  private static function parseConfigFile(string $path): array {
    $config = Yaml::parseFile($path);
    if (!is_array($config)) {
      throw new \UnexpectedValueException('Expected a YAML configuration map.');
    }
    return $config;
  }

  /** @param array-key ...$path */
  private static function configValue(mixed $value, int|string ...$path): mixed {
    foreach ($path as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        throw new \UnexpectedValueException('A required configuration path is missing.');
      }
      $value = $value[$key];
    }
    return $value;
  }
  /**
   * @param array-key ...$path
   *
   * @return array<array-key, mixed>
   */
  private static function configArray(mixed $value, int|string ...$path): array {
    $value = self::configValue($value, ...$path);
    if (!is_array($value)) {
      throw new \UnexpectedValueException('Expected an array in configuration.');
    }
    return $value;
  }

  /** @param array-key ...$path */
  private static function configBoolean(mixed $value, int|string ...$path): bool {
    $value = self::configValue($value, ...$path);
    if (is_bool($value)) {
      return $value;
    }
    throw new \UnexpectedValueException('Expected a boolean in configuration.');
  }

  private static function readSource(string $path): string {
    $source = file_get_contents($path);
    if (!is_string($source)) {
      throw new \UnexpectedValueException('The source file could not be read.');
    }
    return $source;
  }
}
