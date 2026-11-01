<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\PhpStorage;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Component\PhpStorage\MTimeProtectedFileStorage;

/**
 * Stores compiled Twig files without Drupal Core's world-writable directories.
 *
 * The parent directory's setgid bit and default ACL provide mode 02770 and
 * shared development access. This storage preserves those inherited settings
 * and avoids Core cleanup paths that chmod directories to 0777.
 */
final class RestrictedTwigStorage extends MTimeProtectedFileStorage {

  /** {@inheritdoc} */
  public function save($name, $data) {
    if (!parent::save($name, $data)) {
      return FALSE;
    }

    $path = $this->getPath($name);
    if (!$path) {
      return FALSE;
    }

    // Keep compiled PHP immutable and readable only by its owner/group.
    return chmod($path, 0440);
  }

  /** {@inheritdoc} */
  protected function ensureDirectory($directory, $mode = 02770) {
    if ($this->createDirectory($directory, 02770)) {
      FileSecurity::writeHtaccess($directory);
    }
  }

  /** {@inheritdoc} */
  protected function createDirectory($directory, $mode = 02770) {
    if (is_dir($directory)) {
      return TRUE;
    }

    $parent = dirname($directory);
    if (!is_dir($parent) && !$this->createDirectory($parent, 02770)) {
      return FALSE;
    }

    // mkdir inherits the Homelab runtime directory's setgid bit and default
    // ACL. Do not chmod here: PHP-FPM runs as aculta, which is not a member of
    // www-data, so chmod would clear the inherited setgid bit.
    @mkdir($directory);
    if (is_dir($directory)) {
      $mode = fileperms($directory) & 07777;
      if (($mode & 0007) !== 0 || ($mode & 02000) === 0) {
        @rmdir($directory);
        trigger_error('PHP storage directory did not inherit restricted permissions.', E_USER_WARNING);
        return FALSE;
      }
      return TRUE;
    }

    trigger_error('mkdir(): Permission Denied', E_USER_WARNING);
    return FALSE;
  }

  /** {@inheritdoc} */
  public function garbageCollection() {
    $flags = \FilesystemIterator::CURRENT_AS_FILEINFO + \FilesystemIterator::SKIP_DOTS;

    foreach ($this->listAll() as $name) {
      $directory = $this->getContainingDirectoryFullPath($name);
      try {
        $iterator = new \FilesystemIterator($directory, $flags);
      }
      catch (\UnexpectedValueException) {
        continue;
      }

      $directoryUnlink = TRUE;
      $directoryMtime = filemtime($directory);
      foreach ($iterator as $fileInfo) {
        if ($directoryMtime > $fileInfo->getMTime()) {
          @unlink($fileInfo->getPathName());
        }
        else {
          $directoryUnlink = FALSE;
        }
      }

      if ($directoryUnlink) {
        $this->unlink($name);
      }
    }
  }

  /** {@inheritdoc} */
  protected function unlink($path) {
    if (!file_exists($path)) {
      return TRUE;
    }

    if (is_dir($path)) {
      foreach (new \DirectoryIterator($path) as $fileInfo) {
        if (!$fileInfo->isDot()) {
          $this->unlink($fileInfo->getPathName());
        }
      }
      return @rmdir($path);
    }

    return @unlink($path);
  }

}
