<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Pdf;

use Drupal\media\MediaInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Builds an access-checked PDF.js render array.
 *
 * Supports Media and file field documents.
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
   * @param array $settings
   *   Formatter settings that override the global viewer settings.
   *
   * @return array
   *   A render array with cacheability and access metadata.
   */
  public function build(
    MediaInterface $media,
    string $field_name = 'field_media_document',
    array $settings = [],
  ): array;

  /**
   * Builds the shared viewer for a file field outside Media.
   *
   * The caller must add the containing entity and field access decisions to
   * the supplied cacheability metadata before calling this method.
   *
   * @param \Drupal\file\FileInterface $file
   *   The PDF file to render.
   * @param string $title
   *   Accessible title for the viewer.
   * @param string $expected_scheme
   *   Required stream wrapper scheme.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability inherited from the containing entity and field.
   * @param bool $require_file_view_access
   *   Whether File view access is required in addition to download access.
   * @param array $settings
   *   Formatter settings that override the global viewer settings.
   *
   * @return array
   *   A render array with cacheability and access metadata.
   */
  public function buildFile(
    FileInterface $file,
    string $title,
    string $expected_scheme,
    CacheableMetadata $cacheability,
    bool $require_file_view_access = FALSE,
    array $settings = [],
  ): array;

}
