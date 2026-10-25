<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cleanup;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\media\MediaInterface;

/**
 * Removes unpublished Media only after a cross-module reference check.
 */
final class UnusedMediaCleaner {

  public function __construct(
    private readonly FileReferenceInspectorInterface $referenceInspector,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Rechecks and deletes an unpublished Media entity when no reference remains.
   */
  public function removeIfUnused(MediaInterface $media): FileCleanupStatus {
    if ($media->isPublished()) {
      return FileCleanupStatus::OwnerRejected;
    }

    try {
      $status = $this->referenceInspector->inspect($media);
      if ($status === FileReferenceStatus::Referenced) {
        return FileCleanupStatus::InUse;
      }
      if ($status === FileReferenceStatus::Unverifiable) {
        return FileCleanupStatus::Unverifiable;
      }

      // Recheck publication immediately before invoking the Media Entity API.
      if ($media->isPublished()) {
        return FileCleanupStatus::OwnerRejected;
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
