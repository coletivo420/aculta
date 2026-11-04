<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources\Hook;

use Drupal\aculta_lms_resources\Cleanup\CourseDocumentCleanupInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\group\Entity\GroupRelationshipInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Coordinates cleanup of detached course-document Media.
 */
final class CourseDocumentCleanupHooks {

  private const COURSE_DOCUMENT_RELATIONSHIPS = [
    'aculta_document_viewer:course_document',
    'aculta_document_viewer_group:course_document',
  ];

  public function __construct(
    #[Autowire(service: 'aculta_lms_resources.course_document_cleanup')]
    private readonly CourseDocumentCleanupInterface $cleanup,
  ) {}

  /**
   * Cleans a document only after its course relationship has been removed.
   */
  #[Hook('group_relationship_delete')]
  public function relationshipDeleted(GroupRelationshipInterface $relationship): void {
    if (!in_array($relationship->getPluginId(), self::COURSE_DOCUMENT_RELATIONSHIPS, TRUE)) {
      return;
    }

    $mediaId = (int) $relationship->getEntityId();
    if ($mediaId > 0) {
      $this->cleanup->removeDetachedDocument($mediaId);
    }
  }

}
