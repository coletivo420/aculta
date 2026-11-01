<?php

namespace Drupal\aculta_lms_h5p\aculta_H5PEditor;

// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\lms\Entity\Bundle\CourseInterface;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 */
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
class aculta_H5PEditorUtilities {

  /**
   * Cached editor instance created on first use.
   */
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
   *   The editor instance used for this request.
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
   * Gets editor settings needed by the JavaScript front end.
   *
   * @param \Drupal\lms\Entity\Bundle\CourseInterface $course
   *   The course whose editor endpoint will be configured.
   *
   * @return array<string, mixed>
   *   Settings required to render the editor.
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
   * Gets assets needed to display the editor from H5P Core.
   *
   * @return array{css: array<int, string>, js: array<int, string>}
   *   Stylesheets and scripts required by the editor.
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
   * Extracts assets from a collection of assets.
   *
   * @param array $collection
   *   Collection of assets.
   * @param string $prefix
   *   Prefix needed for constructing the file-path of the assets.
   * @param null|array $exceptions
   *   Exceptions from the collection that should be skipped.
   *
   * @return array<int, string>
   *   Asset URLs extracted from the source collection.
   */
  private function getAssets($collection, $prefix, $exceptions = NULL): array {
    $assets      = [];
    $cacheBuster = $this->getCacheBuster();

    foreach ($collection as $item) {
      // Skip exceptions.
      if ($exceptions && in_array($item, $exceptions)) {
        continue;
      }
      $assets[] = "{$prefix}{$item}{$cacheBuster}";
    }
    return $assets;
  }

  /**
   * Gets the cache-busting query string for editor assets.
   *
   * @return string
   *   The query string, or an empty string when cache busting is disabled.
   */
  private function getCacheBuster(): string {
    $cache_buster = $this->assetQueryString->get();
    return $cache_buster ? "?{$cache_buster}" : '';
  }

  /**
   * Gets the editor translation file path, defaulting to English if needed.
   *
   * @return string
   *   Absolute URL of the selected translation file.
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
   * Creates the AJAX URI that the editor client may send to the server.
   *
   * @return string
   *   The internal URL string for the editor AJAX endpoint.
   */
  private function getAjaxPath(CourseInterface $course): string {
    $securityToken = \H5PCore::createToken('editorajax');
    return Url::fromUri(
      "internal:/h5peditor/{$course->id()}/{$securityToken}/:contentId/"
    )->toString();
  }

  /**
   * Extracts library information from a versioned library string.
   *
   * @param string $library
   *   Library string with versioning, e.g. H5P.MultiChoice 1.9.
   * @param string $property
   *   May be used to only extract certain information
   *   about the library. Values include 'all', 'libraryId', or a property name.
   *
   * @return int|bool|array<string, string|int>|null
   *   Requested library data, or FALSE when the library string is invalid.
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
   * Gets the library ID from unique library data.
   *
   * @param array $libraryData
   *   Library data containing its name and major and minor version numbers.
   *
   * @return null|int
   *   The matching library ID, or NULL when it is not installed.
   */
  private function getLibraryId($libraryData) {
    $select = $this->database->select('h5p_libraries');
    $select->fields('h5p_libraries', ['library_id'])
      ->condition('machine_name', $libraryData['machineName'])
      ->condition('major_version', $libraryData['majorVersion'])
      ->condition('minor_version', $libraryData['minorVersion']);
    return $select->execute()->fetchField();
  }

}
