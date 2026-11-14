<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_magazine\Plugin\Field\FieldFormatter\EditorialCategoryColorFormatter;
use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the fixed editorial category palette.
 */
#[Group('aculta_magazine')]
final class EditorialCategoryColorFormatterTest extends UnitTestCase {

  /**
   * Accepts the four tokens approved for category labels.
   */
  public function testOnlyApprovedColorTokensProduceClasses(): void {
    self::assertSame('red', EditorialCategoryColorFormatter::colorForToken('red'));
    self::assertSame('green', EditorialCategoryColorFormatter::colorForToken('green'));
    self::assertSame('yellow', EditorialCategoryColorFormatter::colorForToken('yellow'));
    self::assertSame('green-dark', EditorialCategoryColorFormatter::colorForToken('green-dark'));
    self::assertNull(EditorialCategoryColorFormatter::colorForToken('blue'));
    self::assertNull(EditorialCategoryColorFormatter::colorForToken(''));
    self::assertNull(EditorialCategoryColorFormatter::colorForToken(NULL));
  }

  /**
   * Preserves the rendered Drupal link as the SDC label slot.
   */
  public function testCategoryLinkIsComposedByEditorialCategorySdc(): void {
    $element = [
      '#type' => 'link',
      '#options' => ['attributes' => ['class' => ['field-name']]],
    ];

    $result = EditorialCategoryColorFormatter::buildComponent($element, 'green-dark');

    self::assertSame('component', $result['#type']);
    self::assertSame('aculta420_v2:editorial-category', $result['#component']);
    self::assertSame(['color' => 'green-dark'], $result['#props']);
    self::assertSame(['label' => $element], $result['#slots']);
    self::assertNull(EditorialCategoryColorFormatter::buildComponent($element, 'invalid'));
  }

  /**
   * Connects the editable color field to the public teaser formatter.
   */
  public function testRevistaTeaserUsesTheClosedPaletteFormatter(): void {
    $sync = dirname(DRUPAL_ROOT) . '/config/sync/';
    $storage = Yaml::decode((string) file_get_contents(
      $sync . 'field.storage.taxonomy_term.field_section_color.yml',
    ));
    $display = Yaml::decode((string) file_get_contents(
      $sync . 'core.entity_view_display.node.revista.teaser.yml',
    ));
    $form = Yaml::decode((string) file_get_contents(
      $sync . 'core.entity_form_display.taxonomy_term.editorial_category.default.yml',
    ));

    self::assertSame(['red', 'green', 'yellow', 'green-dark'], array_column($storage['settings']['allowed_values'], 'value'));
    self::assertSame('options_select', $form['content']['field_section_color']['type']);
    self::assertSame('aculta_editorial_category_color', $display['content']['field_editorial_category']['type']);
    self::assertTrue($display['content']['field_editorial_category']['settings']['link']);
  }

}
