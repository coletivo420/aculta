<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Spreadsheet;

use Drupal\aculta_document_viewer\Docx\DocxValidationException;
use Drupal\aculta_document_viewer\Document\DocumentViewerChrome;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Extension\ModuleExtensionList;
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
   * Mapa extensão => MIME aceito pelo leitor (CSV aceita text/plain, comum no Drupal).
   */
  public const FORMATS = [
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
    'xls' => ['application/vnd.ms-excel'],
    'csv' => ['text/csv', 'text/plain'],
  ];

  /**
   * Constructs the builder.
   */
  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly FileSystemInterface $fileSystem,
    private readonly ModuleExtensionList $moduleList,
    private readonly SpreadsheetFileValidator $validator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Builds the viewer for an authorized spreadsheet, or NULL to use the Core link.
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
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    try {
      $this->validator->validate($path, $extension);
    }
    catch (DocxValidationException $exception) {
      $this->logger->notice('Planilha rejeitada pela validação estrutural: @reason (fid @fid).', [
        '@reason' => $exception->getReason(),
        '@fid' => $file->id(),
      ]);
      return NULL;
    }

    $modulePath = $this->moduleList->getPath('aculta_document_viewer');
    return [
      '#type' => 'container',
      '#attributes' => [
        'data-aculta-document-viewer' => 'spreadsheet',
        'data-aculta-sheet' => 'true',
        'data-aculta-sheet-url' => $this->fileUrlGenerator->generateString($uri),
        'data-aculta-sheet-format' => strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)),
        'data-aculta-sheet-tabulator' => Url::fromUri('base:libraries/tabulator/dist/tabulator_esm.min.mjs')->toString(),
        'data-aculta-sheet-frame-url' => Url::fromUri('base:' . $modulePath . '/js/sheet-frame.html')->toString(),
        'data-aculta-sheet-libs' => json_encode([Url::fromUri('base:libraries/sheetjs/dist/xlsx.full.min.js')->toString()], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'data-aculta-sheet-title' => $title,
      ],
      '#attached' => ['library' => [self::LIBRARY]],
      'chrome' => DocumentViewerChrome::elements($title, new TranslatableMarkup('Planilha')),
      'viewer' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['data-aculta-document-part' => 'frame', 'role' => 'region', 'aria-label' => $title],
      ],
    ];
  }

}
