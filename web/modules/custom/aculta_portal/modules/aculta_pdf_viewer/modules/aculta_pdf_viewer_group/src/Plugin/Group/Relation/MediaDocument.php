<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer_group\Plugin\Group\Relation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Plugin\Attribute\GroupRelationType;
use Drupal\group\Plugin\Group\Relation\GroupRelationBase;

/**
 * Provides the Group relation that protects document Media by membership.
 */
#[GroupRelationType(
  id: 'aculta_pdf_viewer_group',
  entity_type_id: 'media',
  label: new TranslatableMarkup('Group Media document'),
  description: new TranslatableMarkup('Adds document Media to a group and applies group access to the Media entity.'),
  reference_label: new TranslatableMarkup('Document'),
  reference_description: new TranslatableMarkup('The document Media attached to this group.'),
  entity_access: TRUE,
  deriver: 'Drupal\\aculta_pdf_viewer_group\\Plugin\\Group\\Relation\\MediaDocumentDeriver'
)]
final class MediaDocument extends GroupRelationBase {

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
      'module' => ['aculta_pdf_viewer_group', 'media'],
      'config' => ['media.type.' . $this->getRelationType()->getEntityBundle()],
    ];
  }

}
