<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Cleanup;

use Drupal\aculta_portal\Cleanup\FileCleanupStatus;
use Drupal\aculta_portal\Cleanup\UnusedFileCleaner;
use Drupal\aculta_portal\Cleanup\UnusedMediaCleaner;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Psr\Log\LoggerInterface;

/**
 * Removes stale, unreferenced Wiki lead images through the shared cleanups.
 */
final class WikiLeadImageCleanup {

  /**
   * Minimum retention period before an orphan can be considered for cleanup.
   */
  private const RETENTION = 3600;

  /**
   * Maximum candidate Files examined by one cron run.
   */
  private const LIMIT = 25;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly UnusedMediaCleaner $unusedMediaCleaner,
    private readonly UnusedFileCleaner $unusedFileCleaner,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Removes old Wiki image Media and Files only after global revalidation.
   *
   * @return array{processed: int, media_deleted: int, files_deleted: int}
   *   Bounded cleanup counts.
   */
  public function cleanup(): array {
    $cutoff = $this->time->getRequestTime() - self::RETENTION;
    $fileStorage = $this->entityTypeManager->getStorage('file');
    $fileIds = $fileStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uri', 'private://wiki/imagens/%', 'LIKE')
      ->condition('status', FileInterface::STATUS_PERMANENT)
      ->condition('created', $cutoff, '<=')
      ->sort('created')
      ->range(0, self::LIMIT)
      ->execute();

    $result = ['processed' => count($fileIds), 'media_deleted' => 0, 'files_deleted' => 0];
    $mediaStorage = $this->entityTypeManager->getStorage('media');
    foreach ($fileStorage->loadMultiple($fileIds) as $file) {
      if (!$file instanceof FileInterface || !str_starts_with($file->getFileUri(), 'private://wiki/imagens/')) {
        continue;
      }

      try {
        $mediaIds = $mediaStorage->getQuery()
          ->accessCheck(FALSE)
          ->condition('bundle', 'image')
          ->condition('status', 0)
          ->condition('created', $cutoff, '<=')
          ->condition('field_media_image.target_id', $file->id())
          ->range(0, self::LIMIT)
          ->execute();
        $canCheckFile = TRUE;
        foreach ($mediaStorage->loadMultiple($mediaIds) as $media) {
          if (!$media instanceof MediaInterface
            || $media->bundle() !== 'image'
            || $media->isPublished()
            || !$media->hasField('field_media_image')
            || $media->get('field_media_image')->isEmpty()
            || (string) $media->get('field_media_image')->target_id !== (string) $file->id()) {
            $canCheckFile = FALSE;
            continue;
          }

          if ($this->unusedMediaCleaner->removeIfUnused($media) === FileCleanupStatus::Deleted) {
            $result['media_deleted']++;
          }
          else {
            // Keep the File while any candidate Media could not be removed.
            $canCheckFile = FALSE;
          }
        }

        if ($canCheckFile
          && $this->unusedFileCleaner->removeIfUnused($file) === FileCleanupStatus::Deleted) {
          $result['files_deleted']++;
        }
      }
      catch (\Throwable $exception) {
        $this->logger->warning('Wiki lead image cleanup retained File {fid}; verification failed ({class}).', [
          'fid' => $file->id(),
          'class' => $exception::class,
        ]);
      }
    }

    return $result;
  }

}
