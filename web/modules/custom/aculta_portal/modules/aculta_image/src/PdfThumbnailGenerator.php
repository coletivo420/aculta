<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

/**
 * Generates first-page PDF thumbnails with the PHP Imagick extension.
 *
 * This avoids relying on a command-line ImageMagick binary, which is not
 * available on every supported hosting plan. PDF input is limited, processed
 * as a single page under temporary resource limits, and written atomically.
 */
final class PdfThumbnailGenerator {

  private const MAX_SOURCE_BYTES = 20_971_520;

  private const WIDTH = 480;

  private const HEIGHT = 640;

  private const QUALITY = 82;

  private const DPI = 96;

  /**
   * Constructs the PDF thumbnail generator.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Generates or reuses a public JPEG derivative for a public PDF File.
   *
   * @return string|null
   *   The derivative URI, or NULL when generation is unavailable or unsafe.
   */
  public function generate(File $file): ?string {
    if (!$this->isEligibleFile($file) || !$this->isSafeConfiguration()) {
      return NULL;
    }
    if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
      return NULL;
    }

    if (!in_array('PDF', \Imagick::queryFormats('PDF'), TRUE)) {
      return NULL;
    }
    $sourcePath = $this->fileSystem->realpath($file->getFileUri());
    if (!is_string($sourcePath) || !$this->isValidPdfSource($sourcePath)) {
      return NULL;
    }

    $previewConfig = $this->configFactory->get('pdfpreview.settings');
    $previewDirectory = (string) $previewConfig->get('path');
    $destinationUri = 'public://' . $previewDirectory . '/' . md5('pdfpreview' . $file->id()) . '.jpg';
    $destinationDirectoryUri = $this->fileSystem->dirname($destinationUri);
    if (!$this->fileSystem->prepareDirectory($destinationDirectoryUri, FileSystemInterface::CREATE_DIRECTORY)) {
      return NULL;
    }
    $destinationDirectory = $this->fileSystem->realpath($destinationDirectoryUri);
    if (!is_string($destinationDirectory) || !is_dir($destinationDirectory)) {
      return NULL;
    }
    $destinationPath = $destinationDirectory . DIRECTORY_SEPARATOR . basename($destinationUri);
    if (is_link($destinationPath)) {
      return NULL;
    }
    clearstatcache(TRUE, $sourcePath);
    clearstatcache(TRUE, $destinationPath);
    if (is_file($destinationPath) && filemtime($sourcePath) <= filemtime($destinationPath)) {
      return $destinationUri;
    }

