<?php

namespace Drupal\aculta_lms_h5p;

// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Defines the aculta_H5PContent schema handler.
 */
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
class aculta_H5PContentStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    $schema['h5p_content']['indexes'] += [
      'h5p_library' => ['library_id'],
    ];

    return $schema;
  }

}
