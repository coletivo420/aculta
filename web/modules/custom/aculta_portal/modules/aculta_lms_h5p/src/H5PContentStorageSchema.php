<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Defines the H5PContent schema handler.
 */
class H5PContentStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    $schema['h5p_content']['indexes'] += array(
      'h5p_library' => array('library_id'),
    );

    return $schema;
  }
}
