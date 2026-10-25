<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\views\FieldViewsDataProvider;

/** Integrates H5P field references with the Drupal Views data layer. */
final class aculta_H5PViewsHooks {

  public function __construct(
    private readonly FieldViewsDataProvider $fieldViewsDataProvider,
  ) {}

  /** Implements hook_field_views_data(). */
  #[Hook('field_views_data')]
  public function fieldViewsData(FieldStorageConfigInterface $fieldStorage): array {
    $data = $this->fieldViewsDataProvider->defaultFieldImplementation($fieldStorage);
    foreach (array_keys($data) as $tableName) {
      $data[$tableName][$fieldStorage->getName() . '_h5p_content_id']['relationship'] = [
        'id' => 'standard',
        'base' => 'h5p_content',
        'entity type' => 'aculta_h5p_content',
        'base field' => 'id',
        'label' => new TranslatableMarkup('H5P relacionado a @field', ['@field' => $fieldStorage->getName()]),
      ];
    }
    return $data;
  }

}
