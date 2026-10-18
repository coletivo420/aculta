<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PDrupal;

use Drupal\aculta_image\FileRemover;
use Drupal\aculta_image\ImageFileUsageManager;
use Drupal\aculta_image\PackagedAssetPublisher;
use Drupal\aculta_lms_h5p\H5PEditor\H5PFileUsageManager;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/** H5P file store that keeps editor uploads managed by Drupal's File API. */
final class H5PManagedFileStorage extends \H5PDefaultStorage {

  private readonly string $h5pPath;
  private readonly string $h5pUri;

  public function __construct(
    string $path,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileUsageInterface $fileUsage,
    private readonly ImageFileUsageManager $imageUsage,
    private readonly FileRemover $imageRemover,
    private readonly PackagedAssetPublisher $imagePublisher,
    private readonly H5PFileUsageManager $fileUsageManager,
  ) {
    parent::__construct($path);
    $this->h5pPath = rtrim($path, DIRECTORY_SEPARATOR);
    $privateRoot = $this->fileSystem->realpath('private://');
    $realRoot = $privateRoot === FALSE ? FALSE : realpath($privateRoot);
    if ($realRoot === FALSE || !str_starts_with($this->h5pPath, $realRoot . DIRECTORY_SEPARATOR)) {
      throw new \InvalidArgumentException('H5P content storage must be under the private files directory.');
    }
    $relativeRoot = str_replace(DIRECTORY_SEPARATOR, '/', substr($this->h5pPath, strlen($realRoot) + 1));
    $this->h5pUri = 'private://' . $relativeRoot;
  }

