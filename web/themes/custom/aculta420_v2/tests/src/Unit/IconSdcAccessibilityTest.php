<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies decorative and informative icon accessibility contracts.
 */
final class IconSdcAccessibilityTest extends UnitTestCase {

  /**
   * Ensures icon labels are optional and mapped to a named image role.
   */
  public function testIconSupportsDecorativeAndInformativeUse(): void {
    $themeDir = dirname(__DIR__, 3);
    $componentPath = $themeDir . '/components/icon/icon.component.yml';
    $metadata = Yaml::decode((string) file_get_contents($componentPath));
    self::assertIsArray($metadata);
    $props = $metadata['props'] ?? NULL;
    self::assertIsArray($props);
    $properties = $props['properties'] ?? NULL;
    self::assertIsArray($properties);
    $label = $properties['label'] ?? NULL;
    self::assertIsArray($label);
    self::assertSame('string', $label['type'] ?? NULL);
    self::assertSame(1, $label['minLength'] ?? NULL);
    self::assertSame(128, $label['maxLength'] ?? NULL);
    self::assertSame(['name'], $props['required'] ?? NULL);

    $template = file_get_contents($themeDir . '/components/icon/icon.twig');
    self::assertIsString($template);
    self::assertStringContainsString('role="img" aria-label="{{ label|trim }}"', $template);
    self::assertStringContainsString('aria-hidden="true"', $template);

    $storyPath = $themeDir . '/components/icon/icon.informative.story.yml';
    self::assertFileExists($storyPath);
    $story = Yaml::decode((string) file_get_contents($storyPath));
    self::assertIsArray($story);
    $storyProps = $story['props'] ?? NULL;
    self::assertIsArray($storyProps);
    self::assertSame('Conteúdo em destaque', $storyProps['label'] ?? NULL);
  }

}
