<?php

declare(strict_types=1);

namespace Drupal\aculta_index_course_document_access_test\Plugin\Group\Relation;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeInterface;
use Drupal\media\Entity\MediaType;

/**
 * Restricts the fixture relation to the course_document Media bundle.
 */
final class CourseDocumentDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    assert($base_plugin_definition instanceof GroupRelationTypeInterface);
    $this->derivatives = [];
    if (MediaType::load('course_document') !== NULL) {
      $derivative = clone $base_plugin_definition;
      $derivative->set('entity_bundle', 'course_document');
      $this->derivatives['course_document'] = $derivative;
    }
    return $this->derivatives;
  }

}
