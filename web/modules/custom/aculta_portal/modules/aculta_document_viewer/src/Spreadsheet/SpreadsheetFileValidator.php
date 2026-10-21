<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Spreadsheet;

use Drupal\aculta_document_viewer\Docx\DocxPackageValidator;
use Drupal\aculta_document_viewer\Docx\DocxValidationException;

/**
 * Valida planilhas antes da leitura, conforme o formato declarado pela extensão.
 *
 * Formatos aceitos no leitor: XLSX, ODS, XLS (BIFF) e CSV. Nenhum formato é
 * executado: macros e scripts são recusados quando o contêiner os traz.
 */
final class SpreadsheetFileValidator {

  /**
   * Tamanho máximo do arquivo para qualquer formato.
   */
  public const MAX_BYTES = 25 * 1024 * 1024;

  /**
   * MIME type do OpenDocument Spreadsheet, gravado no entry `mimetype`.
   */
  public const ODS_MIME = 'application/vnd.oasis.opendocument.spreadsheet';

  /**
   * Assinatura OLE2 (Compound File Binary), usada pelo XLS.
   */
  private const OLE2_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

  /**
   * Extensões aceitas pelo leitor.
   */
  public const EXTENSIONS = ['xlsx', 'ods', 'xls', 'csv'];

  /**
   * Validates the file at $path for the given extension.
   *
   * @throws \Drupal\aculta_document_viewer\Docx\DocxValidationException
   */
  public function validate(string $path, string $extension): void {
    $extension = strtolower($extension);
    if (!in_array($extension, self::EXTENSIONS, TRUE)) {
      throw new DocxValidationException('unsupported_format', "Extensão não suportada: {$extension}.");
    }
    if (!is_file($path) || !is_readable($path)) {
      throw new DocxValidationException('unreadable', 'Arquivo ausente ou ilegível.');
    }
    $size = filesize($path);
    if ($size === FALSE || $size === 0) {
      throw new DocxValidationException('empty', 'Arquivo vazio.');
    }
    if ($size > self::MAX_BYTES) {
      throw new DocxValidationException('too_large', "Arquivo com {$size} bytes acima do limite.");
    }
    match ($extension) {
      'xlsx' => DocxPackageValidator::forXlsx()->validate($path),
      'ods' => $this->validateOds($path),
      'xls' => $this->validateXls($path),
      'csv' => $this->validateCsv($path),
    };
  }

  /**
   * ODS é um ZIP com `mimetype` gravado e `content.xml`, sem macros.
   */
  private function validateOds(string $path): void {
    $zip = new \ZipArchive();
    if ($zip->open($path, \ZipArchive::RDONLY) !== TRUE) {
      throw new DocxValidationException('corrupt_zip', 'ZIP do ODS ilegível.');
    }
    try {
      $mimetype = $zip->getFromName('mimetype');
      if ($mimetype === FALSE || trim($mimetype) !== self::ODS_MIME) {
        throw new DocxValidationException('wrong_content_type', 'Tipo ODS não declarado.');
      }
      if ($zip->locateName('content.xml') === FALSE) {
        throw new DocxValidationException('missing_part', 'content.xml ausente no ODS.');
      }
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (preg_match('#^(Basic|Scripts)/#', $name) || str_contains($name, '../')) {
          throw new DocxValidationException('forbidden_part', "Parte não permitida no ODS: {$name}.");
        }
      }
    }
    finally {
      $zip->close();
    }
  }

  /**
   * XLS é um arquivo OLE2 com a assinatura do Compound File Binary.
   */
  private function validateXls(string $path): void {
    $signature = file_get_contents($path, FALSE, NULL, 0, 8);
    if ($signature !== self::OLE2_SIGNATURE) {
      throw new DocxValidationException('not_ole2', 'Assinatura OLE2 do XLS ausente.');
    }
  }

  /**
   * CSV é texto: recusa conteúdo binário (bytes NUL nos primeiros KB).
   */
  private function validateCsv(string $path): void {
    $head = file_get_contents($path, FALSE, NULL, 0, 4096);
    if ($head === FALSE || str_contains($head, "\0")) {
      throw new DocxValidationException('binary_content', 'CSV com conteúdo binário.');
    }
  }

}
