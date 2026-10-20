<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Pdf;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\aculta_image\GeneratedImageFileManager;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_image\ImageInspector;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\imagemagick\ImagemagickExecManagerInterface;
use Drupal\pdfpreview\PDFPreviewGenerator;
use Psr\Log\LoggerInterface;

/**
 * Generates PDFPreview derivatives only for eligible public files.
 */
final class PdfPreviewThumbnailBuilder implements PdfPreviewThumbnailBuilderInterface {

  private const MAX_SOURCE_BYTES = 20_971_520;

  private const PROCESS_TIMEOUT_SECONDS = 15;

  private const DEFAULT_PROCESS_TIMEOUT_SECONDS = 60;

  public function __construct(
    private readonly ?PDFPreviewGenerator $previewGenerator,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly GeneratedImageFileManager $generatedImageFileManager,
    private readonly ImageRenderer $imageRenderer,
    private readonly ImageInspector $imageInspector,
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerInterface $logger,
    private readonly ?ImagemagickExecManagerInterface $imagemagickExecManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(FileInterface $file, string $alt): ?array {
    if (!$this->previewGenerator instanceof PDFPreviewGenerator
      || !$this->imagemagickExecManager instanceof ImagemagickExecManagerInterface
      || !$file instanceof File
      || !$file->isPermanent()
      || !str_starts_with($file->getFileUri(), 'public://')
      || strtolower((string) $file->getMimeType()) !== 'application/pdf'
      || strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) !== 'pdf'
      || $file->getSize() <= 0
      || $file->getSize() > self::MAX_SOURCE_BYTES
      || !$this->isReadableSourceWithinLimit($file)
      || !$this->isSafeConfiguration()) {
      return NULL;
    }

    try {
      // Avoid unavailable binaries and cap work during the web request.
      $this->imagemagickExecManager->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
      try {
        $preview_uri = $this->previewGenerator->getPDFPreview($file);
      }
      finally {
        // The exec manager is shared; restore ImageMagick's contrib default.
        $this->imagemagickExecManager->setTimeout(self::DEFAULT_PROCESS_TIMEOUT_SECONDS);
      }
      if (!is_string($preview_uri) || !str_starts_with($preview_uri, 'public://pdfpreview/')) {
        return NULL;
      }

      $preview_file = $this->generatedImageFileManager->registerTemporaryDerivative($preview_uri);
      if ($preview_file === NULL) {
        return NULL;
      }
      $dimensions = $this->imageInspector->dimensions($preview_uri);
      if ($dimensions === NULL) {
        return NULL;
      }

      $build = $this->imageRenderer->buildManagedImage($preview_file, $alt, [
        'width' => $dimensions['width'],
        'height' => $dimensions['height'],
        'loading' => 'lazy',
        'decoding' => 'async',
        'data-aculta-pdf-control' => 'thumbnail',
      ]);
      if ($build === NULL) {
        return NULL;
      }
      $cacheability = CacheableMetadata::createFromObject($file);
      $cacheability->addCacheContexts(['url.site']);
      $cacheability->applyTo($build);
      return $build;
    }
    catch (\Throwable $exception) {
      $this->logger->warning('PDF thumbnail generation failed for File entity {fid}; the reader and download fallback remain available.', [
        'fid' => $file->id(),
        'exception' => $exception,
      ]);
      return NULL;
    }
  }

  /**
   * Avoids invoking PDFPreview/ImageMagick for a missing source file.
   */
  private function isReadableSourceWithinLimit(File $file): bool {
    $realpath = $this->fileSystem->realpath($file->getFileUri());
    if (!is_string($realpath) || !is_file($realpath) || !is_readable($realpath)) {
      return FALSE;
    }

    clearstatcache(TRUE, $realpath);
    $actual_size = filesize($realpath);
    return is_int($actual_size) && $actual_size > 0 && $actual_size <= self::MAX_SOURCE_BYTES;
  }

  /**
   * Confirms the constrained settings expected by the contrib generator.
   */
  private function isSafeConfiguration(): bool {
    $preview = $this->configFactory->get('pdfpreview.settings');
    $image = $this->configFactory->get('system.file');
    return $image->get('default_scheme') === 'public'
      && $preview->get('path') === 'pdfpreview'
      && $preview->get('size') === '480x640'
      && $preview->get('quality') === 82
      && $preview->get('type') === 'jpg'
      && $preview->get('filenames') === 'md5';
  }

}
