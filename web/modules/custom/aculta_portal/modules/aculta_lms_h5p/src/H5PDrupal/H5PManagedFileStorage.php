<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PDrupal;

use Drupal\aculta_image\FileRemover;
use Drupal\aculta_image\ImageFileUsageManager;
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
    $copy = $this->fileRepository->copy($source, $destinationUri, FileExists::Error);
    $copy->setTemporary();
    $copy->save();
  }

  /** Copies existing content files through managed records when available. */
  public function cloneContent($id, $newId) {
    $sourceDirectory = "{$this->h5pPath}/content/" . (int) $id;
    if (!is_dir($sourceDirectory)) {
      return;
    }
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $sourcePath) {
      if (!$sourcePath->isFile()) {
        continue;
      }
      $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($sourcePath->getPathname(), strlen($sourceDirectory) + 1));
      $sourceUri = "{$this->h5pUri}/content/" . (int) $id . "/{$relative}";
      $destinationUri = "{$this->h5pUri}/content/" . (int) $newId . "/{$relative}";
      $managed = $this->fileRepository->loadByUri($sourceUri);
      if ($managed !== NULL) {
        $this->fileRepository->copy($managed, $destinationUri, FileExists::Error);
        continue;
      }
      if (preg_match('/\.(?:png|gif|jpe?g|webp)$/i', $relative)) {
        throw new \RuntimeException('An H5P image is not registered in the Drupal File API.');
      }
      $this->fileSystem->prepareDirectory(dirname($destinationUri), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $this->fileSystem->copy($sourcePath->getPathname(), $destinationUri, FileExists::Error);
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
      if (preg_match('/\.(?:png|gif|jpe?g|webp)$/i', $relative)) {
        throw new \RuntimeException('Refusing to remove an untracked H5P image.');
      }
      return parent::removeContentFile($relative, $contentid);
    }

    $this->fileUsage->delete($managed, 'aculta_lms_h5p', 'h5p_content', (string) $contentid);
    $this->imageUsage->removeH5PReference($managed, (int) $contentid);
    if (str_starts_with((string) $managed->getMimeType(), 'image/')) {
      $this->imageRemover->delete($managed, 'H5P activity content updated');
    }
    elseif (empty($this->fileUsage->listUsage($managed))) {
      $managed->delete();
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

}
