<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\file\FileInterface;

/**
 * Owner API for deleting files after reference checks.
 *
 * This is the only path for deleting files in ACULTA. It covers images and
 * other file types.
 */
interface FileRemoverInterface {

  /**
   * Deletes a file only when Core and Entity Usage report no use.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to remove.
   * @param string $reasonCode
   *   Short reason recorded in the log.
   *
   * @return bool
   *   TRUE when the file was removed.
   */
  public function delete(FileInterface $file, string $reasonCode): bool;

  /**
   * Checks File API and Entity Usage references.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to check.
   *
   * @return bool
   *   TRUE when anything still references the file.
   */
  public function hasReferences(FileInterface $file): bool;

}
