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

    self::assertNotNull($result);
    self::assertSame('portal_component', self::valueAt($result, '#theme'));
    self::assertSame('editorial-category', self::valueAt($result, '#name'));
    self::assertSame(['color' => 'green-dark'], self::valueAt($result, '#props'));
    self::assertSame(['label' => $element], self::valueAt($result, '#slots'));
    self::assertNull(EditorialCategoryColorFormatter::buildComponent($element, 'invalid'));
  }

  /**
   * Connects the editable color field to the public teaser formatter.
   */
  public function testRevistaTeaserUsesTheClosedPaletteFormatter(): void {
    $sync = dirname(DRUPAL_ROOT) . '/config/sync/';
    $storage = self::readYamlFile($sync . 'field.storage.taxonomy_term.field_section_color.yml');
    $display = self::readYamlFile($sync . 'core.entity_view_display.node.revista.teaser.yml');
    $form = self::readYamlFile($sync . 'core.entity_form_display.taxonomy_term.editorial_category.default.yml');

    self::assertSame(
      ['red', 'green', 'yellow', 'green-dark'],
      array_column(self::arrayAt($storage, 'settings', 'allowed_values'), 'value'),
    );
    self::assertSame('options_select', self::valueAt($form, 'content', 'field_section_color', 'type'));
    self::assertSame('aculta_editorial_category_color', self::valueAt($display, 'content', 'field_editorial_category', 'type'));
    self::assertTrue(self::valueAt($display, 'content', 'field_editorial_category', 'settings', 'link'));
  }

  /**
   * Reads and decodes a YAML file, rejecting unreadable or non-mapping input.
   *
   * @return array<array-key, mixed>
   *   Decoded YAML mapping.
   */
  private static function readYamlFile(string $path): array {
    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read file "%s".', $path));
    }
    $decoded = Yaml::decode($contents);
    if (!is_array($decoded)) {
      throw new \UnexpectedValueException(sprintf('YAML file "%s" must contain a mapping.', $path));
    }

    return $decoded;
  }

  /**
   * Reads a config value while validating every parent mapping.
   */
  private static function valueAt(mixed $data, string ...$path): mixed {
    $value = $data;
    foreach ($path as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        throw new \UnexpectedValueException(sprintf('Missing config key "%s".', $key));
      }
      $value = $value[$key];
    }

    return $value;
  }

  /**
   * Reads a config value that must be a nested mapping.
   *
   * @return array<array-key, mixed>
   *   Decoded nested mapping.
   */
  private static function arrayAt(mixed $data, string ...$path): array {
    $value = self::valueAt($data, ...$path);
    if (!is_array($value)) {
      throw new \UnexpectedValueException(sprintf('Config path "%s" must contain a mapping.', implode('.', $path)));
    }

    return $value;
  }

}
