<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PDrupal;

use Drupal\aculta_image\FileRemover;
use Drupal\aculta_image\ImageFileUsageManager;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/** Creates an H5P filesystem adapter with Drupal managed-file lifecycle services. */
final class H5PManagedFileStorageFactory {

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileUsageInterface $fileUsage,
    private readonly ImageFileUsageManager $imageUsage,
    private readonly FileRemover $imageRemover,
  ) {}

  public function create(string $path): H5PManagedFileStorage {
    return new H5PManagedFileStorage(
      $path,
      $this->fileSystem,
      $this->fileRepository,
      $this->fileUsage,
      $this->imageUsage,
      $this->imageRemover,
    );
  }

}
