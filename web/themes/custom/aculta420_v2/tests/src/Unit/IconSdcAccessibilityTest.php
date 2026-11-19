<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons_patterns\Plugin\UiPatterns\PropType\IconPropType;
use Drupal\ui_patterns\Attribute\PropType;
use Drupal\ui_patterns\SchemaManager\Canonicalizer;
use Drupal\ui_patterns\SchemaManager\CompatibilityChecker;
use Drupal\ui_patterns\SchemaManager\ReferencesResolver;
use Psr\Log\LoggerInterface;

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
    self::assertSame([
      ['required' => ['name']],
      ['required' => ['icon']],
    ], $props['anyOf'] ?? NULL);
    $iconProp = $properties['icon'] ?? NULL;
    self::assertIsArray($iconProp);
    $iconProperties = $iconProp['properties'] ?? NULL;
    self::assertIsArray($iconProperties);
    $packIdSchema = $iconProperties['pack_id'] ?? NULL;
    self::assertIsArray($packIdSchema);
    self::assertSame(
      ['aculta_phosphor_regular', 'aculta_phosphor_duotone', 'aculta_brocolis'],
      $packIdSchema['enum'] ?? NULL,
    );
    self::assertSame(['pack_id', 'icon_id'], $iconProp['required'] ?? NULL);

    $attributes = (new \ReflectionClass(IconPropType::class))->getAttributes(PropType::class);
    self::assertCount(1, $attributes);
    $iconPropType = $attributes[0]->newInstance();
    $compatibilityChecker = new CompatibilityChecker(
      new Canonicalizer(),
      new ReferencesResolver($this->createMock(LoggerInterface::class)),
    );
    self::assertTrue($compatibilityChecker->isCompatible($iconProp, $iconPropType->schema));

    $template = file_get_contents($themeDir . '/components/icon/icon.twig');
    self::assertIsString($template);
    self::assertStringContainsString('role="img" aria-label="{{ label|trim }}"', $template);
    self::assertStringContainsString('aria-hidden="true"', $template);
    self::assertStringContainsString('icon(icon_value.pack_id, icon_value.icon_id', $template);
    self::assertStringContainsString("icon_value.icon_id|slice(-8) == '-duotone'", $template);
    self::assertStringContainsString("' aculta-icon--' ~ icon_variant", $template);

    $storyPath = $themeDir . '/components/icon/icon.informative.story.yml';
    self::assertFileExists($storyPath);
    $story = Yaml::decode((string) file_get_contents($storyPath));
    self::assertIsArray($story);
    $storyProps = $story['props'] ?? NULL;
    self::assertIsArray($storyProps);
    self::assertSame('Conteúdo em destaque', $storyProps['label'] ?? NULL);
    $storyIcon = $storyProps['icon'] ?? NULL;
    self::assertIsArray($storyIcon);
    self::assertSame('aculta_phosphor_regular', $storyIcon['pack_id'] ?? NULL);
    self::assertSame('star', $storyIcon['icon_id'] ?? NULL);

    $brocolisStoryPath = $themeDir . '/components/icon/icon.brocolis-duotone-informative.story.yml';
    self::assertFileExists($brocolisStoryPath);
    $brocolisStory = Yaml::decode((string) file_get_contents($brocolisStoryPath));
    self::assertIsArray($brocolisStory);
    $brocolisStoryProps = $brocolisStory['props'] ?? NULL;
    self::assertIsArray($brocolisStoryProps);
    self::assertSame('Marca ACULTA Brócolis em duotone', $brocolisStoryProps['label'] ?? NULL);
    $brocolisStoryIcon = $brocolisStoryProps['icon'] ?? NULL;
    self::assertIsArray($brocolisStoryIcon);
    self::assertSame('aculta_brocolis', $brocolisStoryIcon['pack_id'] ?? NULL);
    self::assertSame('aculta-brocolis-duotone', $brocolisStoryIcon['icon_id'] ?? NULL);
  }

}
