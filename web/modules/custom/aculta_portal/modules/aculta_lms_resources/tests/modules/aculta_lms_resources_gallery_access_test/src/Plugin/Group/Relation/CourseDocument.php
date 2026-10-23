<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources_gallery_access_test\Plugin\Group\Relation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Plugin\Attribute\GroupRelationType;
use Drupal\group\Plugin\Group\Relation\GroupRelationBase;

/**
 * Test fixture for the PDF owner's course document relation.
 */
#[GroupRelationType(
  id: 'aculta_pdf_viewer_group',
  entity_type_id: 'media',
  label: new TranslatableMarkup('Group Media document'),
  description: new TranslatableMarkup('Test relation for course documents.'),
  reference_label: new TranslatableMarkup('Document'),
  reference_description: new TranslatableMarkup('Course document.'),
  entity_access: TRUE,
  enforced: FALSE,
  deriver: 'Drupal\\aculta_lms_resources_gallery_access_test\\Plugin\\Group\\Relation\\CourseDocumentDeriver',
)]
final class CourseDocument extends GroupRelationBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $configuration = parent::defaultConfiguration();
    $configuration['entity_cardinality'] = 1;
    return $configuration;
  }

}
