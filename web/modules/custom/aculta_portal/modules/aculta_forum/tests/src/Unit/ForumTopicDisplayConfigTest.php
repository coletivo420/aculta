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
      $install = Yaml::parseFile($module_path . '/config/install/' . $filename);
      $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
      self::assertConfigSame($install, $sync, $filename);
    }
  }

  /**
   * The Forum has its own admin section, separate from Content.
   */
  public function testForumAdminToolsAreFirstLevelPagesUnderCanonicalSection(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $topics = Yaml::parseFile($module_path . '/config/install/views.view.aculta_forum_admin_topics.yml');
    $comments = Yaml::parseFile($module_path . '/config/install/views.view.aculta_forum_admin_comments.yml');
    $tasks = Yaml::parseFile($module_path . '/aculta_forum.links.task.yml');
    $menu = Yaml::parseFile($module_path . '/aculta_forum.links.menu.yml');
    $routes = Yaml::parseFile($module_path . '/aculta_forum.routing.yml');

    self::assertSame(['default', 'page_forum'], array_keys($topics['display']));
    self::assertSame(
      ['default', 'page_forum_comments', 'page_pending', 'page_published', 'page_rejected'],
      array_keys($comments['display']),
    );
    self::assertSame('painel-administrativo/forum/conteudo', $topics['display']['page_forum']['display_options']['path']);
    self::assertSame('painel-administrativo/forum/comentarios', $comments['display']['page_forum_comments']['display_options']['path']);
    self::assertSame('painel-administrativo/forum/comentarios/publicados', $comments['display']['page_published']['display_options']['path']);
    self::assertSame('painel-administrativo/forum/comentarios/aguardando-moderacao', $comments['display']['page_pending']['display_options']['path']);
    self::assertSame('painel-administrativo/forum/comentarios/rejeitados', $comments['display']['page_rejected']['display_options']['path']);
    self::assertSame('aculta_forum.admin_comments_tab', $tasks['aculta_forum.admin_comments_published']['parent_id']);
    self::assertSame('view.aculta_forum_admin_comments.page_published', $tasks['aculta_forum.admin_comments_published']['route_name']);
    foreach ([
      'admin_overview_tab' => 'aculta_forum.admin',
      'admin_topics_tab' => 'view.aculta_forum_admin_topics.page_forum',
      'admin_comments_tab' => 'view.aculta_forum_admin_comments.page_forum_comments',
      'admin_settings_tab' => 'aculta_forum.settings',
    ] as $task => $route_name) {
      self::assertSame($route_name, $tasks["aculta_forum.$task"]['route_name']);
      self::assertSame('aculta_forum.admin', $tasks["aculta_forum.$task"]['base_route']);
    }
    self::assertSame('aculta_forum.admin', $menu['aculta_forum.admin']['route_name']);
    self::assertSame('system.admin', $menu['aculta_forum.admin']['parent']);
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
    self::assertSame('aculta_forum.admin', $menu['aculta_forum.admin_topics']['parent']);
    self::assertSame('view.aculta_forum_admin_topics.page_forum', $menu['aculta_forum.admin_topics']['route_name']);
    self::assertSame('aculta_forum.admin', $menu['aculta_forum.admin_comments']['parent']);
    self::assertSame('view.aculta_forum_admin_comments.page_forum_comments', $menu['aculta_forum.admin_comments']['route_name']);
    self::assertSame('aculta_forum.admin', $menu['aculta_forum.admin_settings']['parent']);
    self::assertSame('aculta_forum.settings', $menu['aculta_forum.admin_settings']['route_name']);
    self::assertSame('/painel-administrativo/forum', $routes['aculta_forum.admin']['path']);
    self::assertSame('access content overview', $routes['aculta_forum.admin']['requirements']['_permission']);
    self::assertArrayNotHasKey('aculta_forum.admin_content_redirect', $routes);
    self::assertArrayNotHasKey('aculta_forum.admin_comments_redirect', $routes);
    // The placeholder topic-move redirect route was removed; moving a topic is
    // a task tab of the administration overview.
    self::assertArrayNotHasKey('aculta_forum.topic_move', $routes);
    self::assertSame('/painel-administrativo/forum/comentarios/{comment}/mover', $routes['aculta_forum.comment_move']['path']);
    self::assertSame('Mover comentário do fórum', $routes['aculta_forum.comment_move']['defaults']['_title']);
    self::assertSame('\Drupal\aculta_forum\Form\ForumCommentMoveForm', $routes['aculta_forum.comment_move']['defaults']['_form']);
    self::assertSame('entity:comment', $routes['aculta_forum.comment_move']['options']['parameters']['comment']['type']);
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

    $content = Yaml::parseFile($repository_path . '/config/sync/views.view.content.yml');
    $filters = $content['display']['default']['display_options']['filters'];
    self::assertSame('not in', $filters['forum_bundle_exclusion']['operator']);
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], $filters['forum_bundle_exclusion']['value']);
    self::assertFalse($filters['forum_bundle_exclusion']['exposed']);
  }

  /**
   * Forum-only settings use a dedicated permission and remain fail-closed.
   */
  public function testForumSettingsAdministrationIsDedicatedAndFailClosed(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $routes = Yaml::parseFile($module_path . '/aculta_forum.routing.yml');
    $menu = Yaml::parseFile($module_path . '/aculta_forum.links.menu.yml');
    $permissions = Yaml::parseFile($module_path . '/aculta_forum.permissions.yml');
    $install = Yaml::parseFile($module_path . '/config/install/aculta_forum.settings.yml');
    $sync = Yaml::parseFile($repository_path . '/config/sync/aculta_forum.settings.yml');
    $editor = Yaml::parseFile($repository_path . '/config/sync/user.role.content_editor.yml');
    $form = file_get_contents($module_path . '/src/Form/ForumSettingsForm.php');

    self::assertSame('/painel-administrativo/forum/configuracoes', $routes['aculta_forum.settings']['path']);
    self::assertSame('\Drupal\aculta_forum\Form\ForumSettingsForm', $routes['aculta_forum.settings']['defaults']['_form']);
    self::assertSame('administer aculta forum settings', $routes['aculta_forum.settings']['requirements']['_permission']);
    self::assertSame(['GET', 'HEAD', 'POST'], $routes['aculta_forum.settings']['methods']);
    self::assertTrue($permissions['administer aculta forum settings']['restrict access']);
    self::assertSame('aculta_forum.admin', $menu['aculta_forum.admin_settings']['parent']);
    self::assertSame(FALSE, $install['comment_move_enabled']);
    self::assertConfigSame($install, $sync);
    self::assertNotContains('administer aculta forum settings', $editor['permissions']);
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
    $install = Yaml::parseFile($module_path . '/config/install/views.view.aculta_forum_public_topics.yml');
    $sync = Yaml::parseFile($repository_path . '/config/sync/views.view.aculta_forum_public_topics.yml');
    $field = $install['display']['default']['display_options']['fields']['last_comment_uid'];

    self::assertSame($field, $sync['display']['default']['display_options']['fields']['last_comment_uid']);
    self::assertSame('comment_entity_statistics', $field['table']);
    self::assertSame('last_comment_uid', $field['field']);
    self::assertTrue($field['exclude'], 'The account ID is a render-only input, never a visible View field.');
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
   * Forum replies use the dedicated Forum attachment format and Media policy.
   */
  public function testForumCommentAttachmentsUseDedicatedForumPolicies(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $field_filename = 'field.field.comment.aculta_discussion_post.comment_body.yml';
    $field = Yaml::parseFile($module_path . '/config/install/' . $field_filename);
    $field_sync = Yaml::parseFile($repository_path . '/config/sync/' . $field_filename);
    unset($field_sync['_core']);

    $format = Yaml::parseFile($module_path . '/config/install/filter.format.forum.yml');
    $format_sync = Yaml::parseFile($repository_path . '/config/sync/filter.format.forum.yml');
    $editor = Yaml::parseFile($module_path . '/config/install/editor.editor.forum.yml');
    $editor_sync = Yaml::parseFile($repository_path . '/config/sync/editor.editor.forum.yml');
    unset($format_sync['_core'], $editor_sync['_core']);

    self::assertConfigSame($field, $field_sync);
    self::assertConfigSame($format, $format_sync);
    self::assertConfigSame($editor, $editor_sync);
    self::assertSame(['forum' => 'forum'], $field['settings']['allowed_formats']);
    self::assertContains('filter.format.forum', $field['dependencies']['config']);
    self::assertSame('forum', $format['format']);
    self::assertTrue($format['filters']['media_embed']['status']);
    self::assertSame(
      ['forum_attachment' => 'forum_attachment'],
      $format['filters']['media_embed']['settings']['allowed_media_types'],
      'Participant comments may embed only Forum attachment media.',
    );
    self::assertContains('acultaForumAttachment', $editor['settings']['toolbar']['items']);
    self::assertContains('acultaForumGallery', $editor['settings']['toolbar']['items']);
    self::assertFalse($editor['image_upload']['status'], 'Forum uploads go through the centralized attachment policy.');
    self::assertSame('private', $editor['image_upload']['scheme']);
    self::assertSame('forum/anexos', $editor['image_upload']['directory']);
  }

  /**
   * Follow and save flags target only the current public topic bundle.
   */
  public function testTopicFlagsTargetCurrentPublicBundle(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    foreach (['seguir_topico', 'salvar_topico'] as $id) {
      $filename = 'flag.flag.' . $id . '.yml';
      $install = Yaml::parseFile($module_path . '/config/install/' . $filename);
      $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
      self::assertConfigSame($install, $sync, $filename);
      self::assertSame(['aculta_forum_topic'], $install['bundles']);
      self::assertFalse($install['global']);
      self::assertSame('node', $install['entity_type']);
    }
  }

  /**
   * Follow and save controls are placed only on the full topic page.
   */
  public function testTopicFullDisplayIncludesFollowAndSaveControls(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'core.entity_view_display.node.aculta_forum_topic.full.yml';
    $install = Yaml::parseFile($module_path . '/config/install/' . $filename);
    $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
    self::assertConfigSame($install, $sync);
    self::assertContains('flag', $install['dependencies']['module']);

    foreach (['flag_seguir_topico' => 20, 'flag_salvar_topico' => 21] as $key => $weight) {
      self::assertSame([
        'weight' => $weight,
        'region' => 'content',
        'settings' => [],
        'third_party_settings' => [],
      ], $install['content'][$key]);
    }
    $teaser = Yaml::parseFile($module_path . '/config/install/core.entity_view_display.node.aculta_forum_topic.teaser.yml');
    $teaser_sync = Yaml::parseFile($repository_path . '/config/sync/core.entity_view_display.node.aculta_forum_topic.teaser.yml');
    self::assertSame($teaser, $teaser_sync);
    self::assertArrayNotHasKey('flag_seguir_topico', $teaser['content']);
    self::assertArrayNotHasKey('flag_salvar_topico', $teaser['content']);
    self::assertTrue($teaser['hidden']['flag_seguir_topico']);
    self::assertTrue($teaser['hidden']['flag_salvar_topico']);
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
    self::assertConfigSame($view, $sync);

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
    self::assertTrue($options['style']['options']['uses_fields']);
    self::assertSame('fields', $options['row']['type']);
    self::assertSame('teaser', $options['fields']['rendered_entity']['view_mode']);
    self::assertSame('comment_entity_statistics', $options['fields']['comment_count']['table']);
    self::assertFalse($options['fields']['comment_count']['empty_zero']);
    self::assertFalse($options['fields']['comment_count']['format_plural']);
    self::assertSame('comment_entity_statistics', $options['fields']['last_updated']['table']);
    self::assertSame('aculta_forum_last_activity', $options['fields']['last_updated']['plugin_id']);
    self::assertSame('comment_entity_statistics', $options['sorts']['last_updated']['table']);
    self::assertSame('aculta_forum_last_activity', $options['sorts']['last_updated']['field']);
    self::assertSame('aculta_forum_last_activity', $options['sorts']['last_updated']['plugin_id']);
    self::assertSame('aculta_forum_last_activity', $options['sorts']['last_updated']['field']);
    self::assertSame('DESC', $options['sorts']['last_updated']['order']);
    self::assertArrayNotHasKey('discussion_field_name', $options['filters']);
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
   * Category discovery stays in Forum taxonomy and links into its filter.
   */
  public function testPublicCategoryIndexUsesOnlyForumVocabulary(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_categories.yml';
    $install = Yaml::parseFile($module_path . '/config/install/' . $filename);
    $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
    self::assertConfigSame($install, $sync);
    self::assertSame('taxonomy_term_field_data', $install['base_table']);
    self::assertSame('categorias', $install['display']['page_categories']['display_options']['path']);
    self::assertSame(
      ['aculta_forum_categories' => 'aculta_forum_categories'],
      $install['display']['default']['display_options']['filters']['vid']['value'],
    );
    self::assertSame('fields', $install['display']['default']['display_options']['row']['type']);
    $category_fields = $install['display']['default']['display_options']['fields'];
    self::assertTrue($category_fields['tid']['exclude']);
    self::assertSame('tid', $category_fields['tid']['field']);
    self::assertTrue($category_fields['name']['alter']['make_link']);
    self::assertSame('/?categoria={{ tid }}', $category_fields['name']['alter']['path']);
    self::assertSame(
      'taxonomy_term__parent',
      $install['display']['default']['display_options']['relationships']['parent']['table'],
    );
    self::assertSame(
      'parent_target_id',
      $install['display']['default']['display_options']['relationships']['parent']['field'],
    );
    self::assertSame('parent', $install['display']['default']['display_options']['fields']['parent_name']['relationship']);
    self::assertSame('Categoria principal', $install['display']['default']['display_options']['fields']['parent_name']['label']);
    self::assertSame('parent', $install['display']['default']['display_options']['sorts']['parent_name']['relationship']);
    self::assertSame('ASC', $install['display']['default']['display_options']['sorts']['parent_name']['order']);
    self::assertSame('block', $install['display']['block_landing']['display_plugin']);
    self::assertSame(
      ['items_per_page' => TRUE, 'pager' => 'pager'],
      $install['display']['block_landing']['display_options']['allow'],
    );
    self::assertSame(
      ['min' => '', 'max' => '', 'value' => '0'],
      $install['display']['block_landing']['display_options']['filters']['parent_target_id']['value'],
    );
    self::assertFalse($install['display']['block_landing']['display_options']['filters']['parent_target_id']['exposed']);
    self::assertSame(
      '<p><a href="/categorias">Ver todas as categorias</a></p>',
      $install['display']['block_landing']['display_options']['header']['area_text_custom']['content'],
    );
    $category_block = Yaml::parseFile($repository_path . '/config/sync/block.block.aculta_forum_categories_home.yml');
    self::assertSame('views_block:aculta_forum_categories-block_landing', $category_block['plugin']);
    self::assertSame('sidebar', $category_block['region']);
    self::assertSame("/\n/discussoes", $category_block['visibility']['request_path']['pages']);
    self::assertSame('forum', $category_block['visibility']['aculta_domain_purpose']['purpose']);
    self::assertSame(
      '<p><a href="/">Discussões recentes</a></p>',
      $install['display']['page_categories']['display_options']['header']['area_text_custom']['content'],
    );
    $topics = Yaml::parseFile($module_path . '/config/install/views.view.aculta_forum_public_topics.yml');
    $topics_sync = Yaml::parseFile($repository_path . '/config/sync/views.view.aculta_forum_public_topics.yml');
    self::assertConfigSame($topics, $topics_sync);
    self::assertSame(
      '<p><a href="/categorias">Ver categorias</a></p>',
      $topics['display']['page_discussions']['display_options']['header']['area_text_custom']['content'],
    );

    $tasks = Yaml::parseFile($module_path . '/aculta_forum.links.task.yml');
    self::assertSame('view.aculta_forum_categories.page_categories', $tasks['aculta_forum.public_categories']['route_name']);
    self::assertSame('view.aculta_forum_public_topics.page_discussions', $tasks['aculta_forum.public_categories']['base_route']);
  }

  /**
   * The unread listing is private and retains public-list scoping.
   */
  public function testUnreadListingUsesHistoryAndKeepsForumScope(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_unread_topics.yml';
    $view = Yaml::parseFile($module_path . '/config/install/' . $filename);
    $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
    $public = Yaml::parseFile($module_path . '/config/install/views.view.aculta_forum_public_topics.yml');
    $module_info = Yaml::parseFile($module_path . '/aculta_forum.info.yml');
    $composer = json_decode((string) file_get_contents($repository_path . '/composer.json'), TRUE, flags: JSON_THROW_ON_ERROR);
    self::assertConfigSame($view, $sync);
    self::assertContains('history', $view['dependencies']['module']);
    self::assertContains('drupal:history', $module_info['dependencies']);
    self::assertSame('^1.0', $composer['require']['drupal/history']);

    $options = $view['display']['default']['display_options'];
    self::assertSame('role', $options['access']['type']);
    self::assertSame(['authenticated' => 'authenticated'], $options['access']['options']['role']);
    self::assertSame('none', $options['cache']['type'], 'Per-user read state must not share a cached result.');
    foreach (['status', 'type', 'field_domain_source_target_id', 'field_aculta_categories_target_id'] as $filter) {
      self::assertSame(
        $public['display']['default']['display_options']['filters'][$filter],
        $options['filters'][$filter],
        sprintf('The unread View preserves the fixed public-list filter %s.', $filter),
      );
    }
    self::assertFalse($options['query']['options']['disable_sql_rewrite']);
    self::assertArrayNotHasKey('timestamp', $options['filters'], 'Unread state includes Forum posts, not only Node history.');
    self::assertSame('aculta_forum_unread_content', $options['filters']['forum_unread_content']['plugin_id']);
    self::assertSame('history', $options['filters']['forum_unread_content']['table']);
    self::assertFalse($options['filters']['forum_unread_content']['exposed']);
    self::assertSame('history_user_timestamp', $options['fields']['timestamp']['plugin_id']);
    self::assertTrue($options['fields']['timestamp']['comments']);
    self::assertSame('aculta_forum_unread_posts', $options['fields']['forum_unread_posts']['plugin_id']);
    self::assertSame('node', $options['fields']['forum_unread_posts']['table']);
    self::assertSame('Publicações novas', $options['fields']['forum_unread_posts']['label']);
    self::assertTrue($options['fields']['forum_unread_posts']['hide_empty']);
    self::assertSame('node_new_comments', $options['fields']['new_comments']['plugin_id']);
    self::assertSame('node', $options['fields']['new_comments']['table']);
    self::assertTrue($options['fields']['new_comments']['link_to_comment']);
    self::assertTrue($options['fields']['new_comments']['hide_empty']);
    self::assertTrue($options['fields']['new_comments']['empty_zero']);
    self::assertSame('discussoes/nao-lidas', $view['display']['page_unread']['display_options']['path']);

    $tasks = Yaml::parseFile($module_path . '/aculta_forum.links.task.yml');
    self::assertSame('view.aculta_forum_unread_topics.page_unread', $tasks['aculta_forum.unread_topics']['route_name']);
    self::assertSame('view.aculta_forum_public_topics.page_discussions', $tasks['aculta_forum.unread_topics']['base_route']);
  }

  /**
   * Discovery displays reuse the public View's fixed access scope.
   */
  public function testDiscoveryPagesPreservePublicFiltersAndUseCoreStatistics(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $filename = 'views.view.aculta_forum_public_topics.yml';
    $view = Yaml::parseFile($module_path . '/config/install/' . $filename);
    $sync = Yaml::parseFile($repository_path . '/config/sync/' . $filename);
    self::assertConfigSame($view, $sync);

    $default = $view['display']['default']['display_options'];
    $new = $view['display']['page_new_topics']['display_options'];
    self::assertSame('discussoes/novas', $new['path']);
    self::assertFalse($new['defaults']['sorts']);
    self::assertSame('created', $new['sorts']['created']['field']);
    self::assertSame('DESC', $new['sorts']['created']['order']);

    $unanswered = $view['display']['page_unanswered']['display_options'];
    self::assertSame('discussoes/sem-resposta', $unanswered['path']);
    foreach (['status', 'type', 'field_domain_source_target_id', 'field_aculta_categories_target_id'] as $filter) {
      self::assertSame($default['filters'][$filter], $unanswered['filters'][$filter], $filter);
    }
    self::assertSame('aculta_forum_no_replies', $unanswered['filters']['comment_count']['plugin_id']);
    self::assertSame('comment_entity_statistics', $unanswered['filters']['comment_count']['table']);
    self::assertSame('0', $unanswered['filters']['comment_count']['value']['value']);
    self::assertFalse($view['display']['default']['display_options']['query']['options']['disable_sql_rewrite']);

    $popular = $view['display']['page_most_commented']['display_options'];
    self::assertSame('discussoes/mais-comentadas', $popular['path']);
    self::assertSame('comment_entity_statistics', $popular['sorts']['comment_count']['table']);
    self::assertSame('comment_count', $popular['sorts']['comment_count']['field']);
    self::assertSame('DESC', $popular['sorts']['comment_count']['order']);

    $tasks = Yaml::parseFile($module_path . '/aculta_forum.links.task.yml');
    foreach ([
      'new_topics' => 'page_new_topics',
      'unanswered_topics' => 'page_unanswered',
      'most_commented_topics' => 'page_most_commented',
    ] as $task => $display) {
      self::assertSame("view.aculta_forum_public_topics.$display", $tasks["aculta_forum.$task"]['route_name']);
      self::assertSame('view.aculta_forum_public_topics.page_discussions', $tasks["aculta_forum.$task"]['base_route']);
    }
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
    self::assertConfigSame($view, $sync);

    $options = $view['display']['default']['display_options'];
    self::assertSame('access content overview', $options['access']['options']['perm']);
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], $options['filters']['type']['value']);
    self::assertFalse($options['filters']['type']['exposed']);
    self::assertFalse($options['query']['options']['disable_sql_rewrite']);
    self::assertArrayNotHasKey('node_bulk_form', $options['fields']);
    self::assertSame('views_bulk_operations_bulk_form', $options['fields']['views_bulk_operations_bulk_form']['plugin_id']);
    self::assertTrue($options['fields']['views_bulk_operations_bulk_form']['batch']);
    self::assertSame(10, $options['fields']['views_bulk_operations_bulk_form']['batch_size']);
    self::assertSame(50, MoveForumTopicsAction::MAX_SELECTION);
    self::assertSame('aculta_forum_move_topics', $options['fields']['views_bulk_operations_bulk_form']['selected_actions'][0]['action_id']);
    self::assertSame('field', $options['fields']['field_aculta_categories']['plugin_id']);
    self::assertSame('field_aculta_categories', $options['fields']['field_aculta_categories']['field']);
    self::assertSame('field_aculta_categories', $options['style']['options']['columns']['field_aculta_categories']);
    self::assertSame('entity_operations', $options['fields']['operations']['plugin_id']);
    self::assertSame('painel-administrativo/forum/conteudo', $view['display']['page_forum']['display_options']['path']);
    self::assertSame('domain_source', $options['filters']['field_domain_source_target_id']['plugin_id']);
    self::assertSame(['_active' => '_active'], $options['filters']['field_domain_source_target_id']['value']);
    self::assertFalse($options['filters']['field_domain_source_target_id']['exposed']);
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
    self::assertConfigSame($view, $sync);

    $options = $view['display']['default']['display_options'];
    self::assertSame('Atualizado', $options['fields']['changed']['label']);
    self::assertSame('Operações', $options['fields']['operations']['label']);
    self::assertSame('Não há comentários disponíveis.', $options['empty']['area_text_custom']['content']);
    self::assertSame('administer comments', $options['access']['options']['perm']);
    self::assertSame(['aculta_discussion_post' => 'aculta_discussion_post'], $options['filters']['comment_type']['value']);
    self::assertSame('field_aculta_discussion', $options['filters']['field_name']['value']);
    self::assertSame('node', $options['filters']['entity_type']['value']);
    self::assertSame(['aculta_forum_topic' => 'aculta_forum_topic'], $options['filters']['type']['value']);
    self::assertSame('node', $options['filters']['type']['relationship']);
    self::assertFalse($options['query']['options']['disable_sql_rewrite']);
    self::assertArrayNotHasKey('comment_bulk_form', $options['fields']);
    self::assertSame(
      ['default', 'page_forum_comments', 'page_pending', 'page_published', 'page_rejected'],
      array_keys($view['display']),
    );
    self::assertSame('node', $options['filters']['field_domain_source_target_id']['relationship']);
    self::assertSame(['_active' => '_active'], $options['filters']['field_domain_source_target_id']['value']);
    self::assertFalse($options['filters']['field_domain_source_target_id']['exposed']);
    $tasks = Yaml::parseFile($module_path . '/aculta_forum.links.task.yml');
    self::assertSame('view.aculta_forum_admin_comments.page_rejected', $tasks['aculta_forum.admin_comments_rejected']['route_name']);
    self::assertSame('view.aculta_forum_admin_comments.page_pending', $tasks['aculta_forum.admin_comments_pending']['route_name']);

    $storage_filename = 'field.storage.comment.field_aculta_rejected.yml';
    self::assertSame(
      Yaml::parseFile($module_path . '/config/install/' . $storage_filename),
      Yaml::parseFile($repository_path . '/config/sync/' . $storage_filename),
    );
    $field_filename = 'field.field.comment.aculta_discussion_post.field_aculta_rejected.yml';
    self::assertSame(
      Yaml::parseFile($module_path . '/config/install/' . $field_filename),
      Yaml::parseFile($repository_path . '/config/sync/' . $field_filename),
    );
    $display_filename = 'core.entity_view_display.comment.aculta_discussion_post.default.yml';
    $display = Yaml::parseFile($module_path . '/config/install/' . $display_filename);
    self::assertSame($display, Yaml::parseFile($repository_path . '/config/sync/' . $display_filename));
    self::assertArrayHasKey('field_aculta_rejected', $display['hidden']);
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
    self::assertConfigSame($install, $sync);
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

  /**
   * Compares YAML config maps without treating key order as significant.
   *
   * Drupal configuration is a mapping; only YAML lists have meaningful order.
   */
  private static function assertConfigSame(array $expected, array $actual, string $message = ''): void {
    self::assertSame(self::normalizeConfigMap($expected), self::normalizeConfigMap($actual), $message);
  }

  /**
   * Recursively sorts associative maps while preserving sequence order.
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

}
