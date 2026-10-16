<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Pdf;

use Drupal\media\MediaInterface;

/**
 * Builds an access-checked PDF.js render array for a Media document. */
interface PdfViewerBuilderInterface {

  /**
   * Builds a public PDF viewer for a document Media entity.
   *
   * Private files are intentionally not rendered until the protected delivery
   * path is integrated and validated in F3.
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

}
