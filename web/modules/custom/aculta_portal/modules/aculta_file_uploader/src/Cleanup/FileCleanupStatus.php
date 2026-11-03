<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader\Cleanup;

/**
 * Result of an unused-file cleanup request. */
enum FileCleanupStatus: string {

  case Available = 'available';
  case Deleted = 'deleted';
  case InUse = 'in_use';
  case Unverifiable = 'unverifiable';
  case Temporary = 'temporary';
  case OwnerRejected = 'owner_rejected';

}
