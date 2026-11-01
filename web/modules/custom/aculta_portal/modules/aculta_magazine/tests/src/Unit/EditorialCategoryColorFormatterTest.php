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
    self::assertSame('aculta-category--red', EditorialCategoryColorFormatter::classForColor('red'));
    self::assertSame('aculta-category--green', EditorialCategoryColorFormatter::classForColor('green'));
    self::assertSame('aculta-category--yellow', EditorialCategoryColorFormatter::classForColor('yellow'));
    self::assertSame('aculta-category--green-dark', EditorialCategoryColorFormatter::classForColor('green-dark'));
    self::assertNull(EditorialCategoryColorFormatter::classForColor('blue'));
    self::assertNull(EditorialCategoryColorFormatter::classForColor(''));
    self::assertNull(EditorialCategoryColorFormatter::classForColor(NULL));
  }

  /**
   * Preserves existing link classes when adding a color.
   */
  public function testColorIsAddedToCategoryLinkWithoutDroppingExistingClasses(): void {
    $element = [
      '#type' => 'link',
      '#options' => ['attributes' => ['class' => ['field-name']]],
    ];

    $result = EditorialCategoryColorFormatter::applyColorClass($element, 'green-dark');
    $expectedTokens = [
      'field-name',
      'aculta-category',
      EditorialCategoryColorFormatter::classForColor('green-dark'),
    ];

    self::assertSame('link', $result['#type']);
    self::assertSame($expectedTokens, $result['#options']['attributes']['class']);
    self::assertSame($element, EditorialCategoryColorFormatter::applyColorClass($element, 'invalid'));
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
    self::assertSame(TRUE, $display['content']['field_editorial_category']['settings']['link']);
  }

}
