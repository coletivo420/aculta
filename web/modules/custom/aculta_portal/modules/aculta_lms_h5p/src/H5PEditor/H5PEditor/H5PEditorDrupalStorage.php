<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\H5PEditor;

use Drupal\aculta_lms_h5p\H5PEditor\H5PFileUsageManager;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;

class H5PEditorDrupalStorage implements \H5peditorStorage {

  public function __construct(private readonly H5PFileUsageManager $fileUsageManager) {}

  /**
   * Load language file(JSON) from database.
   * This is used to translate the editor fields(title, description etc.)
   *
   * @param string $machineName The machine readable name of the library(content type)
   * @param int $majorVersion Major part of version number
   * @param int $minorVersion Minor part of version number
   * @param string $languageCode Language code
   * @return string Translation in JSON format
   */
  public function getLanguage($machineName, $majorVersion, $minorVersion, $languageCode) {
    $lang = \Drupal::database()->query(
      "SELECT language_json
        FROM {h5p_libraries_languages} hlt
        JOIN {h5p_libraries} hl
          ON hl.library_id = hlt.library_id
        WHERE hl.machine_name = :name
          AND hl.major_version = :major
          AND hl.minor_version = :minor
          AND hlt.language_code = :lang",
      [
        ':name' => $machineName,
        ':major' => $majorVersion,
        ':minor' => $minorVersion,
        ':lang' => $languageCode,
      ])->fetchField();

    return ($lang === FALSE ? NULL : $lang);
  }

  /**
   * Load a list of available language codes from the database.
   *
   * @param string $machineName The machine readable name of the library(content type)
   * @param int $majorVersion Major part of version number
   * @param int $minorVersion Minor part of version number
   * @return array List of possible language codes
   */
  public function getAvailableLanguages($machineName, $majorVersion, $minorVersion) {
    $results = \Drupal::database()->query(
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
        ':minor' => $minorVersion
      ]);

    $codes = array('en'); // Semantics is 'en' by default.
    foreach ($results as $result) {
      $codes[] = $result->language_code;
    }

    return $codes;
  }

  /**
   * "Callback" for mark the given file as a permanent file.
   * Used when saving content that has new uploaded files.
   *
   * @param string $path To new file
   */
  public function keepFile($path) {
    $this->fileUsageManager->keep((string) $path);
  }

  /**
   * Decides which content types the editor should have.
   *
   * Two usecases:
   * 1. No input, will list all the available content types.
   * 2. Libraries supported are specified, load additional data and verify
   * that the content types are available. Used by e.g. the Presentation Tool
   * Editor that already knows which content types are supported in its
   * slides.
   *
   * @param array $libraries List of library names + version to load info for
   * @return array List of all libraries loaded
   */
  public function getLibraries($libraries = NULL) {
    $database = \Drupal::database();
    $user = \Drupal::currentUser();
    $super_user = $user->hasPermission('create restricted h5p content types');

    if ($libraries !== NULL) {
      // Get details for the specified libraries only.
      $librariesWithDetails = array();
      foreach ($libraries as $library) {
        $details = $database->query(
          "SELECT title, runnable, restricted, tutorial_url, metadata_settings
          FROM {h5p_libraries}
          WHERE machine_name = :name
          AND major_version = :major
          AND minor_version = :minor
          AND semantics IS NOT NULL", // TODO: Consider if semantics is really needed (DB performance-wise)
          [
            ':name' => $library->name,
            ':major' => $library->majorVersion,
            ':minor' => $library->minorVersion
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
      ORDER BY title"); // TODO: Consider if semantics is really needed (DB performance-wise)
    foreach ($libraries_result as $library) {
      // Convert result object properties to camelCase.
      $library = \H5PCore::snakeToCamel($library, true);

      $library->metadataSettings = json_decode($library->metadataSettings ?? '');

      // Make sure we only display the newest version of a library.
      foreach ($libraries as $existingLibrary) {
        if ($library->name === $existingLibrary->name) {

          // Mark old ones
          // This is the newest
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

      // Add new library
      $libraries[] = $library;
    }

    return $libraries;
  }

  /**
   * Allow for other plugins to decide which styles and scripts are attached.
   * This is useful for adding and/or modifing the functionality and look of
   * the content types.
   *
   * @param array $files
   *  List of files as objects with path and version as properties
   * @param array $libraries
   *  List of libraries indexed by machineName with objects as values. The objects
   *  have majorVersion and minorVersion as properties.
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

    \Drupal::moduleHandler()->alter('h5p_scripts', $files['scripts'], $library_list, $mode);
    \Drupal::moduleHandler()->alter('h5p_styles', $files['styles'], $library_list, $mode);
  }

  /**
   * Saves a file temporarily with a given name
   *
   * @param string $data
   * @param bool $move_file Only move the uploaded file
   *
   * @return bool|false|string Real absolute path of the temporary folder
   */
  public static function saveFileTemporarily($data, $move_file = FALSE) {
    // Package import must go through H5PLibraryPackageInstaller; this legacy
    // editor callback cannot provide the required managed-file lifecycle.
    return FALSE;
  }

  /**
   * Marks a file for later cleanup, useful when files are not instantly cleaned
   * up. E.g. for files that are uploaded through the editor.
   *
   * @param \H5peditorFile $file
   * @param int $content_id
   */
  public static function markFileForCleanup($file, $content_id = null) {
    throw new \LogicException('Legacy H5P editor file cleanup is disabled; use managed H5P uploads.');
  }

  /**
   * Clean up temporary files
   *
   * @param string $filePath Path to file or directory
   */
  public static function removeTemporarilySavedFiles($filePath) {
    throw new \LogicException('Legacy H5P temporary package cleanup is disabled.');
  }
}
