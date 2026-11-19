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
      $component = self::buildComponent($element, $term->get('field_section_color')->value);
      if ($component !== NULL) {
        $elements[$delta] = $component;
      }
    }
    return $elements;
  }

  /**
   * Returns an approved color token or NULL for empty/invalid data.
   */
  public static function colorForToken(mixed $value): ?string {
    return is_string($value) && in_array($value, self::COLOR_TOKENS, TRUE) ? $value : NULL;
  }

  /**
   * Composes a Drupal reference link inside the editorial-category SDC.
   *
   * @param array<string, mixed> $element
   *   Render array for a category reference.
   * @param mixed $value
   *   Stored color token.
   *
   * @return array<string, mixed>|null
   *   SDC render array, or NULL when the token or element is unsupported.
   */
  public static function buildComponent(array $element, mixed $value): ?array {
    $color = self::colorForToken($value);
    if ($color === NULL || ($element['#type'] ?? NULL) !== 'link') {
      return NULL;
    }
    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:editorial-category',
      '#props' => ['color' => $color],
      '#slots' => ['label' => $element],
    ];
  }

}