  /** Stores a validated library without bypassing managed image ingestion. */
  public function saveLibrary($library) {
    $source = realpath((string) ($library['uploadDirectory'] ?? ''));
    if ($source === FALSE || !is_dir($source) || !str_starts_with($source, $this->h5pPath . DIRECTORY_SEPARATOR)) {
      throw new \RuntimeException('The H5P library package directory is unavailable.');
    }
    $directoryName = \H5PCore::libraryToFolderName($library);
    if (!preg_match('@^[A-Za-z0-9_.-]+$@D', $directoryName)) {
      throw new \InvalidArgumentException('The H5P library package name is invalid.');
    }
    $destinationUri = "{$this->h5pUri}/libraries/{$directoryName}";
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $sourceFile) {
      if ($sourceFile->isLink() || !$sourceFile->isFile()) {
        throw new \RuntimeException('The H5P library contains an unsupported filesystem entry.');
      }
      $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($sourceFile->getPathname(), strlen($source) + 1));
      if (!preg_match('@^[\pL\pN._/-]+$@uD', $relative) || preg_match('@(?:^|/)\.{1,2}(?:/|$)@', $relative)) {
        throw new \RuntimeException('The H5P library contains an unsafe file path.');
      }
      if (in_array(basename($relative), ['.h5pignore', '.gitignore'], TRUE) || str_contains($relative, '/.git/')) {
        continue;
      }
      $uri = "{$destinationUri}/{$relative}";
      $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
      $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($sourceFile->getPathname()) ?: '';
      if ($this->isImageAsset($extension, $mime)) {
        $this->assertSupportedImageExtension($extension);
        $origin = 'h5p-library://' . $library['machineName'] . '/' . $library['majorVersion'] . '.' . $library['minorVersion'] . '/' . $relative;
        if (!$this->imagePublisher->publishH5PLibraryImage($sourceFile->getPathname(), $uri, $source, $origin)) {
          throw new \RuntimeException('An H5P library image was rejected by aculta_image.');
        }
        continue;
      }
      $bytes = file_get_contents($sourceFile->getPathname());
      if (!is_string($bytes) || strlen($bytes) > 20971520) {
        throw new \RuntimeException('An H5P library asset exceeds the allowed file size.');
      }
      $managed = $this->fileRepository->loadByUri($uri);
      if ($managed !== NULL) {
        $storedPath = $this->fileSystem->realpath($uri);
        if ($storedPath === FALSE || !hash_equals(hash('sha256', $bytes), hash_file('sha256', $storedPath) ?: '')) {
          throw new \RuntimeException('An installed H5P library cannot be replaced silently.');
        }
      }
      else {
        $this->fileSystem->prepareDirectory(dirname($uri), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $managed = $this->fileRepository->writeData($bytes, $uri, FileExists::Error);
        if ($managed === FALSE) {
          throw new \RuntimeException('An H5P library asset could not be stored.');
        }
        $managed->setPermanent();
        $managed->save();
      }
      $usage = $this->fileUsage->listUsage($managed);
      if (empty($usage['aculta_lms_h5p']['h5p_library'][$directoryName])) {
        $this->fileUsage->add($managed, 'aculta_lms_h5p', 'h5p_library', $directoryName);
      }
    }
  }

  /** Removes managed files for a library that Core has confirmed deletable. */
  public function removeLibraryFiles($library) {
    $directoryName = \H5PCore::libraryToFolderName((array) $library);
    if (!preg_match('@^[A-Za-z0-9_.-]+$@D', $directoryName)) {
      throw new \InvalidArgumentException('The H5P library name is invalid.');
    }
    $directory = "{$this->h5pPath}/libraries/{$directoryName}";
    if (!is_dir($directory)) {
      return;
    }
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
      if ($item->isLink()) {
        throw new \RuntimeException('Refusing to delete a library containing a symbolic link.');
      }
      if (!$item->isFile()) {
        continue;
      }
      $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($directory) + 1));
      $uri = "{$this->h5pUri}/libraries/{$directoryName}/{$relative}";
      $file = $this->fileRepository->loadByUri($uri);
      if ($file === NULL) {
        throw new \RuntimeException('Refusing to delete an H5P library file that is not managed by Drupal.');
      }
      if (in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], TRUE)) {
        $usageId = hash('sha256', dirname($relative));
        $this->fileUsage->delete($file, 'aculta_image', 'h5p_library_asset', $usageId);
        if (!$this->imageRemover->delete($file, 'H5P library removed by administrator')) {
          throw new \RuntimeException('An H5P library image is still referenced and cannot be removed.');
        }
      }
      else {
        $this->fileUsage->delete($file, 'aculta_lms_h5p', 'h5p_library', $directoryName);
        if (empty($this->fileUsage->listUsage($file)) && !$this->imageRemover->hasReferences($file)) {
          $file->getEntityType()->getStorage()->delete([$file]);
        }
        else {
          throw new \RuntimeException('An H5P library asset is still referenced and cannot be removed.');
        }
      }
    }
    $this->fileSystem->deleteRecursive($directory);
  }

  /**
   * Copies managed H5P media through File API instead of PHP copy().
   *
   * @param string $file
   * @param string|int $fromid
   * @param int $toid
   */
  public function cloneContentFile($file, $fromid, $toid) {
    $relative = $this->validatedRelativeFile((string) $file);
    $sourceId = $fromid === 'editor' ? 'editor' : 'content/' . (int) $fromid;
    $sourceUri = "{$this->h5pUri}/{$sourceId}/{$relative}";
    $destinationUri = "{$this->h5pUri}/content/" . (int) $toid . "/{$relative}";
    if ($this->fileSystem->realpath($destinationUri) !== FALSE) {
      return;
    }
    $source = $this->fileRepository->loadByUri($sourceUri);
    if ($source === NULL) {
      throw new \RuntimeException('The H5P editor file is not a managed Drupal file.');
    }
    $this->assertSupportedImage($source);
    $copy = $this->fileRepository->copy($source, $destinationUri, FileExists::Error);
    $copy->setTemporary();
    $copy->save();
    $this->fileUsageManager->keep((string) $this->fileSystem->realpath($destinationUri));
  }

  /** Copies existing content files only when Drupal manages their records. */
  public function cloneContent($id, $newId) {
    $sourceDirectory = "{$this->h5pPath}/content/" . (int) $id;
    if (!is_dir($sourceDirectory)) {
      return;
    }
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $sourcePath) {
      if ($sourcePath->isLink()) {
        throw new \RuntimeException('Refusing to clone an H5P content symbolic link.');
      }
      if (!$sourcePath->isFile()) {
        continue;
      }
      $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($sourcePath->getPathname(), strlen($sourceDirectory) + 1));
      if ($relative === 'content.json') {
        $destinationUri = "{$this->h5pUri}/content/" . (int) $newId . '/content.json';
        $this->fileSystem->prepareDirectory(dirname($destinationUri), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $this->fileSystem->copy($sourcePath->getPathname(), $destinationUri, FileExists::Error);
        continue;
      }
      $sourceUri = "{$this->h5pUri}/content/" . (int) $id . "/{$relative}";
      $destinationUri = "{$this->h5pUri}/content/" . (int) $newId . "/{$relative}";
      $managed = $this->fileRepository->loadByUri($sourceUri);
      if ($managed === NULL) {
        throw new \RuntimeException('An H5P content asset is missing its Drupal File API record.');
      }
      $this->assertSupportedImage($managed);
      $this->fileRepository->copy($managed, $destinationUri, FileExists::Error);
      $this->fileUsageManager->keep((string) $this->fileSystem->realpath($destinationUri));
    }
  }

  /** Removes managed content media before removing the content directory. */
  public function deleteContent($content) {
    $contentId = (int) ($content['id'] ?? 0);
    if ($contentId < 1) {
      return;
    }
    $directory = "{$this->h5pPath}/content/{$contentId}";
    if (!is_dir($directory)) {
      return;
    }
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
      if (!$item->isFile()) {
        continue;
      }
      $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($directory) + 1));
      if ($relative === 'content.json') {
        $this->fileSystem->unlink($item->getPathname());
        continue;
      }
      $this->removeContentFile($relative, $contentId);
    }
    $this->fileSystem->deleteRecursive($directory);
  }

  /**
   * Removes an H5P content file only after clearing its managed references.
   *
   * @param string $file
   * @param int $contentid
   * @return string|int|null
   */
  public function removeContentFile($file, $contentid) {
    $relative = $this->validatedRelativeFile((string) $file);
    $uri = "{$this->h5pUri}/content/" . (int) $contentid . "/{$relative}";
    $managed = $this->fileRepository->loadByUri($uri);
    if ($managed === NULL) {
      throw new \RuntimeException('Refusing to remove an H5P asset that is not managed by Drupal.');
    }

    $this->assertSupportedImage($managed);
    $this->fileUsage->delete($managed, 'aculta_lms_h5p', 'h5p_content', (string) $contentid);
    $this->imageUsage->removeH5PReference($managed, (int) $contentid);
    if ($this->isImageAsset(strtolower(pathinfo($managed->getFilename(), PATHINFO_EXTENSION)), (string) $managed->getMimeType())) {
      if (!$this->imageRemover->delete($managed, 'H5P activity content updated')) {
        throw new \RuntimeException('An H5P content image is still referenced and cannot be removed.');
      }
    }
    elseif (empty($this->fileUsage->listUsage($managed)) && !$this->imageRemover->hasReferences($managed)) {
      $managed->getEntityType()->getStorage()->delete([$managed]);
    }
    else {
      throw new \RuntimeException('An H5P content asset is still referenced and cannot be removed.');
    }
  }

  /** Returns a safe relative H5P media path. */
  private function validatedRelativeFile(string $file): string {
    if (!preg_match('@^(?:images|audios|videos|files)/[\p{L}\p{N}._ -]{1,240}$@uD', $file)
      || str_contains($file, '..')) {
      throw new \InvalidArgumentException('The H5P file path is invalid.');
    }
    return $file;
  }

  /** Rejects image formats that cannot be safely retained by aculta_image. */
  private function assertSupportedImage(\Drupal\file\FileInterface $file): void {
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    $mime = (string) $file->getMimeType();
    if (!$this->isImageAsset($extension, $mime)) {
      return;
    }
    if (!in_array($extension, ['png', 'gif', 'jpg', 'jpeg', 'webp'], TRUE)) {
      throw new \RuntimeException('This H5P image format is not accepted by aculta_image.');
    }
    $expectedExtensions = match ($mime) {
      'image/png' => ['png'],
      'image/jpeg' => ['jpg', 'jpeg'],
      'image/gif' => ['gif'],
      'image/webp' => ['webp'],
      'image/svg+xml' => ['svg'],
      default => [],
    };
    if (!in_array($extension, $expectedExtensions, TRUE)) {
      throw new \RuntimeException('An H5P image has an unsupported or mismatched media type.');
    }
  }

  private function isImageAsset(string $extension, string $mime): bool {
    return in_array($extension, ['png', 'gif', 'jpg', 'jpeg', 'webp', 'svg', 'bmp', 'tif', 'tiff', 'ico', 'avif'], TRUE)
      || str_starts_with(strtolower($mime), 'image/');
  }

  private function assertSupportedImageExtension(string $extension): void {
    if (!in_array($extension, ['png', 'gif', 'jpg', 'jpeg', 'webp', 'svg'], TRUE)) {
      throw new \RuntimeException('This H5P image format is not accepted by aculta_image.');
    }
  }

}
