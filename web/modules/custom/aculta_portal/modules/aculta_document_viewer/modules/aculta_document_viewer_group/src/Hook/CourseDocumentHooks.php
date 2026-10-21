<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer_group\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media\MediaInterface;

/**
 * Lifecycle guards for private course documents.
 */
final class CourseDocumentHooks {

  /**
   * Prevents private course Media from becoming globally visible if detached.
   */
  #[Hook('entity_presave')]
  public function keepCourseDocumentsUnpublished(EntityInterface $entity): void {
    if ($entity instanceof MediaInterface && $entity->bundle() === 'course_document') {
      $entity->setUnpublished();
    }
  }

}
