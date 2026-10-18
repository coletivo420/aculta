<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PDrupal;

use Drupal\Core\File\FileSystemInterface;

/** Installs locally reviewed H5P library packages with the official validator. */
final class H5PLibraryPackageInstaller {

  public function __construct(private readonly FileSystemInterface $fileSystem) {}

  /**
   * Validates and installs the libraries in a package, without importing content.
   */
  public function install(string $packagePath): int {
    $source = realpath($packagePath);
    if ($source === FALSE || !is_file($source) || strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'h5p') {
      throw new \InvalidArgumentException('Selecione um pacote H5P válido.');
    }
    $this->assertSafeArchivePaths($source);
    $root = H5PDrupal::getStoragePath();
    $tempDirectory = $root . '/package-' . bin2hex(random_bytes(16));
    if (!mkdir($tempDirectory, 0700) || !chmod($tempDirectory, 0700)) {
      throw new \RuntimeException('Não foi possível preparar o armazenamento temporário do pacote.');
    }
    $tempPackage = $tempDirectory . '/upload.h5p';
    try {
      if (!copy($source, $tempPackage)) {
        throw new \RuntimeException('Não foi possível preparar o pacote para validação.');
      }
      $interface = H5PDrupal::getInstance();
      $interface->getUploadedH5pFolderPath($tempDirectory);
      $interface->getUploadedH5pPath($tempPackage);
      $validator = H5PDrupal::getInstance('validator');
      if (!$validator->isValidPackage(TRUE)) {
        throw new \UnexpectedValueException('O pacote foi recusado pelo validador de bibliotecas H5P.');
      }
      $core = H5PDrupal::getInstance('core');
      if (empty($core->librariesJsonData) || !is_array($core->librariesJsonData)) {
        throw new \UnexpectedValueException('O pacote não contém bibliotecas H5P instaláveis.');
      }
      $number = count($core->librariesJsonData);
      H5PDrupal::getInstance('storage')->savePackage(NULL, NULL, TRUE);
      return $number;
    }
    finally {
      H5PDrupal::getInstance()->resetUploadedH5pPaths();
      $this->fileSystem->deleteRecursive($tempDirectory);
    }
  }

  /** Rejects ZIP paths that could escape the isolated extraction directory. */
  private function assertSafeArchivePaths(string $packagePath): void {
    $zip = new \ZipArchive();
    if ($zip->open($packagePath, \ZipArchive::RDONLY) !== TRUE) {
      throw new \UnexpectedValueException('O pacote H5P não é um arquivo ZIP válido.');
    }
    try {
      for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = $zip->statIndex($index);
        $name = $entry['name'] ?? NULL;
        if (!is_string($name) || $name === '' || str_contains($name, "\0") || str_contains($name, '\\')
          || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
          throw new \UnexpectedValueException('O pacote H5P contém um caminho inválido.');
        }
        $segments = explode('/', rtrim($name, '/'));
        foreach ($segments as $segment) {
          if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new \UnexpectedValueException('O pacote H5P contém um caminho inválido.');
          }
        }

        if (method_exists($zip, 'getExternalAttributesIndex')
          && $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)
          && $operatingSystem === \ZipArchive::OPSYS_UNIX
          && (($attributes >> 16) & 0170000) === 0120000) {
          throw new \UnexpectedValueException('O pacote H5P contém um link simbólico.');
        }
      }
    }
    finally {
      $zip->close();
    }
  }

}
