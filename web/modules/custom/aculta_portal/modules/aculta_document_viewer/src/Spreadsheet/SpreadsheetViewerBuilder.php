<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Spreadsheet;

use Drupal\aculta_document_viewer\Docx\DocxPackageValidator;
use Drupal\aculta_document_viewer\Docx\DocxValidationException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Psr\Log\LoggerInterface;

/**
 * Monta o render array do leitor de planilhas (SheetJS + Tabulator).
 *
 * Somente leitura: o viewer não grava o arquivo nem recalcula fórmulas. Aceita
 * só `public://` e `private://` e valida o pacote XLSX antes de entregar a URL.
 */
final class SpreadsheetViewerBuilder {

  /**
   * Biblioteca do leitor de planilhas (SheetJS, Tabulator e behavior).
   */
  public const LIBRARY = 'aculta_document_viewer/spreadsheet';

  /**
   * MIME type de planilhas XLSX (SpreadsheetML).
   */
  public const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

  /**
   * Constructs the builder.
   */
  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Builds the viewer for an authorized XLSX, or NULL to use the Core link.
   *
   * @return array<string, mixed>|null
   *   Render array, or NULL when the file must not be rendered.
   */
  public function build(FileInterface $file, string $title): ?array {
    $uri = $file->getFileUri();
    if (!str_starts_with($uri, 'public://') && !str_starts_with($uri, 'private://')) {
      return NULL;
    }
    $path = $this->fileSystem->realpath($uri);
    if ($path === FALSE || $path === '') {
      return NULL;
    }
    try {
      DocxPackageValidator::forXlsx()->validate($path);
    }
    catch (DocxValidationException $exception) {
      $this->logger->notice('XLSX rejeitado pela validação estrutural: @reason (fid @fid).', [
        '@reason' => $exception->getReason(),
        '@fid' => $file->id(),
      ]);
      return NULL;
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['aculta-spreadsheet-viewer'],
        'data-aculta-sheet' => 'true',
        'data-aculta-sheet-url' => $this->fileUrlGenerator->generateString($uri),
        'data-aculta-sheet-tabulator' => Url::fromUri('base:libraries/tabulator/dist/tabulator_esm.min.mjs')->toString(),
        'data-aculta-sheet-title' => $title,
      ],
      '#attached' => ['library' => [self::LIBRARY]],
      'viewer' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['aculta-spreadsheet-viewer__frame'], 'role' => 'region', 'aria-label' => $title],
      ],
    ];
  }

}
