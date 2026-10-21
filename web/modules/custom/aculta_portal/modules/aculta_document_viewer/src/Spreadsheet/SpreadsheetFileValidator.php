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
   * Limites de descompactação do ODS (mesmos do XLSX).
   */
  public const MAX_ENTRIES = 2000;
  public const MAX_UNCOMPRESSED_BYTES = 200 * 1024 * 1024;
  public const MAX_RATIO = 100;

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
      if ($zip->numFiles > self::MAX_ENTRIES) {
        throw new DocxValidationException('too_many_entries', "ODS com {$zip->numFiles} entradas.");
      }
      $total = 0;
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat === FALSE) {
          throw new DocxValidationException('corrupt_zip', "Entrada {$i} ilegível.");
        }
        $name = (string) $stat['name'];
        if (DocxPackageValidator::isUnsafeEntryName($name) || preg_match('#^(Basic|Scripts)/#', $name)) {
          throw new DocxValidationException('forbidden_part', "Parte não permitida no ODS: {$name}.");
        }
        $declared = (int) $stat['size'];
        $compressed = max(1, (int) $stat['comp_size']);
        $size = DocxPackageValidator::measureEntry($zip, $name, self::MAX_UNCOMPRESSED_BYTES - $total);
        if ($size > self::MAX_UNCOMPRESSED_BYTES - $total) {
          throw new DocxValidationException('uncompressed_too_large', 'ODS descompactado acima do limite.');
        }
        if (max($size, $declared) > 1024 * 1024 && intdiv($size, $compressed) > self::MAX_RATIO) {
          throw new DocxValidationException('zip_bomb', "Razão de compressão suspeita em {$name}.");
        }
        $total += $size;
        if ($total > self::MAX_UNCOMPRESSED_BYTES) {
          throw new DocxValidationException('uncompressed_too_large', 'ODS descompactado acima do limite.');
        }
      }
    }
    finally {
      $zip->close();
    }
  }

  /**
   * XLS é um arquivo OLE2: só a assinatura é verificada aqui. Macros VBA dentro do XLS
   * não são inspecionadas; o leitor não as executa (SheetJS só lê valores).
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
