<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Docx;

/**
 * Valida a estrutura OOXML de um DOCX antes de qualquer renderização.
 *
 * Extensão e MIME informados pelo Drupal não bastam: o arquivo precisa ser um
 * pacote ZIP com as partes exigidas por WordprocessingML, dentro de limites de
 * tamanho, entradas e descompressão. Não extrai nada em disco e não interpreta
 * XML além de procurar o tipo de conteúdo principal.
 */
final class DocxPackageValidator {

  /**
   * Tipo de conteúdo do documento principal WordprocessingML.
   */
  public const MAIN_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';

  /**
   * Tipo de conteúdo do workbook principal SpreadsheetML (XLSX).
   */
  public const XLSX_MAIN_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml';

  /**
   * Partes obrigatórias de um pacote DOCX.
   */
  public const DOCX_REQUIRED_PARTS = ['[Content_Types].xml', 'word/document.xml'];

  /**
   * Partes obrigatórias de um pacote XLSX.
   */
  public const XLSX_REQUIRED_PARTS = ['[Content_Types].xml', 'xl/workbook.xml'];

  /**
   * Partes que nunca devem existir num DOCX visualizado (macros e HTML).
   */
  private const FORBIDDEN_PART_PATTERNS = ['/vbaProject\.bin$/i', '/\.(html?|xhtml)$/i'];

  /**
   * Constructs the validator with explicit limits.
   *
   * @param int $maxBytes
   *   Tamanho máximo do arquivo compactado.
   * @param int $maxEntries
   *   Número máximo de entradas no pacote.
   * @param int $maxUncompressedBytes
   *   Soma máxima dos tamanhos descompactados.
   * @param int $maxRatio
   *   Razão máxima descompactado/compactado por entrada relevante.
   * @param string[] $requiredParts
   *   Partes exigidas no pacote (padrão: DOCX).
   * @param string $mainContentType
   *   Tipo de conteúdo principal declarado em [Content_Types].xml (padrão: DOCX).
   */
  public function __construct(
    private readonly int $maxBytes = 25 * 1024 * 1024,
    private readonly int $maxEntries = 2000,
    private readonly int $maxUncompressedBytes = 200 * 1024 * 1024,
    private readonly int $maxRatio = 100,
    private readonly array $requiredParts = self::DOCX_REQUIRED_PARTS,
    private readonly string $mainContentType = self::MAIN_CONTENT_TYPE,
  ) {}

  /**
   * Creates the validator for XLSX workbooks with the same limits.
   */
  public static function forXlsx(): self {
    return new self(requiredParts: self::XLSX_REQUIRED_PARTS, mainContentType: self::XLSX_MAIN_CONTENT_TYPE);
  }

  /**
   * Validates the package at the given path.
   *
   * @throws \Drupal\aculta_document_viewer\Docx\DocxValidationException
   *   When the file is not a DOCX package accepted by the viewer.
   */
  public function validate(string $path): void {
    if (!is_file($path) || !is_readable($path)) {
      throw new DocxValidationException('unreadable', 'Arquivo ausente ou ilegível.');
    }
    $size = filesize($path);
    if ($size === FALSE || $size === 0) {
      throw new DocxValidationException('empty', 'Arquivo vazio.');
    }
    if ($size > $this->maxBytes) {
      throw new DocxValidationException('too_large', "Arquivo com {$size} bytes acima do limite.");
    }
    $signature = file_get_contents($path, FALSE, NULL, 0, 4);
    if ($signature !== "PK\x03\x04") {
      throw new DocxValidationException('not_zip', 'Assinatura ZIP ausente.');
    }

    $zip = new \ZipArchive();
    $opened = $zip->open($path, \ZipArchive::RDONLY);
    if ($opened !== TRUE) {
      throw new DocxValidationException('corrupt_zip', "ZipArchive recusou o arquivo (código {$opened}).");
    }
    try {
      $this->validateEntries($zip);
      $this->validateContentTypes($zip);
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Checks entry count, sizes, names, forbidden parts and required parts.
   */
  private function validateEntries(\ZipArchive $zip): void {
    $count = $zip->numFiles;
    if ($count > $this->maxEntries) {
      throw new DocxValidationException('too_many_entries', "Pacote com {$count} entradas.");
    }
    $names = [];
    $total = 0;
    for ($i = 0; $i < $count; $i++) {
      $stat = $zip->statIndex($i);
      if ($stat === FALSE) {
        throw new DocxValidationException('corrupt_zip', "Entrada {$i} ilegível.");
      }
      $name = $stat['name'];
      if (str_starts_with($name, '/') || str_contains($name, '../') || str_contains($name, '\\') || preg_match('/^[A-Za-z]:/', $name)) {
        throw new DocxValidationException('unsafe_path', 'Caminho de entrada inseguro.');
      }
      foreach (self::FORBIDDEN_PART_PATTERNS as $pattern) {
        if (preg_match($pattern, $name)) {
          throw new DocxValidationException('forbidden_part', "Parte não permitida: {$name}.");
        }
      }
      $size = (int) $stat['size'];
      $compressed = max(1, (int) $stat['comp_size']);
      if ($size > 1024 * 1024 && intdiv($size, $compressed) > $this->maxRatio) {
        throw new DocxValidationException('zip_bomb', "Razão de compressão suspeita em {$name}.");
      }
      $total += $size;
      if ($total > $this->maxUncompressedBytes) {
        throw new DocxValidationException('uncompressed_too_large', 'Conteúdo descompactado acima do limite.');
      }
      $names[] = $name;
    }
    foreach ($this->requiredParts as $part) {
      if (!in_array($part, $names, TRUE)) {
        throw new DocxValidationException('missing_part', "Parte obrigatória ausente: {$part}.");
      }
    }
  }

  /**
   * Confirms the package declares a WordprocessingML main document.
   */
  private function validateContentTypes(\ZipArchive $zip): void {
    $xml = $zip->getFromName('[Content_Types].xml', 0);
    if ($xml === FALSE || !str_contains($xml, $this->mainContentType)) {
      throw new DocxValidationException('wrong_content_type', 'Tipo principal do pacote não declarado.');
    }
  }

}
