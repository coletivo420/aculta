<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader\Cleanup;

/**
 * Outcome of checking Drupal entity references to a File. */
enum FileReferenceStatus: string {

  case Clear = 'clear';
  case Referenced = 'referenced';
  case Unverifiable = 'unverifiable';

}
