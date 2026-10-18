<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PEditor;

use Drupal\file\Validation\FileValidatorSettingsTrait;

/** Builds Drupal Core upload validators for non-image H5P media. */
final class H5PFileUploadValidators {

  use FileValidatorSettingsTrait;

  /** Returns Core validators bounded by PHP upload limits and extensions. */
  public function forExtensions(string $extensions): array {
    return $this->getFileUploadValidators(['file_extensions' => $extensions]);
  }

}
