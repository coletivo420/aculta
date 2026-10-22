<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cleanup;

use Drupal\aculta_image\FileRemoverInterface;
use Drupal\aculta_image\ImageInspector;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Coordinates safe File cleanup while leaving image deletion to aculta_image. */
final class UnusedFileCleaner {

  public function __construct(
    private readonly FileUsageInterface $fileUsage,
    private readonly Connection $database,
    private readonly FileReferenceInspectorInterface $referenceInspector,
    private readonly FileRemoverInterface $imageRemover,
    private readonly ImageInspector $imageInspector,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Assesses whether a File is unused by active fields, rich text, or trackers.
   */
  public function assess(FileInterface $file, bool $allowTemporary = FALSE): FileCleanupStatus {
    if ($file->isTemporary() && !$allowTemporary) {
      return FileCleanupStatus::Temporary;
    }
    try {
      if ($this->fileUsage->listUsage($file) !== [] || $this->hasEntityUsage($file)) {
        return FileCleanupStatus::InUse;
      }
      return match ($this->referenceInspector->inspect($file)) {
        FileReferenceStatus::Clear => FileCleanupStatus::Available,
        FileReferenceStatus::Referenced => FileCleanupStatus::InUse,
        FileReferenceStatus::Unverifiable => FileCleanupStatus::Unverifiable,
      };
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not verify references for File {fid}; retaining it. {message}', [
        'fid' => $file->id(),
        'message' => $exception->getMessage(),
      ]);
      return FileCleanupStatus::Unverifiable;
    }
  }

  /**
   * Deletes an unused File after a second, immediate reference check.
   *
   * @param \Drupal\file\FileInterface $file
   *   File entity to recheck and remove.
   * @param bool $allowTemporary
   *   Whether the caller already applied the configured retention period.
   *
   * @return \Drupal\aculta_portal\Cleanup\FileCleanupStatus
   *   Deleted or a fail-closed reason why it was retained.
   */
  public function removeIfUnused(FileInterface $file, bool $allowTemporary = FALSE): FileCleanupStatus {
    $status = $this->assess($file, $allowTemporary);
    if ($status !== FileCleanupStatus::Available) {
      return $status;
    }

    try {
      $mime = (string) $file->getMimeType();
      $isRaster = str_starts_with($mime, 'image/') || $this->imageInspector->dimensions($file->getFileUri()) !== NULL;
      if ($isRaster) {
        if (!str_starts_with($mime, 'image/') || !$this->imageRemover->delete($file, 'portal_unused_file_cleanup')) {
          return FileCleanupStatus::OwnerRejected;
        }
      }
      else {
        $file->delete();
      }
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not remove unused File {fid}. {message}', [
        'fid' => $file->id(),
        'message' => $exception->getMessage(),
      ]);
      return FileCleanupStatus::Unverifiable;
    }

    $this->logger->info('Unused File {fid} removed through Portal cleanup.', ['fid' => $file->id()]);
    return FileCleanupStatus::Deleted;
  }

  /**
   * Checks the Entity Usage table when that tracker is installed.
   */
  private function hasEntityUsage(FileInterface $file): bool {
    if (!$this->database->schema()->tableExists('entity_usage')) {
      return FALSE;
    }
    return (bool) $this->database->select('entity_usage', 'eu')
      ->fields('eu', ['target_id'])
      ->condition('eu.target_type', 'file')
      ->condition('eu.target_id', (string) $file->id())
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

}
