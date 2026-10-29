<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\aculta_H5PDrupal;

use Drupal\Core\Asset\LibraryDiscoveryCollector;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 *
 * Clears Drupal and H5P dynamic asset definitions after library changes. */
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
final class aculta_H5PLibraryCacheInvalidator {

  public const CACHE_ID = 'aculta_lms_h5p_library_info_build';

  public function __construct(
    private readonly CacheBackendInterface $cache,
    private readonly LibraryDiscoveryCollector $libraryDiscovery,
  ) {}

  /**
   * Invalidates cached definitions so new/deleted library assets take effect. */
  public function invalidate(): void {
    $this->cache->delete(self::CACHE_ID);
    $this->libraryDiscovery->clear();
  }

}
