<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PEditor;

use Drupal\aculta_image\ImageFileUsageManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/** Keeps H5P media records synchronized with Drupal's managed file lifecycle. */
final class H5PFileUsageManager {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileUsageInterface $fileUsage,
    private readonly ImageFileUsageManager $imageUsage,
  ) {}

  /** Marks a media file retained by a saved H5P content entity. */
  public function keep(string $absolutePath): void {
    $base = (string) ($this->configFactory->get('aculta_lms_h5p.settings')->get('h5p_default_path') ?? 'h5p');
    if (!preg_match('@^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$@iD', $base)) {
      throw new \RuntimeException('The H5P private storage path is invalid.');
    }
    $root = $this->fileSystem->realpath("private://{$base}");
    $filePath = realpath($absolutePath);
    if ($root === FALSE || $filePath === FALSE || !str_starts_with($filePath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
      throw new \RuntimeException('H5P attempted to retain a file outside its private storage.');
    }
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($filePath, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1));
    if (!preg_match('@^content/(\d+)/(?:images|audios|videos|files)/[^/]+$@D', $relative, $matches)) {
      throw new \RuntimeException('H5P attempted to retain a file outside a content directory.');
    }
    $contentId = (int) $matches[1];
    $uri = "private://{$base}/{$relative}";
    $file = $this->fileRepository->loadByUri($uri);
    if ($file === NULL) {
      throw new \RuntimeException('H5P media is missing its Drupal File API record.');
    }
    if ($file->isTemporary()) {
      $file->setPermanent();
      $file->save();
    }
    $usage = $this->fileUsage->listUsage($file);
    if (empty($usage['aculta_lms_h5p']['h5p_content'][(string) $contentId])) {
      $this->fileUsage->add($file, 'aculta_lms_h5p', 'h5p_content', (string) $contentId);
    }
    if (str_starts_with((string) $file->getMimeType(), 'image/')) {
      $this->imageUsage->addH5PReference($file, $contentId);
    }
  }

}
