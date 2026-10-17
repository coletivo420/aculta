<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Pdf;

use Drupal\media\MediaInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Builds an access-checked PDF.js render array for Media or file field documents.
 */
interface PdfViewerBuilderInterface {

  /**
   * Builds a public PDF viewer for a document Media entity.
   *
   * Private Media documents use Drupal Core delivery and receive zero render
   * cache lifetime when permitted.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The document Media entity.
   * @param string $field_name
   *   The PDF file field on the Media entity.
   *
   * @return array
   *   A render array with cacheability and access metadata.
   */
  public function build(MediaInterface $media, string $field_name = 'field_media_document'): array;

  /**
   * Builds the shared viewer for a file field outside Media.
   *
   * The caller must add the containing entity and field access decisions to
   * the supplied cacheability metadata before calling this method.
   */
  public function buildFile(FileInterface $file, string $title, string $expected_scheme, CacheableMetadata $cacheability, bool $require_file_view_access = FALSE): array;

}
