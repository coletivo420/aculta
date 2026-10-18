<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/** Registers managed H5P image references with the central image lifecycle. */
final class ImageFileUsageManager {

  public function __construct(
    private readonly ImageInspector $inspector,
    private readonly FileUsageInterface $fileUsage,
  ) {}

  /** Adds an aculta_image usage reference if the managed file is a valid raster image. */
  public function addH5PReference(FileInterface $file, int $contentId): bool {
    if ($contentId < 1 || !ImageIngester::extensaoAceita($file->getFilename())
      || $this->inspector->dimensions($file->getFileUri()) === NULL) {
      return FALSE;
    }
    $usage = $this->fileUsage->listUsage($file);
    if (empty($usage['aculta_image']['h5p_content'][(string) $contentId])) {
      $this->fileUsage->add($file, 'aculta_image', 'h5p_content', (string) $contentId);
    }
    return TRUE;
  }

  /** Removes one H5P reference before aculta_image revalidates deletion. */
  public function removeH5PReference(FileInterface $file, int $contentId): void {
    if ($contentId > 0) {
      $this->fileUsage->delete($file, 'aculta_image', 'h5p_content', (string) $contentId);
    }
  }

}
