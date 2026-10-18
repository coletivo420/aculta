<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PDrupal;

/** Provides the resolved private storage directory for H5P files. */
interface H5PStoragePathInterface {

  /** Returns the validated, prepared absolute H5P storage directory. */
  public function getPath(): string;

}
