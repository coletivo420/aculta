<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cleanup;

use Drupal\aculta_file_uploader\Cleanup\FileReferenceInspectorInterface;
use Drupal\aculta_file_uploader\Cleanup\FileReferenceStatus;
use Drupal\aculta_file_uploader\Cleanup\FileCleanupStatus;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\media\MediaInterface;

/**
 * Removes unpublished Media only after a cross-module reference check.
 */
final class UnusedMediaCleaner implements MediaCleanupAssessorInterface {

  public function __construct(
    private readonly FileReferenceInspectorInterface $referenceInspector,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function assess(MediaInterface $media): FileCleanupStatus {
    if ($media->isPublished()) {
      return FileCleanupStatus::OwnerRejected;
    }

    try {
      return match ($this->referenceInspector->inspect($media)) {
        FileReferenceStatus::Clear => FileCleanupStatus::Available,
        FileReferenceStatus::Referenced => FileCleanupStatus::InUse,
        FileReferenceStatus::Unverifiable => FileCleanupStatus::Unverifiable,
      };
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not verify references for Media {mid}; retaining it. {message}', [
        'mid' => $media->id(),
        'message' => $exception->getMessage(),
      ]);
      return FileCleanupStatus::Unverifiable;
    }
  }

  /**
   * Rechecks and deletes an unpublished Media entity when no reference remains.
   */
  public function removeIfUnused(MediaInterface $media): FileCleanupStatus {
    $status = $this->assess($media);
    if ($status !== FileCleanupStatus::Available) {
      return $status;
    }

    try {
      // Revalidate references and publication immediately before deletion.
      if ($media->isPublished()) {
        return FileCleanupStatus::OwnerRejected;
      }
      $status = match ($this->referenceInspector->inspect($media)) {
        FileReferenceStatus::Clear => FileCleanupStatus::Available,
        FileReferenceStatus::Referenced => FileCleanupStatus::InUse,
        FileReferenceStatus::Unverifiable => FileCleanupStatus::Unverifiable,
      };
      if ($status !== FileCleanupStatus::Available) {
        return $status;
      }

      $media->delete();
      $this->logger->info('Unused Media {mid} removed through Portal cleanup.', ['mid' => $media->id()]);
      return FileCleanupStatus::Deleted;
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not verify or remove Media {mid}; retaining it. {message}', [
        'mid' => $media->id(),
        'message' => $exception->getMessage(),
      ]);
      return FileCleanupStatus::Unverifiable;
    }
  }

}
