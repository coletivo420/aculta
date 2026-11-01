<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader\Cleanup;

use Drupal\file\FileInterface;

/**
 * Read-only assessment of whether a File may be considered for cleanup.
 */
interface FileCleanupAssessorInterface {

  /**
   * Checks trackers and entity references without modifying the File.
   */
  public function assess(FileInterface $file, bool $allowTemporary = FALSE): FileCleanupStatus;

}
