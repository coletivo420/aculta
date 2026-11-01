<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\aculta_H5PDrupal;

use Drupal\aculta_portal\Cleanup\UnusedFileCleaner;
use Drupal\aculta_image\ImageFileUsageManager;
use Drupal\aculta_image\PackagedAssetPublisher;
use Drupal\aculta_lms_h5p\aculta_H5PEditor\aculta_H5PFileUsageManager;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Creates an H5P adapter with Drupal-managed file lifecycle services.
 */
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital,Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the established H5P factory classname used by the service container.
final class aculta_H5PManagedFileStorageFactory {

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileUsageInterface $fileUsage,
    private readonly ImageFileUsageManager $imageUsage,
    private readonly UnusedFileCleaner $unusedFileCleaner,
    private readonly PackagedAssetPublisher $imagePublisher,
    private readonly aculta_H5PFileUsageManager $fileUsageManager,
  ) {}

  /**
   * Creates a managed H5P storage adapter for the private content directory.
   *
   * @param string $path
   *   Absolute private H5P path.
   *
   * @return \Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PManagedFileStorage
   *   Configured storage adapter.
   */
  public function create(string $path): aculta_H5PManagedFileStorage {
    return new aculta_H5PManagedFileStorage(
      $path,
      $this->fileSystem,
      $this->fileRepository,
      $this->fileUsage,
      $this->imageUsage,
      $this->unusedFileCleaner,
      $this->imagePublisher,
      $this->fileUsageManager,
    );
  }

}
