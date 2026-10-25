<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p;

use Composer\InstalledVersions;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\aculta_image\PackagedAssetPublisher;

/**
 * Publishes Composer assets required by the ACULTA H5P integration.
 */
class aculta_H5PAssetFileStorage {

  /**
   * Directory to store all the assets in --`h5p`.
   */
  protected string $assetPrefix;

  /**
   * Drupal's FileSystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  public function __construct(
    FileSystemInterface $fileSystem,
    private readonly PackagedAssetPublisher $imagePublisher,
  ) {
    $this->fileSystem = $fileSystem;
    $this->assetPrefix = 'h5p';
  }

  /**
   * Get the URI where the assets will be stored.
   *
   * @return string
   *   Internal URI
   */
  public function getDirectoryUri(): string {
    $directory_uri = "assets://{$this->assetPrefix}/";

    return $directory_uri;
  }

  public function mirrorAssets(): void {
    $specifications = [
      'h5p-core' => [
        'directories' => [
          'js',
          'fonts',
          'images',
          'styles',
        ],
      ],
      'h5p-editor' => [
        'directories' => [
          'ckeditor',
          'images',
          'language',
          'libs',
          'scripts',
          'styles/css',
        ],
      ],
    ];
    foreach ($specifications as $project_name => $params) {
      $source_root = InstalledVersions::getInstallPath("h5p/$project_name");

      foreach ($params['directories'] as $directory) {
        $source_path = "$source_root/$directory";
        $files = [];
        $file_sources = $this->fileSystem->scanDirectory($source_path, '/\.(js|css|jpe?g|png|gif|svg|webp|bmp|ico|eot|ttf|woff|woff2)/');

        foreach ($file_sources as $file_source) {
          // The structure of the files array is [source => destination].
          $files[$file_source->uri]
            = $this->getDirectoryUri()
            . substr($file_source->uri,
              // We need to offset the destination path by the length of h5p/ or
              // else all the assets will be double nested in /h5p/h5p.
              (stripos($file_source->uri, "{$this->assetPrefix}/$project_name") + strlen("h5p/"))
            );
        }

        foreach ($files as $source_path => $destination_path) {
          $extension = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));
          if (in_array($extension, ['png', 'gif', 'jpg', 'jpeg', 'webp', 'svg'], TRUE)) {
            $public_uri = preg_replace('/^assets:/', 'public:', $destination_path);
            $origin = "composer://h5p/{$project_name}/" . substr($source_path, strlen($source_root) + 1);
            if ($public_uri === NULL || !$this->imagePublisher->publish($source_path, $public_uri, $origin)) {
              throw new \UnexpectedValueException("Unable to publish reviewed H5P image asset: {$origin}");
            }
            continue;
          }
          $local_destination = $this->resolveLocalAssetPath($destination_path);
          $destination_directory = dirname($local_destination);
          if (!$this->fileSystem->prepareDirectory($destination_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
            throw new \UnexpectedValueException("Unable to prepare H5P asset directory: {$destination_directory}");
          }
          $this->fileSystem->copy($source_path, $local_destination, FileExists::Replace);

        }
      }
    }
  }

  /**
   * Resolves a trusted assets URI to its local path for a Drupal FileSystem write.
   *
   * `assets://` is a hidden stream wrapper. On Linux installations using ACLs,
   * PHP's stream-wrapper `is_writable()` check can disagree with the actual
   * filesystem permissions and make Core's `prepareDirectory()` reject a
   * writable directory. The Core wrapper still supplies the configured root;
   * writes then use its canonical local path through FileSystemInterface.
   */
  private function resolveLocalAssetPath(string $uri): string {
    $scheme = 'assets://';
    $relative_path = str_starts_with($uri, $scheme) ? substr($uri, strlen($scheme)) : '';
    if ($relative_path === '' || str_contains($relative_path, '\\') || preg_match('@(?:^|/)\.{1,2}(?:/|$)@', $relative_path)) {
      throw new \UnexpectedValueException('Invalid H5P asset destination.');
    }

    $asset_root = $this->fileSystem->realpath($scheme);
    if (!is_string($asset_root) || $asset_root === '') {
      throw new \UnexpectedValueException('Unable to resolve the configured Drupal assets directory.');
    }

    $local_path = $asset_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    if (!str_starts_with($local_path, rtrim($asset_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
      throw new \UnexpectedValueException('H5P asset destination escapes the configured assets directory.');
    }

    return $local_path;
  }

}
