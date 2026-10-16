<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

/** Registers generated image derivatives for the aculta_image lifecycle. */
final class GeneratedImageFileManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly MimeTypeGuesser $mimeTypeGuesser,
    private readonly ImageInspector $imageInspector,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Registers a generated public image as temporary, or returns NULL if invalid.
   *
   * Temporary derivatives have no entity usage and are removed by the normal
   * aculta_image cleanup after system.file:temporary_maximum_age.
   */
  public function registerTemporaryDerivative(string $uri): ?FileInterface {
    if (!str_starts_with($uri, 'public://')) {
      return NULL;
    }
    $realpath = $this->fileSystem->realpath($uri);
    if (!is_string($realpath) || !is_file($realpath) || !is_readable($realpath)) {
      return NULL;
    }
    $dimensions = $this->imageInspector->dimensions($uri);
    $mime = $this->mimeTypeGuesser->guessMimeType($uri);
    if ($dimensions === NULL || !is_string($mime) || !str_starts_with($mime, 'image/')) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('file');
    $existing = $storage->loadByProperties(['uri' => $uri]);
    $file = reset($existing);
    if ($file instanceof FileInterface) {
      // A permanent file may have independent references; never demote it.
      if ($file->isPermanent()) {
        return $file;
      }
      $file->setTemporary();
      $file->set('created', $this->time->getRequestTime());
      $file->set('changed', $this->time->getRequestTime());
      $file->save();
      return $file;
    }

    $size = filesize($realpath);
    if ($size === FALSE || $size < 1) {
      return NULL;
    }
    $file = File::create([
      'uid' => 0,
      'filename' => basename($uri),
      'uri' => $uri,
      'filemime' => $mime,
      'filesize' => $size,
      'status' => 0,
      'created' => $this->time->getRequestTime(),
      'changed' => $this->time->getRequestTime(),
    ]);
    $file->save();
    return $file;
  }

}
