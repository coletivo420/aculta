<?php

declare(strict_types=1);

/**
 * Copy the Composer-managed Bootstrap distribution to Drupal's public library path.
 *
 * The Drupal Bootstrap theme resolves its local framework library from
 * /libraries/bootstrap. The upstream twbs/bootstrap package is a regular
 * Composer library and stays under vendor/, so Composer lifecycle events expose
 * only the CSS and JavaScript assets needed by the theme at that public path.
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
