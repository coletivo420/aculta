<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Pdf;

use Drupal\file\FileInterface;

/**
 * Builds a safe optional first-page thumbnail for a public PDF.
 */
interface PdfPreviewThumbnailBuilderInterface {

  /**
   * Builds an image render array when generation is available and succeeds.
   *
   * Private, temporary, oversized, and unsupported files return NULL.
   */
  public function build(FileInterface $file, string $alt): ?array;

}
