<?php

namespace Drupal\aculta_lms_h5p\aculta_H5PEditor;

// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 */
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
class aculta_H5PEditorDrupalStorage implements \H5peditorStorage {

  public function __construct(
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly aculta_H5PFileUsageManager $fileUsageManager,
  ) {}

  /**
   * Loads a language file from the database.
   *
   * Used to translate editor fields such as title and description.
   *
   * @param string $name
   *   Machine name of the H5P library.
   * @param int $major
   *   Major version number of the library.
   * @param int $minor
   *   Minor version number of the library.
   * @param string $lang
   *   Language code requested by the editor.
   *
   * @return string|null
   *   Translation JSON, or NULL when the language is not installed.
   */
  public function getLanguage($name, $major, $minor, $lang) {
    $lang = $this->database->query(
      "SELECT language_json
        FROM {h5p_libraries_languages} hlt
        JOIN {h5p_libraries} hl
          ON hl.library_id = hlt.library_id
        WHERE hl.machine_name = :name
          AND hl.major_version = :major
          AND hl.minor_version = :minor
          AND hlt.language_code = :lang",
      [
        ':name' => $name,
        ':major' => $major,
        ':minor' => $minor,
        ':lang' => $lang,
      ])->fetchField();

    return ($lang === FALSE ? NULL : $lang);
  }

  /**
   * Loads available language codes from the database.
   *
   * @param string $machineName
   *   Machine name of the H5P library.
   * @param int $majorVersion
   *   Major part of version number.
   * @param int $minor
   *   Minor part of version number.
   *
   * @return array<int, string>
   *   Available language codes, including English as the default.
   */
  public function getAvailableLanguages($machineName, $majorVersion, $minor) {
    $results = $this->database->query(
      "SELECT language_code
      FROM {h5p_libraries_languages} hlt
      JOIN {h5p_libraries} hl
        ON hl.library_id = hlt.library_id
      WHERE hl.machine_name = :name
        AND hl.major_version = :major
        AND hl.minor_version = :minor",
      [
        ':name' => $machineName,
        ':major' => $majorVersion,
        ':minor' => $minor,
      ]);

    // Semantics is 'en' by default.
    $codes = ['en'];
    foreach ($results as $result) {
      $codes[] = $result->language_code;
    }

    return $codes;
  }

  /**
   * Marks an uploaded file as permanent when saving H5P content.
   *
   * @param string $path
   *   URI of the uploaded file.
   */
  public function keepFile($path) {
    $this->fileUsageManager->keep((string) $path);
  }

  /**
   * Determines which content types are available to the editor.
   *
   * Without a list, returns every available type. With a list, loads details
   * for those types, as used by editors that already know supported libraries.
   *
   * @param array<int, object>|null $libraries
   *   Optional library names and versions for which to load details.
   *
   * @return array<int, object>
   *   Available libraries with details needed by the H5P editor.
   */
  public function getLibraries($libraries = NULL) {
    $database = $this->database;
    $user = $this->currentUser;
    $super_user = $user->hasPermission('create restricted h5p content types');

    if ($libraries !== NULL) {
      // Get details for the specified libraries only.
      $librariesWithDetails = [];
      foreach ($libraries as $library) {
        $details = $database->query(
          "SELECT title, runnable, restricted, tutorial_url, metadata_settings
          FROM {h5p_libraries}
          WHERE machine_name = :name
          AND major_version = :major
          AND minor_version = :minor
// DT-H5P04 (DEBT-REGISTER.md): measure before removing.
          AND semantics IS NOT NULL",
          [
            ':name' => $library->name,
            ':major' => $library->majorVersion,
            ':minor' => $library->minorVersion,
          ])
          ->fetchObject();
        if ($details !== FALSE) {
          $library->tutorialUrl = $details->tutorial_url;
          $library->title = $details->title;
          $library->runnable = $details->runnable;
          $library->restricted = $super_user ? FALSE : ($details->restricted === '1' ? TRUE : FALSE);
          $library->metadataSettings = json_decode($details->metadata_settings ?? '');
          $librariesWithDetails[] = $library;
        }
      }

      return $librariesWithDetails;
    }

    $libraries = [];

    $libraries_result = $database->query(
      "SELECT machine_name AS name,
              title,
              major_version,
              minor_version,
              restricted,
              tutorial_url,
              metadata_settings
      FROM {h5p_libraries}
      WHERE runnable = 1
      AND semantics IS NOT NULL
// DT-H5P04 (DEBT-REGISTER.md): measure before removing.
      ORDER BY title");
    foreach ($libraries_result as $library) {
      // Convert result object properties to camelCase.
      $library = \H5PCore::snakeToCamel($library, TRUE);

      $library->metadataSettings = json_decode($library->metadataSettings ?? '');

      // Make sure we only display the newest version of a library.
      foreach ($libraries as $existingLibrary) {
        if ($library->name === $existingLibrary->name) {

          // Mark old ones
          // This is the newest.
          if (($library->majorVersion === $existingLibrary->majorVersion && $library->minorVersion > $existingLibrary->minorVersion) ||
            ($library->majorVersion > $existingLibrary->majorVersion)) {
            $existingLibrary->isOld = TRUE;
          }
          else {
            $library->isOld = TRUE;
          }
        }
      }

      $library->restricted = $super_user ? FALSE : ($library->restricted === '1' ? TRUE : FALSE);

      // Add new library.
      $libraries[] = $library;
    }

    return $libraries;
  }

  /**
   * Allows modules to alter the scripts and styles attached by H5P.
   *
   * @param array $files
   *   File assets with path and version properties, passed by reference.
   * @param array $libraries
   *   Libraries keyed by machine name, with major and minor version values.
   */
  public function alterLibraryFiles(&$files, $libraries) {
    $mode = 'editor';
    $library_list = [];
    foreach ($libraries as $dependency) {
      $library_list[$dependency['machineName']] = [
        'majorVersion' => $dependency['majorVersion'],
        'minorVersion' => $dependency['minorVersion'],
      ];
    }

    $this->moduleHandler->alter('h5p_scripts', $files['scripts'], $library_list, $mode);
    $this->moduleHandler->alter('h5p_styles', $files['styles'], $library_list, $mode);
  }

  /**
   * Saves a file temporarily with a given name.
   *
   * @param string $data
   *   File data provided by the legacy editor callback.
   * @param bool $move_file
   *   Only move the uploaded file.
   *
   * @return false
   *   Always FALSE because temporary package writes are disabled.
   */
  public static function saveFileTemporarily($data, $move_file = FALSE) {
    // Package import uses the installer; this legacy callback cannot provide
    // the required managed-file lifecycle.
    return FALSE;
  }

  /**
   * Rejects legacy temporary-file cleanup requests.
   *
   * Editor uploads now use Drupal's managed-file lifecycle.
   *
   * @param \H5peditorFile $file
   *   Legacy editor file object scheduled for cleanup.
   * @param int|null $content_id
   *   Content ID associated with the temporary file.
   */
  public static function markFileForCleanup($file, $content_id = NULL) {
    throw new \LogicException('Legacy H5P editor file cleanup is disabled; use managed H5P uploads.');
  }

  /**
   * Clean up temporary files.
   *
   * @param string $filePath
   *   Path to file or directory.
   */
  public static function removeTemporarilySavedFiles($filePath) {
    throw new \LogicException('Legacy H5P temporary package cleanup is disabled.');
  }

}
