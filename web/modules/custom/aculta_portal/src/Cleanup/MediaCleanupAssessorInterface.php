<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cleanup;

use Drupal\media\MediaInterface;

/**
 * Read-only assessment of whether a Media may be considered for cleanup.
 */
interface MediaCleanupAssessorInterface {

  /**
   * Checks publication and entity references without modifying the Media.
   */
  public function assess(MediaInterface $media): FileCleanupStatus;

}
