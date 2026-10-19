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

}
