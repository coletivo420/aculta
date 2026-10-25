<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\aculta_forum_lms\Controller\LmsForumModerationController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates the LMS topic's install configuration boundaries.
 */
#[Group('aculta_forum_lms')]
final class LmsDiscussionInstallConfigTest extends UnitTestCase {

  /**
   * The moderation controller loads independently of ControllerBase internals.
   */
  public function testModerationControllerClassCanBeLoaded(): void {
    self::assertTrue(class_exists(LmsForumModerationController::class));
  }

  /**
   * The LMS bundle reuses Comment storage and has a visible full display.
   */
  public function testBundleReusesTheSharedCommentField(): void {
    $config = $this->config('node.type.aculta_lms_topic');
    self::assertSame('aculta_lms_topic', $config['type']);
    self::assertLessThanOrEqual(32, strlen($config['type']));
    self::assertContains('aculta_forum_lms', $config['dependencies']['enforced']['module']);

    $comment = $this->config('field.field.node.aculta_lms_topic.field_aculta_discussion');
    self::assertSame('field_aculta_discussion', $comment['field_name']);
    self::assertSame('comment', $comment['field_type']);
    self::assertSame('aculta_discussion_post', $this->storageConfig('field.storage.node.field_aculta_discussion')['settings']['comment_type']);

    $display = $this->config('core.entity_view_display.node.aculta_lms_topic.full');
    self::assertSame('comment_default', $display['content']['field_aculta_discussion']['type']);
    self::assertArrayNotHasKey('field_aculta_discussion', $display['hidden']);

    $commentBody = $this->syncConfig('field.field.comment.aculta_discussion_post.comment_body');
    self::assertSame(['forum' => 'forum'], $commentBody['settings']['allowed_formats']);
    $textFormat = $this->syncConfig('filter.format.forum');
    self::assertSame('media', $textFormat['filters']['media_embed']['provider']);
    $editor = $this->syncConfig('editor.editor.forum');
    self::assertSame('forum/anexos', $editor['image_upload']['directory']);
  }

  /**
   * Activity references reuse the LMS Activity entity and Group access.
   */
  public function testActivityAssociationUsesExistingLmsAndForumEntities(): void {
    $storage = $this->config('field.storage.node.field_lms_activity');
    $syncStorage = $this->syncConfig('field.storage.node.field_lms_activity');
    self::assertSame('lms_activity', $storage['settings']['target_type']);
    self::assertSame($storage['settings'], $syncStorage['settings']);
    self::assertContains('aculta_forum_lms', $storage['dependencies']['enforced']['module']);
    self::assertSame(1, $storage['cardinality']);
    $field = $this->config('field.field.node.aculta_lms_topic.field_lms_activity');
    $syncField = $this->syncConfig('field.field.node.aculta_lms_topic.field_lms_activity');
    self::assertSame('entity_reference', $field['field_type']);
    self::assertSame($field['settings'], $syncField['settings']);
    self::assertLessThanOrEqual(32, strlen($field['field_name']));
    self::assertSame('entity_reference_autocomplete', $this->config('core.entity_form_display.node.aculta_lms_topic.default')['content']['field_lms_activity']['type']);
    self::assertTrue($this->config('core.entity_view_display.node.aculta_lms_topic.full')['hidden']['field_lms_activity']);
    self::assertTrue($this->syncConfig('core.entity_view_display.node.aculta_lms_topic.full')['hidden']['field_lms_activity']);

    $h5pStorage = $this->optionalConfig('field.storage.node.field_h5p_content');
    self::assertSame('aculta_h5p_content', $h5pStorage['settings']['target_type']);
    self::assertSame($h5pStorage['settings'], $this->syncConfig('field.storage.node.field_h5p_content')['settings']);
    self::assertContains('aculta_forum_lms', $h5pStorage['dependencies']['enforced']['module']);
    $h5pField = $this->optionalConfig('field.field.node.aculta_lms_topic.field_h5p_content');
    self::assertSame('entity_reference', $h5pField['field_type']);
    self::assertSame($h5pField['settings'], $this->syncConfig('field.field.node.aculta_lms_topic.field_h5p_content')['settings']);
    self::assertSame('aculta_forum_lms:course_h5p_content', $h5pField['settings']['handler']);
    self::assertLessThanOrEqual(32, strlen($h5pField['field_name']));
    self::assertSame('entity_reference_autocomplete', $this->config('core.entity_form_display.node.aculta_lms_topic.default')['content']['field_h5p_content']['type']);
    self::assertTrue($this->config('core.entity_view_display.node.aculta_lms_topic.full')['hidden']['field_h5p_content']);
    self::assertTrue($this->syncConfig('core.entity_view_display.node.aculta_lms_topic.full')['hidden']['field_h5p_content']);

    $services = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_forum_lms.services.yml')['services'];
    self::assertSame('Drupal\\aculta_forum_lms\\Context\\LmsActivityDiscussionResolver', $services['aculta_forum_lms.activity_discussion_resolver']['class']);
    self::assertSame(['@aculta_forum_lms.activity_discussion_resolver', '@current_user'], $services['Drupal\\aculta_forum_lms\\Hook\\LmsActivityDiscussionHooks']['arguments']);
    self::assertSame('Drupal\\aculta_forum_lms\\Context\\Lmsaculta_H5PDiscussionResolver', $services['aculta_forum_lms.h5p_discussion_resolver']['class']);
    self::assertSame(
      [
        '@aculta_forum_lms.h5p_discussion_resolver',
        '@aculta_lms_h5p.course_access',
        '@current_user',
        '@entity_type.manager',
        '@request_stack',
      ],
      $services['Drupal\\aculta_forum_lms\\Hook\\Lmsaculta_H5PDiscussionHooks']['arguments'],
    );
  }

