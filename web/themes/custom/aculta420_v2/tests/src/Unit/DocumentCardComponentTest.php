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
    self::assertSame(FALSE, $component['props']['additionalProperties']);
    self::assertSame(['title', 'url'], $component['props']['required']);
    self::assertArrayHasKey('category', $component['slots']);
    self::assertArrayHasKey('summary', $component['slots']);
    self::assertArrayHasKey('attachment', $component['slots']);
    self::assertFileExists($componentDir . '/document-card.twig');
    self::assertFileExists($componentDir . '/document-card.css');
    self::assertFileExists($componentDir . '/document-card.default.story.yml');
  }

  /**
   * Ensures Drupal field render arrays reach the SDC slots unchanged.
   */
  public function testDocumentTeaserAdapterPreservesRenderedFields(): void {
    $themeDir = dirname(__DIR__, 3);
    $template = (string) file_get_contents($themeDir . '/templates/content/node--document--teaser.html.twig');
    self::assertStringContainsString("embed 'aculta420_v2:document-card'", $template);
    self::assertStringContainsString('category_render: content.field_category', $template);
    self::assertStringContainsString('summary_render: content.body', $template);
    self::assertStringContainsString('attachment_render: content.field_document', $template);
    self::assertStringNotContainsString('|raw', $template);
  }

  /**
   * Ensures the documents View uses the shared responsive SDC grid.
   */
  public function testDocumentsViewUsesUiPatternsGrid(): void {
    $root = dirname(__DIR__, 7);
    $view = Yaml::parseFile($root . '/config/sync/views.view.aculta_documents.yml');
    $options = $view['display']['default']['display_options'];
    self::assertSame('ui_patterns', $options['style']['type']);
    self::assertSame('aculta420_v2:layout-grid', $options['style']['options']['ui_patterns']['ui_patterns']['component_id']);
    self::assertSame('view_rows', $options['style']['options']['ui_patterns']['ui_patterns']['slots']['content']['sources'][0]['source_id']);
    self::assertSame('entity:node', $options['row']['type']);
    self::assertSame('teaser', $options['row']['options']['view_mode']);
    self::assertSame('', $options['css_class']);
  }

}
