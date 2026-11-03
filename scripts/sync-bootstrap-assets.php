<?php

declare(strict_types=1);

/**
 * Copy the Composer-managed Bootstrap 5 distribution to the local public library path.
 *
 * Drupal Bootstrap 8.x-3.x provides Bootstrap 3 from its configured CDN. ACULTA
 * keeps this local Bootstrap 5 distribution for its existing browser fixtures
 * and static integration checks; it is not the base theme's framework asset.
 */

$projectRoot = dirname(__DIR__);
$assets = [
  'vendor/twbs/bootstrap/dist/css/bootstrap.min.css' => 'web/libraries/bootstrap/dist/css/bootstrap.min.css',
  'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js' => 'web/libraries/bootstrap/dist/js/bootstrap.bundle.min.js',
];

foreach ($assets as $source => $destination) {
  $sourcePath = $projectRoot . DIRECTORY_SEPARATOR . $source;
  $destinationPath = $projectRoot . DIRECTORY_SEPARATOR . $destination;

  if (!is_file($sourcePath)) {
    throw new RuntimeException(sprintf('Bootstrap asset is missing: %s', $source));
  }

  $destinationDirectory = dirname($destinationPath);
  if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0755, TRUE) && !is_dir($destinationDirectory)) {
    throw new RuntimeException(sprintf('Could not create directory: %s', $destinationDirectory));
  }

  if (!copy($sourcePath, $destinationPath)) {
    throw new RuntimeException(sprintf('Could not copy Bootstrap asset to: %s', $destination));
  }
}