  /**
   * Only the course/class Group bundles can contain LMS topic Nodes.
   */
  public function testGroupRelationshipsAreBundleSpecificAndWithinCoreLimits(): void {
    $files = [
      'group.relationship_type.lms_course-3abb8caf7f7d526ecedee',
      'group.relationship_type.lms_class-3abb8caf7f7d526ecedeec',
    ];
    foreach ($files as $name) {
      $config = $this->config($name);
      self::assertLessThanOrEqual(32, strlen($config['id']));
      self::assertSame('group_node:aculta_lms_topic', $config['content_plugin']);
      self::assertContains('node.type.aculta_lms_topic', $config['dependencies']['config']);
      self::assertSame(1, $config['plugin_config']['entity_cardinality']);
    }
    self::assertSame('lms_course', $this->config($files[0])['group_type']);
    self::assertSame('lms_class', $this->config($files[1])['group_type']);
  }

  /**
   * Root Config Sync grants the topic relation only through contextual roles.
   */
  public function testConfigSyncRolesMatchTheAdapterRelationships(): void {
    $roleRelationships = [
      'group.role.lms_course-teacher' => 'group.relationship_type.lms_course-3abb8caf7f7d526ecedee',
      'group.role.lms_course-member' => 'group.relationship_type.lms_course-3abb8caf7f7d526ecedee',
      'group.role.lms_course-course_creator' => 'group.relationship_type.lms_course-3abb8caf7f7d526ecedee',
      'group.role.lms_class-member' => 'group.relationship_type.lms_class-3abb8caf7f7d526ecedeec',
    ];
    foreach ($roleRelationships as $roleName => $relationshipName) {
      $role = $this->syncConfig($roleName);
      self::assertContains('aculta_forum_lms', $role['dependencies']['module']);
      self::assertContains($relationshipName, $role['dependencies']['config']);
      self::assertContains('view group_node:aculta_lms_topic entity', $role['permissions']);
    }

    $classSettings = $this->syncConfig('lms_classes.settings');
    self::assertSame(
      'grade students',
      $classSettings['class_permission_mappings']['view group_node:aculta_lms_topic entity'],
    );
  }

  /**
   * The enabled extension list tracks the adapter and Group Node integration.
   */
  public function testCoreExtensionTracksTheLmsAdapterWithoutLegacyForum(): void {
    $path = dirname(__DIR__, 9) . '/config/sync/core.extension.yml';
    self::assertFileExists($path);
    $extensions = Yaml::parseFile($path)['module'];

    self::assertArrayHasKey('aculta_forum_lms', $extensions);
    self::assertArrayHasKey('gnode', $extensions);
    self::assertArrayNotHasKey('forum', $extensions);
  }

