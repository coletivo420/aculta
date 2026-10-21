<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\aculta_H5PDrupal;

use Drupal\aculta_image\FileRemover;
use Drupal\aculta_image\ImageFileUsageManager;
use Drupal\aculta_image\PackagedAssetPublisher;
use Drupal\aculta_lms_h5p\aculta_H5PEditor\aculta_H5PFileUsageManager;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/** Creates an H5P filesystem adapter with Drupal managed-file lifecycle services. */
final class aculta_H5PManagedFileStorageFactory {

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUsageInterface $fileUsage,
    private readonly ImageFileUsageManager $imageUsage,
    private readonly FileRemover $imageRemover,
    private readonly PackagedAssetPublisher $imagePublisher,
    private readonly aculta_H5PFileUsageManager $fileUsageManager,
  ) {}

  public function create(string $path): aculta_H5PManagedFileStorage {
    return new aculta_H5PManagedFileStorage(
      $path,
      $this->fileSystem,
      $this->fileRepository,
      $this->entityTypeManager,
      $this->fileUsage,
      $this->imageUsage,
      $this->imageRemover,
      $this->imagePublisher,
      $this->fileUsageManager,
    );
  }

}
