<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Document;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Normalizes technical MIME types into accessible human-readable labels.
 */
class ReadableMime {

  use StringTranslationTrait;

  /**
   * Technical MIME to short display badge map.
   */
  private const MIME_MAP = [
    'application/pdf' => 'PDF',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
    'application/msword' => 'DOC',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
    'application/vnd.ms-excel' => 'XLS',
    'application/vnd.oasis.opendocument.text' => 'ODT',
    'application/vnd.oasis.opendocument.spreadsheet' => 'ODS',
    'text/csv' => 'CSV',
    'text/plain' => 'TXT',
    'application/zip' => 'ZIP',
    'application/x-zip-compressed' => 'ZIP',
    'application/x-tar' => 'TAR',
    'application/gzip' => 'GZ',
    'image/jpeg' => 'JPEG',
    'image/png' => 'PNG',
    'image/webp' => 'WebP',
    'image/gif' => 'GIF',
    'image/svg+xml' => 'SVG',
    'audio/mpeg' => 'MP3',
    'audio/ogg' => 'OGG',
    'audio/wav' => 'WAV',
    'video/mp4' => 'MP4',
    'video/webm' => 'WebM',
  ];

  public function __construct(?TranslationInterface $stringTranslation = NULL) {
    if ($stringTranslation !== NULL) {
      $this->stringTranslation = $stringTranslation;
    }
  }

  /**
   * Returns a concise human-readable badge label for a given MIME type.
   *
   * @param string $mimeType
   *   The mime type string.
   *
   * @return string
   *   Uppercase format name (e.g. 'PDF', 'DOCX', 'XLSX', or clean subtype).
   */
  public function getHumanReadableFormat(string $mimeType): string {
    $normalized = strtolower(trim($mimeType));
    if (isset(self::MIME_MAP[$normalized])) {
      return self::MIME_MAP[$normalized];
    }

    // Fallback: extract subtype after slash.
    if (str_contains($normalized, '/')) {
      $parts = explode('/', $normalized, 2);
      $subtype = strtoupper($parts[1]);
      // Remove any vendor prefixes like vnd.
      if (str_contains($subtype, '.')) {
        $subParts = explode('.', $subtype);
        $subtype = end($subParts);
      }
      return substr($subtype, 0, 6);
    }

    return 'ARQUIVO';
  }

  /**
   * Formats file size in bytes to a human-readable string with units.
   *
   * @param int $bytes
   *   Size in bytes.
   *
   * @return string
   *   Localized string like '2,4 MB' or '512 KB'.
   */
  public function formatFileSize(int $bytes): string {
    if ($bytes <= 0) {
      return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);

    if ($i === 0) {
      return $bytes . ' B';
    }

    $value = $bytes / pow(1024, $i);
    // Format with Brazilian Portuguese comma as decimal separator.
    $formatted = number_format($value, $value >= 10 ? 0 : 1, ',', '.');
    return $formatted . ' ' . $units[$i];
  }

  /**
   * Generates a complete accessible screen-reader announcement label.
   *
   * @param string $title
   *   Title or original filename.
   * @param string $mimeType
   *   Technical MIME type.
   * @param int $bytes
   *   Size in bytes.
   *
   * @return string
   *   E.g., "Baixar Relatório Anual (formato PDF, tamanho 2,4 MB)"
   */
  public function getAccessibleDownloadLabel(string $title, string $mimeType, int $bytes): string {
    $format = $this->getHumanReadableFormat($mimeType);
    $size = $this->formatFileSize($bytes);

    return sprintf('Baixar %s (formato %s, tamanho %s)', $title, $format, $size);
  }

}
