<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Spreadsheet;

use Drupal\file\FileInterface;

/**
 * Builds a local, read-only spreadsheet viewer for an authorized File.
 */
interface SpreadsheetViewerBuilderInterface {

  /**
   * Builds the viewer or returns NULL when the file is not safe to present.
   *
   * @param \Drupal\file\FileInterface $file
   *   The authorized managed file.
   * @param string $title
   *   An accessible title for the document.
   *
   * @return array<string, mixed>|null
   *   The viewer render array, or NULL to keep the Core file link.
   */
  public function build(FileInterface $file, string $title): ?array;

}
