<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Resources\Cleanup;

use Drupal\aculta_file_uploader\Cleanup\FileCleanupStatus;
use Drupal\aculta_file_uploader\Cleanup\UnusedFileCleaner;
use Drupal\aculta_portal\Cleanup\UnusedMediaCleaner;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Submits detached course documents to the shared Portal cleanup services.
 */
final class CourseDocumentCleanup implements CourseDocumentCleanupInterface {

  public const DOCUMENT_BUNDLE = 'course_document';

  public const FILE_FIELD = 'field_media_course_document';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly UnusedMediaCleaner $unusedMediaCleaner,
    private readonly UnusedFileCleaner $unusedFileCleaner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function removeDetachedDocument(int $mediaId): void {
    if ($mediaId <= 0) {
      return;
    }

    $media = $this->entityTypeManager->getStorage('media')->load($mediaId);
    if (!$media instanceof MediaInterface
      || $media->bundle() !== self::DOCUMENT_BUNDLE
      || $media->isPublished()
      || !$media->hasField(self::FILE_FIELD)
      || $media->get(self::FILE_FIELD)->isEmpty()) {
      return;
    }

    $file = $media->get(self::FILE_FIELD)->entity;
    if (!$file instanceof FileInterface) {
      return;
    }

    // Portal rechecks all references and the unpublished state immediately
    // before removing the Media. Only then can its former File be considered.
    if ($this->unusedMediaCleaner->removeIfUnused($media) !== FileCleanupStatus::Deleted) {
      return;
    }
    $this->unusedFileCleaner->removeIfUnused($file);
  }

}
