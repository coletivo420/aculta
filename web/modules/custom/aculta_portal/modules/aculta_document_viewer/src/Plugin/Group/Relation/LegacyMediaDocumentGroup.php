<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Plugin\Group\Relation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Plugin\Attribute\GroupRelationType;
use Drupal\group\Plugin\Group\Relation\GroupRelationBase;

/**
 * Provides backward compatibility for the legacy relationship plugin ID.
 */
#[GroupRelationType(
  id: 'aculta_document_viewer_group',
  entity_type_id: 'media',
  label: new TranslatableMarkup('Group Media document (legacy)'),
  description: new TranslatableMarkup('Backward compatibility alias for course document group relation.'),
  reference_label: new TranslatableMarkup('Document'),
  reference_description: new TranslatableMarkup('The document Media attached to this group.'),
  entity_access: TRUE,
  enforced: FALSE,
  deriver: 'Drupal\aculta_document_viewer\Plugin\Group\Relation\MediaDocumentDeriver'
)]
final class LegacyMediaDocumentGroup extends GroupRelationBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $configuration = parent::defaultConfiguration();
    $configuration['entity_cardinality'] = 1;
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return [
      'module' => ['aculta_document_viewer', 'media'],
      'config' => ['media.type.' . $this->getRelationType()->getEntityBundle()],
    ];
  }

}
