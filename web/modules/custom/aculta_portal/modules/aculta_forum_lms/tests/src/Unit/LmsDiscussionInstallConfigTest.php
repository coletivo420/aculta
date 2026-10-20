<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates the LMS topic's install configuration boundaries.
 */
#[Group('aculta_forum_lms')]
final class LmsDiscussionInstallConfigTest extends UnitTestCase {

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
   * Loads one install config file by its config object name.
   */
  private function config(string $name): array {
    $path = dirname(__DIR__, 3) . '/config/install/' . $name . '.yml';
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

}
