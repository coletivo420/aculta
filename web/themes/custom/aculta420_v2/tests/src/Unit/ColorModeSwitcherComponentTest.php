<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * Checks the Color Mode Switcher SDC contract and published stories.
 *
 * @group aculta420_v2
 */
final class ColorModeSwitcherComponentTest extends UnitTestCase {

  /**
   * Ensures the manifest is strict and the expected states are published. */
  public function testManifestStoriesAndIconComposition(): void {
    $theme = dirname(__DIR__, 3);
    $component = $theme . '/components/color-mode-switcher';
    $manifest = Yaml::decode((string) file_get_contents($component . '/color-mode-switcher.component.yml'));
    if (!is_array($manifest) || !is_array($manifest['props'] ?? NULL)) {
      self::fail('The component manifest must decode with a props object.');
    }
    $props = $manifest['props'];
    self::assertFalse($props['additionalProperties'] ?? TRUE);
    $properties = $props['properties'] ?? NULL;
    if (!is_array($properties)) {
      self::fail('The component manifest must declare typed properties.');
    }
    $mode = $properties['mode'] ?? NULL;
    $context = $properties['context'] ?? NULL;
    $appearance = $properties['appearance'] ?? NULL;
    if (!is_array($mode) || !is_array($context) || !is_array($appearance)) {
      self::fail('The mode, context and appearance property schemas must be objects.');
    }
    self::assertSame(['light', 'auto', 'dark'], $mode['enum'] ?? NULL);
    self::assertSame(['site', 'library', 'demo'], $context['enum'] ?? NULL);
    self::assertSame(['auto', 'light', 'dark'], $appearance['enum'] ?? NULL);

    foreach ([
      'color-mode-switcher.light.story.yml',
      'color-mode-switcher.dark.story.yml',
      'color-mode-switcher.auto.story.yml',
      'color-mode-switcher.open-light.story.yml',
      'color-mode-switcher.open-dark.story.yml',
      'color-mode-switcher.states.story.yml',
    ] as $storyFile) {
      self::assertFileExists($component . '/' . $storyFile);
      $story = Yaml::decode((string) file_get_contents($component . '/' . $storyFile));
      if (!is_array($story) || !is_array($story['props'] ?? NULL)) {
        self::fail(sprintf('Story %s must define props.', $storyFile));
      }
      self::assertSame('library', $story['props']['context'] ?? NULL);
    }

    $template = (string) file_get_contents($component . '/color-mode-switcher.twig');
    self::assertStringContainsString("include('aculta420_v2:button'", $template);
    self::assertStringContainsString("include('aculta420_v2:icon'", $template);
    self::assertStringContainsString("'sun'", $template);
    self::assertStringContainsString("'circle-half'", $template);
    self::assertStringContainsString("'moon'", $template);
    self::assertStringContainsString("'check'", $template);
    self::assertStringContainsString('type="radio"', $template);
  }

}
