<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

/**
 * Verifica se o conteúdo do arquivo corresponde à extensão declarada.
 *
 * Conceito portado do File Upload Secure Validator: o tipo é detectado pelo
 * conteúdo (fileinfo), não pelo nome. Implementação própria, sem dependência
 * do módulo contrib.
 */
final class MimeTypeVerifier {

  /**
   * Tipos MIME aceitos para cada extensão do catálogo central.
   */
  private const MIME_BY_EXTENSION = [
    'png' => ['image/png'],
    'gif' => ['image/gif'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'webp' => ['image/webp'],
    'pdf' => ['application/pdf'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
  ];

  /**
   * Detecta o tipo MIME real pelo conteúdo, ou NULL se não for possível.
   */
  public function detect(string $bytes): ?string {
    if ($bytes === '' || !function_exists('finfo_open')) {
      return NULL;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === FALSE) {
      return NULL;
    }
    $mime = finfo_buffer($finfo, $bytes);
    return is_string($mime) && $mime !== '' ? $mime : NULL;
  }

  /**
   * Indica se o conteúdo é compatível com a extensão declarada.
   *
   * Extensões fora do catálogo central são recusadas.
   */
  public function matches(string $bytes, string $extension): bool {
    $extension = strtolower($extension);
    $allowed = self::MIME_BY_EXTENSION[$extension] ?? NULL;
    if ($allowed === NULL) {
      return FALSE;
    }
    $mime = $this->detect($bytes);
    return $mime !== NULL && in_array($mime, $allowed, TRUE);
  }

}
