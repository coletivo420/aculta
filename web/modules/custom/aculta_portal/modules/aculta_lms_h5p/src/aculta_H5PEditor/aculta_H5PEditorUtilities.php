<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\aculta_H5PEditor;

use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\lms\Entity\Bundle\CourseInterface;

class aculta_H5PEditorUtilities {

  private ?\H5peditor $editor = NULL;

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly FileSystemInterface $fileSystem,
    private readonly LanguageManagerInterface $languageManager,
    private readonly AssetQueryStringInterface $assetQueryString,
    private readonly Connection $database,
    private readonly aculta_H5PEditorDrupalStorage $editorStorage,
    private readonly aculta_H5PEditorDrupalAjax $editorAjax,
    private readonly aculta_H5PDrupal $framework,
  ) {}

  /**
   * Keeps track of our editor instance, saving valuable resources.
   *
   * @return \H5peditor
   */
  public function getEditor(): \H5peditor {
    if ($this->editor === NULL) {
      $this->editor = new \H5peditor(
        aculta_H5PDrupal::getInstance('core'),
        $this->editorStorage,
        $this->editorAjax,
      );
    }
    return $this->editor;
  }

  /**
   * Get editor settings needed for JS front-end
   *
   * @return array Settings needed for view
   */
  public function getEditorSettings(CourseInterface $course): array {
    $contentValidator = aculta_H5PDrupal::getInstance('contentvalidator');

    $settings = [
      'filesPath'          => base_path() . aculta_H5PDrupal::getRelativeaculta_H5PPath(),
      'fileIcon'           => [
        'path' => $this->fileUrlGenerator->generateAbsoluteString('assets://h5p/h5p-editor/images/binary-file.png'),
        'width'  => 50,
        'height' => 50,
      ],
      'ajaxPath' => str_replace('%3A', ':', $this->getAjaxPath($course)),
      'libraryPath' => $this->fileUrlGenerator->generateAbsoluteString('assets://h5p/h5p-editor/') . base_path(),
      'copyrightSemantics' => $contentValidator->getCopyrightSemantics(),
      'metadataSemantics' => $contentValidator->getMetadataSemantics(),
      'assets'             => $this->getEditorAssets(),
      'apiVersion'         => \H5PCore::$coreApi,
      'language' => $this->languageManager->getCurrentLanguage()->getId(),
    ];

    return $settings;
  }

  /**
   * Get assets needed to display editor. These are fetched from core.
   *
   * @return array Js and css for showing the editor
   */
  private function getEditorAssets(): array {
    $corePath = $this->fileUrlGenerator->generateAbsoluteString('assets://h5p/h5p-core/') . base_path();
    $editorPath = $this->fileUrlGenerator->generateAbsoluteString('assets://h5p/h5p-editor/') . base_path();

    $css = array_merge(
      $this->getAssets(\H5PCore::$styles, $corePath),
      $this->getAssets(\H5PEditor::$styles, $editorPath)
    );
    $js = array_merge(
      $this->getAssets(\H5PCore::$scripts, $corePath),
      $this->getAssets(\H5PEditor::$scripts, $editorPath, ['scripts/h5peditor-editor.js'])
    );
    $js[] = $this->getTranslationFilePath();

    return ['css' => $css, 'js' => $js];
  }

  /**
   * Extracts assets from a collection of assets
   *
   * @param array $collection Collection of assets
   * @param string $prefix Prefix needed for constructing the file-path of the assets
   * @param null|array $exceptions Exceptions from the collection that should be skipped
   *
   * @return array Extracted assets from the source collection
   */
  private function getAssets($collection, $prefix, $exceptions = NULL): array {
    $assets      = [];
    $cacheBuster = $this->getCacheBuster();

    foreach ($collection as $item) {
      // Skip exceptions
      if ($exceptions && in_array($item, $exceptions)) {
        continue;
      }
      $assets[] = "{$prefix}{$item}{$cacheBuster}";
    }
    return $assets;
  }

  /**
   * Get cache buster
   *
   * @return string A cache buster that may be applied to resources
   */
  private function getCacheBuster(): string {
    $cache_buster = $this->assetQueryString->get();
    return $cache_buster ? "?{$cache_buster}" : '';
  }

  /**
   * Translation file path for the editor. Defaults to English if chosen
   * language is not available.
   *
   * @return string Path to translation file for editor
   */
  private function getTranslationFilePath(): string {
    $language = $this->languageManager->getCurrentLanguage()->getId();

    $languageFolder = "assets://h5p/h5p-editor/language";
    $defaultLanguage = $this->fileUrlGenerator->generateAbsoluteString("{$languageFolder}/en.js");
    $chosenLanguage = $this->fileUrlGenerator->generateAbsoluteString("{$languageFolder}/{$language}.js");
    $cacheBuster = $this->getCacheBuster();

    return ($this->fileSystem->realpath("{$languageFolder}/{$language}.js") !== FALSE ? $chosenLanguage : $defaultLanguage) . $cacheBuster;
  }

  /**
   * Create URI for ajax the client may send to the server
   *
   * @return \Drupal\Core\GeneratedUrl|string Uri for AJAX
   */
  private function getAjaxPath(CourseInterface $course): string {
    $securityToken = \H5PCore::createToken('editorajax');
    return Url::fromUri(
      "internal:/h5peditor/{$course->id()}/{$securityToken}/:contentId/"
    )->toString();
  }

  /**
   * Extract library information from library string
   *
   * @param string $library Library string with versioning, e.g. H5P.MultiChoice 1.9
   * @param string $property May be used to only extract certain information
   * about library. Available values are 'all', 'libraryId' and specific property
   *
   * @return int|bool|array One or more properties, or false if invalid.
   */
  public function getLibraryProperty($library, $property = 'all') {
    $matches = [];
    preg_match_all('/(.+)\s(\d+)\.(\d+)$/', $library, $matches);
    if (count($matches) == 4) {
      $libraryData = [
        'name'         => $matches[1][0],
        'machineName'  => $matches[1][0],
        'majorVersion' => $matches[2][0],
        'minorVersion' => $matches[3][0],
      ];
      switch ($property) {
        case 'all':
          $libraryData['libraryId'] = $this->getLibraryId($libraryData);
          return $libraryData;
        case 'libraryId':
          $libraryId = $this->getLibraryId($libraryData);
          return $libraryId;
        default:
          return $libraryData[$property];
      }
    }
    else {
      return FALSE;
    }
  }

  /**
   * Library ID from unique library data
   *
   * @param array $libraryData Library data which must contain
   * 'machineName', 'majorVersion' and 'minorVersion'
   *
   * @return null|int Library id
   */
  private function getLibraryId($libraryData) {
    $select = $this->database->select('h5p_libraries');
    $select->fields('h5p_libraries', array('library_id'))
           ->condition('machine_name', $libraryData['machineName'])
           ->condition('major_version', $libraryData['majorVersion'])
           ->condition('minor_version', $libraryData['minorVersion']);
    return $select->execute()->fetchField();
  }
}
