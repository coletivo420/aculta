<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\TermInterface;

/**
 * Renders an editorial category label with its approved theme color token.
 */
#[FieldFormatter(
  id: 'aculta_editorial_category_color',
  label: new TranslatableMarkup('Editorial category label with theme color'),
  description: new TranslatableMarkup('Displays a category label using one of the fixed ACULTA theme color tokens.'),
  field_types: ['entity_reference'],
)]
final class EditorialCategoryColorFormatter extends EntityReferenceLabelFormatter {

  /**
   * Color tokens approved for editorial category labels.
   *
   * @var string[]
   */
  private const COLOR_TOKENS = ['red', 'green', 'yellow', 'green-dark'];

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    foreach ($items as $delta => $item) {
      $term = $item->entity;
      if (!$term instanceof TermInterface || $term->bundle() !== 'editorial_category' || !$term->hasField('field_section_color')) {
        continue;
      }
      $element = $elements[$delta] ?? NULL;
      if (!is_array($element) || ($element['#type'] ?? NULL) !== 'link') {
        continue;
      }
      $elements[$delta] = self::applyColorClass($element, $term->get('field_section_color')->value);
    }
    return $elements;
  }

  /**
   * Returns a CSS class for approved tokens or NULL for empty/invalid data.
   */
  public static function classForColor(mixed $value): ?string {
    return is_string($value) && in_array($value, self::COLOR_TOKENS, TRUE)
      ? 'aculta-category--' . $value
      : NULL;
  }

  /**
   * Applies a palette class to a reference link while preserving its classes.
   *
   * @param array<string, mixed> $element
   *   Render array for a category reference.
   * @param mixed $value
   *   Stored color token.
   *
   * @return array<string, mixed>
   *   Updated render array.
   */
  public static function applyColorClass(array $element, mixed $value): array {
    $class = self::classForColor($value);
    if ($class === NULL || ($element['#type'] ?? NULL) !== 'link') {
      return $element;
    }
    $classes = $element['#options']['attributes']['class'] ?? [];
    $classes = is_array($classes) ? $classes : [$classes];
    $element['#options']['attributes']['class'] = array_values(array_unique([
      ...$classes,
      'aculta-category',
      $class,
    ]));
    return $element;
  }

}
