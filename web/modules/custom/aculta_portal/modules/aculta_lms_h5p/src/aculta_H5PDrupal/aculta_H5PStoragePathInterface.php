<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\aculta_H5PDrupal;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 *
 * Provides the resolved private storage directory for H5P files. */
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
interface aculta_H5PStoragePathInterface {

  /**
   * Returns the validated, prepared absolute H5P storage directory. */
  public function getPath(): string;

}
