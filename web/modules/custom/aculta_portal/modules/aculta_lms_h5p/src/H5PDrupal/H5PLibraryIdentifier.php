<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PDrupal;

/** Converts an installed H5P library version to its Drupal asset library ID. */
final class H5PLibraryIdentifier {

  /**
   * Returns the dynamic Drupal library ID used by H5P library definitions.
   *
   * H5P uses both editor metadata arrays and database dependency objects. This
   * method accepts those two shapes while keeping the generated ID restricted
   * to the same identifier alphabet used by Drupal libraries.
   *
   * @param array<string, mixed>|\stdClass $library
   *   H5P library metadata (`machineName`, `majorVersion`, `minorVersion`) or a
   *   dependency record (`name`, `major`, `minor`).
   */
  public static function toDrupalLibraryId(array|\stdClass $library): string {
    if (is_array($library)) {
      $name = $library['machineName'] ?? NULL;
      $major = $library['majorVersion'] ?? NULL;
      $minor = $library['minorVersion'] ?? NULL;
    }
    else {
      $name = $library->machineName ?? $library->name ?? NULL;
      $major = $library->majorVersion ?? $library->major ?? NULL;
      $minor = $library->minorVersion ?? $library->minor ?? NULL;
    }

    if (!is_string($name) || !preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/iD', $name)) {
      throw new \InvalidArgumentException('The H5P library machine name is invalid.');
    }
    $major = self::version($major);
    $minor = self::version($minor);
    if ($major === NULL || $minor === NULL) {
      throw new \InvalidArgumentException('The H5P library version is invalid.');
    }

    $name = strtolower($name);
    if (!str_starts_with($name, 'h5p.')) {
      $name = 'h5p.' . $name;
    }

    return $name . '-' . $major . '.' . $minor;
  }

  /** Normalizes a non-negative H5P version component. */
  private static function version(mixed $version): ?int {
    if (is_int($version) && $version >= 0) {
      return $version;
    }
    if (is_string($version) && preg_match('/^(0|[1-9][0-9]*)$/D', $version)) {
      return (int) $version;
    }
    return NULL;
  }

}