  /**
   * Moderation routes require one Group and expose no global View display.
   */
  public function testModerationRoutesAndEmbeddedViewStayContextual(): void {
    $modulePath = dirname(__DIR__, 3);
    $routes = Yaml::parseFile($modulePath . '/aculta_forum_lms.routing.yml');
    self::assertSame('/painel-administrativo/lms/forum', $routes['aculta_forum_lms.moderation_overview']['path']);
    self::assertSame('aculta_forum_lms.moderation_manager:overviewAccess', $routes['aculta_forum_lms.moderation_overview']['requirements']['_custom_access']);

    $queueRoutes = [
      'aculta_forum_lms.comments_published' => '/painel-administrativo/lms/curso/{course}/forum/{group}/comentarios/publicados',
      'aculta_forum_lms.comments_rejected' => '/painel-administrativo/lms/curso/{course}/forum/{group}/comentarios/rejeitados',
      'aculta_forum_lms.comments_pending' => '/painel-administrativo/lms/curso/{course}/forum/{group}/comentarios/aguardando-moderacao',
    ];
    foreach ($queueRoutes as $routeName => $path) {
      self::assertSame($path, $routes[$routeName]['path']);
      self::assertSame('aculta_forum_lms.moderation_manager:courseGroupAccess', $routes[$routeName]['requirements']['_custom_access']);
      self::assertSame('entity:group', $routes[$routeName]['options']['parameters']['course']['type']);
      self::assertSame('entity:group', $routes[$routeName]['options']['parameters']['group']['type']);
    }

    self::assertSame(
      '/painel-administrativo/lms/curso/{course}/forum/{group}/comentario/{comment}/moderacao/{decision}',
      $routes['aculta_forum_lms.comment_moderate']['path'],
    );
    self::assertSame(
      'aculta_forum_lms.moderation_manager:courseCommentModerationAccess',
      $routes['aculta_forum_lms.comment_moderate']['requirements']['_custom_access'],
    );
    self::assertSame(
      '\\Drupal\\aculta_forum_lms\\Form\\LmsCommentModerationForm',
      $routes['aculta_forum_lms.comment_moderate']['defaults']['_form'],
    );
    self::assertSame('entity:group', $routes['aculta_forum_lms.comment_moderate']['options']['parameters']['group']['type']);
    self::assertSame('entity:group', $routes['aculta_forum_lms.comment_moderate']['options']['parameters']['course']['type']);
    self::assertSame('entity:comment', $routes['aculta_forum_lms.comment_moderate']['options']['parameters']['comment']['type']);

    $view = $this->config('views.view.aculta_forum_lms_admin_comments');
    self::assertEqualsCanonicalizing([
      'group.relationship_type.lms_class-3abb8caf7f7d526ecedeec',
      'group.relationship_type.lms_course-3abb8caf7f7d526ecedee',
    ], array_values(array_intersect($view['dependencies']['config'], [
      'group.relationship_type.lms_class-3abb8caf7f7d526ecedeec',
      'group.relationship_type.lms_course-3abb8caf7f7d526ecedee',
      'group.relationship_type.lms_class-3abb8caf7f7d526ecedeeec',
    ])));
    $displayPlugins = array_column($view['display'], 'display_plugin');
    self::assertSame(['embed'], array_values(array_unique(array_slice($displayPlugins, 1))));
    self::assertNotContains('page', $displayPlugins);
    self::assertNotContains('block', $displayPlugins);
    self::assertSame('empty', $view['display']['default']['display_options']['arguments']['gid']['default_action']);
    self::assertSame('aculta_lms_topic', $view['display']['default']['display_options']['filters']['type']['value']['aculta_lms_topic']);
    self::assertSame('entity_operations', $view['display']['default']['display_options']['fields']['operations']['plugin_id']);
  }

  /**
   * Loads one install config file by its config object name.
   */
  private function config(string $name): array {
    $path = dirname(__DIR__, 3) . '/config/install/' . $name . '.yml';
    self::assertFileExists($path);
    return Yaml::parseFile($path);
  }

  /**
   * Loads one optional install config file by its config object name.
   */
  private function optionalConfig(string $name): array {
    $path = dirname(__DIR__, 3) . '/config/optional/' . $name . '.yml';
    self::assertFileExists($path);
    return Yaml::parseFile($path);
  }

  /**
   * Loads the existing shared Node Comment field storage configuration.
   */
  private function storageConfig(string $name): array {
    $path = dirname(__DIR__, 4) . '/aculta_forum/config/install/' . $name . '.yml';
    self::assertFileExists($path);
    return Yaml::parseFile($path);
  }

  /**
   * Loads one root Config Sync YAML file.
   */
  private function syncConfig(string $name): array {
    $path = dirname(__DIR__, 9) . '/config/sync/' . $name . '.yml';
    self::assertFileExists($path);
    return Yaml::parseFile($path);
  }

}