    return $this->writeThumbnail($sourcePath, $destinationPath, $destinationUri);
  }

  /**
   * Confirms the public PDF and processing limits before calling Imagick.
   */
  private function isEligibleFile(File $file): bool {
    if (!$file->isPermanent()
      || !str_starts_with($file->getFileUri(), 'public://')
      || strtolower((string) $file->getMimeType()) !== 'application/pdf'
      || strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) !== 'pdf'
      || $file->getSize() <= 0
      || $file->getSize() > self::MAX_SOURCE_BYTES) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Confirms the fixed PDFPreview configuration retained by this integration.
   */
  private function isSafeConfiguration(): bool {
    $preview = $this->configFactory->get('pdfpreview.settings');
    $image = $this->configFactory->get('system.file');
    return $image->get('default_scheme') === 'public'
      && $preview->get('path') === 'pdfpreview'
      && $preview->get('size') === '480x640'
      && $preview->get('quality') === self::QUALITY
      && $preview->get('type') === 'jpg'
      && $preview->get('filenames') === 'md5';
  }

  /**
   * Validates the real source path, current size and PDF signature.
   */
  private function isValidPdfSource(string $sourcePath): bool {
    if (!is_file($sourcePath) || !is_readable($sourcePath)) {
      return FALSE;
    }
    clearstatcache(TRUE, $sourcePath);
    $size = filesize($sourcePath);
    if (!is_int($size) || $size <= 0 || $size > self::MAX_SOURCE_BYTES) {
      return FALSE;
    }
    $header = file_get_contents($sourcePath, FALSE, NULL, 0, 1024);
    return is_string($header) && str_contains($header, '%PDF-');
  }

  /**
   * Renders only the first page and atomically replaces the thumbnail.
   */
  private function writeThumbnail(string $sourcePath, string $destinationPath, string $destinationUri): ?string {
    $temporaryDirectory = $this->fileSystem->getTempDirectory();
    if (!is_string($temporaryDirectory) || !is_dir($temporaryDirectory) || !is_writable($temporaryDirectory)) {
      return NULL;
    }

    $temporarySource = tempnam($temporaryDirectory, 'aculta-pdf-source-');
    $temporaryOutput = tempnam(dirname($destinationPath), '.aculta-pdf-preview-');
    if (!is_string($temporarySource) || !is_string($temporaryOutput)) {
      if (is_string($temporarySource) && is_file($temporarySource)) {
        unlink($temporarySource);
      }
      if (is_string($temporaryOutput) && is_file($temporaryOutput)) {
        unlink($temporaryOutput);
      }
      return NULL;
    }

    $image = NULL;
    $flattened = NULL;
    $resourceLimits = [];
    try {
      if (!copy($sourcePath, $temporarySource)) {
        return NULL;
      }
      $resourceLimits = $this->applyResourceLimits();
      $image = new \Imagick();
      $image->setResolution(self::DPI, self::DPI);
      $image->readImage($temporarySource . '[0]');
      $image->setIteratorIndex(0);
      $image->setImageBackgroundColor('#ffffff');
      $flattened = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
      $flattened->thumbnailImage(self::WIDTH, self::HEIGHT, TRUE, FALSE);
      $flattened->stripImage();
      $flattened->setImageFormat('jpeg');
      $flattened->setImageCompression(\Imagick::COMPRESSION_JPEG);
      $flattened->setImageCompressionQuality(self::QUALITY);
      if (!$flattened->writeImage($temporaryOutput)) {
        return NULL;
      }
      if (!is_file($temporaryOutput) || filesize($temporaryOutput) < 1) {
        return NULL;
      }
      if (!$this->fileSystem->chmod($temporaryOutput)) {
        return NULL;
      }
      if (!rename($temporaryOutput, $destinationPath)) {
        return NULL;
      }
      return $destinationUri;
    }
    finally {
      if ($flattened instanceof \Imagick) {
        $flattened->clear();
      }
      if ($image instanceof \Imagick) {
        $image->clear();
      }
      $this->restoreResourceLimits($resourceLimits);
      foreach ([$temporarySource, $temporaryOutput] as $temporaryPath) {
        if (is_file($temporaryPath)) {
          unlink($temporaryPath);
        }
      }
    }
  }

  /**
   * Limits ImageMagick resources for this conversion and records old values.
   *
   * Imagick resource limits are process-wide, so callers restore them in a
   * finally block before returning to Drupal request handling.
   *
   * @return array<int, int>
   *   Previous limits keyed by Imagick resource type.
   */
  private function applyResourceLimits(): array {
    $limits = [
      \Imagick::RESOURCETYPE_MEMORY => 134_217_728,
      \Imagick::RESOURCETYPE_MAP => 268_435_456,
      \Imagick::RESOURCETYPE_DISK => 536_870_912,
      \Imagick::RESOURCETYPE_AREA => 20_000_000,
      \Imagick::RESOURCETYPE_TIME => 15,
      \Imagick::RESOURCETYPE_THREAD => 1,
    ];
    $previous = [];
    foreach ($limits as $type => $limit) {
      $current = \Imagick::getResourceLimit($type);
      $previous[$type] = $current;
      $effective = $current > 0 ? min($current, $limit) : $limit;
      if (!\Imagick::setResourceLimit($type, $effective)) {
        $this->restoreResourceLimits($previous);
        throw new \RuntimeException('Unable to apply ImageMagick resource limits.');
      }
    }
    return $previous;
  }

  /**
   * Restores process-wide ImageMagick resource limits.
   *
   * @param array<int, int> $limits
   *   Previous limits keyed by Imagick resource type.
   */
  private function restoreResourceLimits(array $limits): void {
    foreach ($limits as $type => $limit) {
      \Imagick::setResourceLimit($type, $limit);
    }
  }

}
