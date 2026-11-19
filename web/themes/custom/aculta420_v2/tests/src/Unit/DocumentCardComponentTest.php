<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Protects the document teaser SDC adapter and its View integration contract.
 *
 * @group aculta420_v2
 */
final class DocumentCardComponentTest extends UnitTestCase {

  /**
   * Ensures the document card has a strict SDC schema and local assets.
   */
  public function testDocumentCardContract(): void {
    $themeDir = dirname(__DIR__, 3);
    $componentDir = $themeDir . '/components/document-card';
    $component = Yaml::parseFile($componentDir . '/document-card.component.yml');
    self::assertFalse(self::valueAtPath($component, 'props', 'additionalProperties'));
    self::assertSame(['title', 'url'], self::valueAtPath($component, 'props', 'required'));
    $slots = self::arrayAtPath($component, 'slots');
    self::assertArrayHasKey('category', $slots);
    self::assertArrayHasKey('summary', $slots);
    self::assertArrayHasKey('attachment', $slots);
    self::assertFileExists($componentDir . '/document-card.twig');
    self::assertFileExists($componentDir . '/document-card.css');
    self::assertFileExists($componentDir . '/document-card.default.story.yml');
  }

  /**
   * Ensures Drupal field render arrays reach the SDC slots unchanged.
   */
  public function testDocumentTeaserCompositionPreservesRenderedFields(): void {
    $themeDir = dirname(__DIR__, 3);
    $hook = (string) file_get_contents($themeDir . '/src/Hook/PagePresentationHooks.php');
    self::assertStringContainsString("'#component' => 'aculta420_v2:document-card'", $hook);
    self::assertStringContainsString("'category' => \$content['field_category'] ?? []", $hook);
    self::assertStringContainsString("'summary' => \$content['body'] ?? []", $hook);
    self::assertStringContainsString("'attachment' => \$content['field_document'] ?? []", $hook);
    self::assertStringContainsString("CacheableMetadata::createFromRenderArray(\$variables['elements'] ?? [])", $hook);
    self::assertStringNotContainsString('node--document--teaser.html.twig', $hook);
    self::assertFileDoesNotExist($themeDir . '/templates/content/node--document--teaser.html.twig');
  }

  /**
   * Ensures the documents View uses the shared responsive SDC grid.
   */
  public function testDocumentsViewUsesUiPatternsGrid(): void {
    $root = dirname(__DIR__, 7);
    $view = Yaml::parseFile($root . '/config/sync/views.view.aculta_documents.yml');
    $options = self::arrayAtPath($view, 'display', 'default', 'display_options');
    self::assertSame('ui_patterns', self::valueAtPath($options, 'style', 'type'));
    self::assertSame('aculta420_v2:layout-grid', self::valueAtPath($options, 'style', 'options', 'ui_patterns', 'ui_patterns', 'component_id'));
    self::assertSame('cards-wide', self::valueAtPath($options, 'style', 'options', 'ui_patterns', 'ui_patterns', 'props', 'preset', 'source', 'value'));
    self::assertSame('view_rows', self::valueAtPath($options, 'style', 'options', 'ui_patterns', 'ui_patterns', 'slots', 'content', 'sources', '0', 'source_id'));
    self::assertSame('entity:node', self::valueAtPath($options, 'row', 'type'));
    self::assertSame('teaser', self::valueAtPath($options, 'row', 'options', 'view_mode'));
    self::assertSame('', self::valueAtPath($options, 'css_class'));
  }

  /**
   * Returns a nested YAML value after checking every container and key.
   *
   * @param string ...$path
   *   Keys to traverse from the parsed YAML value.
   */
  private static function valueAtPath(mixed $value, string ...$path): mixed {
    foreach ($path as $key) {
      self::assertIsArray($value);
      self::assertArrayHasKey($key, $value);
      $value = $value[$key];
    }

    return $value;
  }

  /**
   * Returns a nested YAML array after verifying its structure.
   *
   * @param string ...$path
   *   Keys to traverse from the parsed YAML value.
   *
   * @return array<mixed>
   *   The array at the requested path.
   */
  private static function arrayAtPath(mixed $value, string ...$path): array {
    $value = self::valueAtPath($value, ...$path);
    self::assertIsArray($value);

    return $value;
  }

}
