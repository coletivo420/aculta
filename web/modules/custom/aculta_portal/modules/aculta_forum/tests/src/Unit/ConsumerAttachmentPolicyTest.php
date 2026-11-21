<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Decision: only the Forum accepts attachments; consumers accept embedded media only.
 */
#[CoversNothing]
#[Group('aculta_forum')]
final class ConsumerAttachmentPolicyTest extends TestCase {

  /**
   * The shared comment bundle used by Wiki, Revista, LMS and general comments has no file or attachment field.
   */
  public function testSharedCommentBundleHasNoAttachmentField(): void {
    $configDir = dirname(__DIR__, 9) . '/config/sync';
    self::assertDirectoryExists($configDir);
    $fields = glob($configDir . '/field.field.comment.aculta_discussion_post.*.yml') ?: [];
    self::assertNotEmpty($fields);
    foreach ($fields as $file) {
      $config = Yaml::parseFile($file);
      self::assertIsArray($config);
      $fieldType = $config['field_type'] ?? NULL;
      self::assertFalse(in_array($fieldType, ['file', 'image'], TRUE), basename($file));
      $settings = $config['settings'] ?? NULL;
      $handler = is_array($settings) ? ($settings['handler_settings'] ?? NULL) : NULL;
      $bundles = is_array($handler) ? ($handler['target_bundles'] ?? NULL) : NULL;
      $encoded = json_encode($bundles) ?: '';
      self::assertStringNotContainsString('forum_attachment', $encoded, basename($file));
    }
  }

}
