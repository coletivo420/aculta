<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer_group\Plugin\Group\Relation;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeInterface;
use Drupal\media\Entity\MediaType;

/**
 * Derives the Group relation for the document Media bundle.
 */
final class MediaDocumentDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    assert($base_plugin_definition instanceof GroupRelationTypeInterface);
    $this->derivatives = [];

    foreach (MediaType::loadMultiple() as $id => $_media_type) {
      if ($id !== 'course_document') {
        continue;
      }

      $derivative = clone $base_plugin_definition;
      $derivative->set('entity_bundle', $id);
      $derivative->set('label', t('Group Media document'));
      $derivative->set('description', t('Adds document Media to a group and applies group access to the Media entity.'));
      $this->derivatives[$id] = $derivative;
    }

    return $this->derivatives;
  }

}
