<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies the shared SDC contract used by editorial Portal sections.
 */
final class LayoutSectionComponentTest extends UnitTestCase {

  /**
   * Editorial options remain strict SDC props with bounded values.
   */
  public function testEditorialSectionPropsAndThemePreprocess(): void {
    $themeDir = dirname(__DIR__, 3);
    $repositoryRoot = dirname($themeDir, 4);
    $componentPath = $themeDir . '/components/layout-section/layout-section.component.yml';
    $metadata = Yaml::parse((string) file_get_contents($componentPath));

    self::assertFalse($metadata['props']['additionalProperties']);
    self::assertEqualsCanonicalizing(
      ['default', 'city', 'transparency'],
      $metadata['props']['properties']['section_variant']['enum'],
    );
    self::assertEqualsCanonicalizing(
      ['default', 'red', 'green', 'yellow', 'green-dark'],
      $metadata['props']['properties']['section_color']['enum'],
    );

    $story = Yaml::parse((string) file_get_contents($themeDir . '/components/layout-section/layout-section.default.story.yml'));
    self::assertSame('city', $story['props']['section_variant']);
    self::assertSame('yellow', $story['props']['section_color']);

    $preprocess = (string) file_get_contents($themeDir . '/src/Hook/PagePresentationHooks.php');
    self::assertStringContainsString("#[Hook('preprocess_aculta_section')]", $preprocess);
    self::assertStringContainsString("'#component' => 'aculta420_v2:layout-section'", $preprocess);
    self::assertStringContainsString("['default', 'city', 'transparency']", $preprocess);
    self::assertStringContainsString("['default', 'red', 'green', 'yellow', 'green-dark']", $preprocess);
    self::assertStringContainsString("'content' => is_array(", $preprocess);
    self::assertStringContainsString("is_array(\$attributes) ? \$attributes : []", $preprocess);

    $fallback = (string) file_get_contents($repositoryRoot . '/web/modules/custom/aculta_portal/templates/aculta-section.html.twig');
    self::assertStringContainsString('{{ component }}', $fallback);
    self::assertStringContainsString('<section>', $fallback);
  }

  /**
   * The page view keeps Layout Builder blocks inside the shared SDC layout.
   */
  public function testPageFullDisplayUsesUiPatternsSectionLayout(): void {
    $themeDir = dirname(__DIR__, 3);
    $repositoryRoot = dirname($themeDir, 4);
    foreach (['default', 'full'] as $viewMode) {
      $display = Yaml::parse((string) file_get_contents(
        $repositoryRoot . '/config/sync/core.entity_view_display.node.page.' . $viewMode . '.yml',
      ));
      $section = $display['third_party_settings']['layout_builder']['sections'][0];

      self::assertSame('ui_patterns:aculta420_v2:layout_section', $section['layout_id'], $viewMode);
      self::assertSame('aculta420_v2:layout-section', $section['layout_settings']['ui_patterns']['component_id'], $viewMode);
      self::assertSame([], $section['layout_settings']['ui_patterns']['props'], $viewMode);
      self::assertSame(['content'], array_values(array_unique(array_column($section['components'], 'region'))), $viewMode);
      self::assertContains('ui_patterns_layouts', $display['dependencies']['module'], $viewMode);
    }
  }

}
