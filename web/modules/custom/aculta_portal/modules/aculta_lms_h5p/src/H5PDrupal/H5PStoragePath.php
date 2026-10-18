<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PDrupal;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;

/** Resolves the private directory used for H5P content and libraries. */
final class H5PStoragePath implements H5PStoragePathInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /** Returns the validated, prepared absolute H5P storage directory. */
  public function getPath(): string {
    $relativePath = (string) ($this->configFactory->get('aculta_lms_h5p.settings')->get('aculta_lms_h5p_default_path') ?? 'h5p');
    if (!preg_match('@^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$@iD', $relativePath)) {
      throw new \RuntimeException('The private H5P storage path is invalid.');
    }
    $uri = 'private://' . $relativePath;
    if (!$this->fileSystem->prepareDirectory($uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new \RuntimeException('The private H5P storage directory is unavailable.');
    }
    $path = $this->fileSystem->realpath($uri);
    if ($path === FALSE) {
      throw new \RuntimeException('The private H5P storage directory could not be resolved.');
    }
    return $path;
  }

}
