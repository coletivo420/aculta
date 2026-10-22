<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\file\FileInterface;

/**
 * Owner API for deleting image files after reference checks. */
interface FileRemoverInterface {

  /**
   * Deletes an image only when Core and Entity Usage report no use. */
  public function delete(FileInterface $file, string $reasonCode): bool;

  /**
   * Checks File API and Entity Usage references. */
  public function hasReferences(FileInterface $file): bool;

}
