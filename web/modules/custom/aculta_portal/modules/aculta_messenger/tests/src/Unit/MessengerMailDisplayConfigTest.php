<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Protects mail and notice displays from exposing Entity Print controls.
 */
#[Group('aculta_messenger')]
final class MessengerMailDisplayConfigTest extends TestCase {

  /**
   * Tests that install defaults and Config Sync hide the Entity Print fields.
   */
  public function testEntityPrintFieldsStayHidden(): void {
    $module_root = dirname(__DIR__, 3);
    $project_root = dirname($module_root, 6);
    $config_names = [
      'core.entity_view_display.message.aculta_msg_forum_published.mail_body',
      'core.entity_view_display.message.aculta_msg_forum_published.mail_subject',
      'core.entity_view_display.message.aculta_msg_friendship.mail_body',
      'core.entity_view_display.message.aculta_msg_friendship.mail_subject',
      'core.entity_view_display.message.aculta_msg_private_notification.mail_body',
      'core.entity_view_display.message.aculta_msg_private_notification.mail_subject',
      'core.entity_view_display.node.aculta_notice.default',
    ];
    $directories = [
      $module_root . '/config/install',
      $project_root . '/config/sync',
    ];

    foreach ($directories as $directory) {
      foreach ($config_names as $name) {
        $path = $directory . '/' . $name . '.yml';
        self::assertFileExists($path);
        $config = self::yamlArray($path);
        $hidden = self::arrayAt($config, 'hidden');
        foreach (['entity_print_view_epub', 'entity_print_view_pdf', 'entity_print_view_word_docx'] as $field) {
          self::assertTrue($hidden[$field] ?? FALSE, sprintf('%s must stay hidden in %s.', $field, $name));
        }
      }
    }
  }

  /**
   * Tests that enqueue attempts use valid Integer storage settings.
   */
  public function testEnqueueAttemptsIntegerConfigUsesStorageAndInstanceSettings(): void {
    $module_root = dirname(__DIR__, 3);
    $project_root = dirname($module_root, 6);
    foreach ([
      $module_root . '/config/install',
      $project_root . '/config/sync',
    ] as $directory) {
      $storage_path = $directory . '/field.storage.message.field_aculta_enqueue_attempts.yml';
      self::assertFileExists($storage_path);
      $storage = self::yamlArray($storage_path);
      self::assertSame('integer', $storage['type']);
      self::assertSame(['unsigned' => FALSE, 'size' => 'normal'], $storage['settings']);

      $field_path = $directory . '/field.field.message.aculta_msg_friendship.field_aculta_enqueue_attempts.yml';
      self::assertFileExists($field_path);
      $field = self::yamlArray($field_path);
      self::assertSame('integer', $field['field_type']);
      self::assertSame('field.storage.message.field_aculta_enqueue_attempts', self::arrayAt(self::arrayAt($field, 'dependencies'), 'config')[0]);
    }
  }

  /**
   * Tests that the friendship event is hidden from both email displays.
   */
  public function testFriendshipEventIsNotRenderedInEmail(): void {
    $module_root = dirname(__DIR__, 3);
    $project_root = dirname($module_root, 6);
    foreach ([
      $module_root . '/config/install',
      $project_root . '/config/sync',
    ] as $directory) {
      foreach ([
        'core.entity_view_display.message.aculta_msg_friendship.mail_body',
        'core.entity_view_display.message.aculta_msg_friendship.mail_subject',
      ] as $name) {
        $path = $directory . '/' . $name . '.yml';
        self::assertFileExists($path);
        $config = self::yamlArray($path);
        $hidden = self::arrayAt($config, 'hidden');
        self::assertTrue($hidden['field_aculta_friendship_event'] ?? FALSE);
        $dependencies = self::arrayAt(self::arrayAt($config, 'dependencies'), 'config');
        self::assertContains(
          'field.field.message.aculta_msg_friendship.field_aculta_friendship_event',
          $dependencies,
        );
      }
    }
  }

  /**
   * Parses a YAML configuration file and validates its array shape.
   *
   * @return array<mixed>
   *   Parsed configuration data.
   */
  private static function yamlArray(string $path): array {
    $config = Yaml::parseFile($path);
    self::assertIsArray($config);
    return $config;
  }

  /**
   * Returns a nested configuration array after checking its runtime shape.
   *
   * @param array<mixed> $parent
   *   Parsed configuration or a nested config section.
   *
   * @return array<mixed>
   *   Nested configuration values.
   */
  private static function arrayAt(array $parent, int|string $key): array {
    self::assertArrayHasKey($key, $parent);
    self::assertIsArray($parent[$key]);
    return $parent[$key];
  }

}
