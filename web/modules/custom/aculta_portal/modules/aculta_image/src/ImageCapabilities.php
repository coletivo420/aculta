<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

/**
 * Reports image processing capabilities available to this PHP runtime.
 */
final class ImageCapabilities {

  /**
   * Returns available PHP image backends and their supported formats.
   *
   * @return array<string, array<string, bool>>
   *   Backend IDs mapped to supported MIME types.
   */
  public function backends(): array {
    $backends = [];
    if (class_exists(\Imagick::class)) {
      $formats = array_map('strtoupper', \Imagick::queryFormats());
      $backends['imagick'] = [
        'image/jpeg' => in_array('JPEG', $formats, TRUE),
        'image/png' => in_array('PNG', $formats, TRUE),
        'image/gif' => in_array('GIF', $formats, TRUE),
        'image/webp' => in_array('WEBP', $formats, TRUE),
      ];
    }
    if (extension_loaded('gd')) {
      $backends['gd'] = [
        'image/jpeg' => function_exists('imagejpeg'),
        'image/png' => function_exists('imagepng'),
        // The GD pipeline deliberately skips GIF to avoid dropping animation.
        'image/gif' => FALSE,
        'image/webp' => function_exists('imagewebp'),
      ];
    }
    return $backends;
  }

  /**
   * Returns executable image optimizers available on PATH.
   *
   * @return array<string, bool>
   *   Binary IDs mapped to availability.
   */
  public function optimizers(): array {
    $available = [];
    foreach (['optipng', 'jpegoptim', 'jpegtran', 'gifsicle', 'cwebp'] as $binary) {
      $available[$binary] = $this->findExecutable($binary) !== NULL;
    }
    return $available;
  }

  /**
   * Finds an executable using PATH without invoking a shell.
   */
  private function findExecutable(string $binary): ?string {
    foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
      $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binary;
      if (is_file($candidate) && is_executable($candidate)) {
        return $candidate;
      }
    }
    return NULL;
  }

}
