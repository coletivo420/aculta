<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources\Cleanup;

/**
 * Coordinates safe cleanup after a course document is detached.
 */
interface CourseDocumentCleanupInterface {

  /**
   * Removes an orphan course document and its file through Portal services.
   *
   * @param int $mediaId
   *   Detached Media ID.
   */
  public function removeDetachedDocument(int $mediaId): void;

}
