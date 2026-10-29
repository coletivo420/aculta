<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Plugin\Field\FieldFormatter;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\aculta_document_viewer\Docx\DocxViewerBuilderInterface;
use Drupal\aculta_document_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\aculta_document_viewer\Plugin\Field\FieldFormatter\AcultaDocumentMediaFormatter;
use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetViewerBuilderInterface;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests format dispatch and access handling for document Media.
 */
#[CoversClass(AcultaDocumentMediaFormatter::class)]
#[Group('aculta_document_viewer')]
final class AcultaDocumentMediaFormatterTest extends UnitTestCase {

  /**
   * Tests that matching DOCX files use the DOCX viewer and retain a link.
   */
  public function testDocxViewerWithDownloadLink(): void {
    [, $file, $items] = $this->fieldItems(
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      filename: 'guia.docx',
    );
    $viewer = ['#type' => 'container'];
    $docx = $this->createMock(DocxViewerBuilderInterface::class);
    $docx->expects(self::once())->method('build')->with($file, 'Guia')->willReturn($viewer);
    $pdf = $this->createMock(PdfViewerBuilderInterface::class);
    $pdf->expects(self::never())->method('build');
    $spreadsheet = $this->createMock(SpreadsheetViewerBuilderInterface::class);
    $spreadsheet->expects(self::never())->method('build');

    $elements = $this->formatter($pdf, $docx, $spreadsheet)->viewElements($items, 'pt-br');

    self::assertSame($viewer['#type'], $elements[0]['#type']);
    self::assertSame('file_link', $elements[0]['link']['#theme']);
  }

  /**
   * Tests that valid XLSX MIME and extension use the spreadsheet viewer.
   */
  public function testSpreadsheetViewer(): void {
    [, $file, $items] = $this->fieldItems(
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      filename: 'dados.xlsx',
    );
    $viewer = ['#type' => 'container'];
    $spreadsheet = $this->createMock(SpreadsheetViewerBuilderInterface::class);
    $spreadsheet->expects(self::once())->method('build')->with($file, 'Guia')->willReturn($viewer);

    $elements = $this->formatter(
      $this->createMock(PdfViewerBuilderInterface::class),
      $this->createMock(DocxViewerBuilderInterface::class),
      $spreadsheet,
    )->viewElements($items, 'pt-br');

    self::assertSame($viewer['#type'], $elements[0]['#type']);
  }

  /**
   * Tests that unsupported or mismatched file types preserve the Core link.
   */
  public function testMismatchedMimeUsesCoreLink(): void {
    [, $file, $items] = $this->fieldItems('text/plain', 'dados.xlsx');
    $pdf = $this->createMock(PdfViewerBuilderInterface::class);
    $pdf->expects(self::never())->method('build');
    $docx = $this->createMock(DocxViewerBuilderInterface::class);
    $docx->expects(self::never())->method('build');
    $spreadsheet = $this->createMock(SpreadsheetViewerBuilderInterface::class);
    $spreadsheet->expects(self::never())->method('build');

    $elements = $this->formatter($pdf, $docx, $spreadsheet)->viewElements($items, 'pt-br');

    self::assertSame('file_link', $elements[0]['#theme']);
    self::assertSame($file, $elements[0]['#file']);
  }

  /**
   * Tests that access denial prevents every viewer builder from running.
   */
  public function testDeniedMediaDoesNotInvokeBuilders(): void {
    [, , $items] = $this->fieldItems('application/pdf', 'document.pdf', FALSE);
    $pdf = $this->createMock(PdfViewerBuilderInterface::class);
    $pdf->expects(self::never())->method('build');
    $docx = $this->createMock(DocxViewerBuilderInterface::class);
    $docx->expects(self::never())->method('build');
    $spreadsheet = $this->createMock(SpreadsheetViewerBuilderInterface::class);
    $spreadsheet->expects(self::never())->method('build');

    $elements = $this->formatter($pdf, $docx, $spreadsheet)->viewElements($items, 'pt-br');

    self::assertFalse($elements[0]['#access']);
  }

  /**
   * Creates the formatter with injectable reader builders.
   */
  private function formatter(
    PdfViewerBuilderInterface $pdf,
    DocxViewerBuilderInterface $docx,
    SpreadsheetViewerBuilderInterface $spreadsheet,
  ): AcultaDocumentMediaFormatter {
    return new AcultaDocumentMediaFormatter(
      'aculta_document_media',
      [],
      $this->createMock(FieldDefinitionInterface::class),
      [],
      'hidden',
      'default',
      [],
      $pdf,
      $docx,
      $spreadsheet,
    );
  }

  /**
   * Creates an authorized Media and file field list for a document.
   *
   * @return array{0: \Drupal\media\MediaInterface, 1: \Drupal\file\FileInterface, 2: \Drupal\file\Plugin\Field\FieldType\FileFieldItemList}
   *   Media, file and its field item list.
   */
  private function fieldItems(string $mime, string $filename, bool $mediaAccessAllowed = TRUE): array {
    $media = $this->createMock(MediaInterface::class);
    $media->method('access')->willReturn($mediaAccessAllowed ? AccessResult::allowed() : AccessResult::forbidden());
    $media->method('getCacheTags')->willReturn(['media:7']);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);
    $media->method('bundle')->willReturn('document');
    $media->method('label')->willReturn('Guia');

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->with('download', NULL, TRUE)->willReturn(AccessResult::allowed());
    $file->method('getCacheTags')->willReturn(['file:11']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getMimeType')->willReturn($mime);
    $file->method('getFilename')->willReturn($filename);

    $items = $this->createMock(FileFieldItemList::class);
    $items->method('getEntity')->willReturn($media);
    $items->method('getName')->willReturn('field_media_document');
    $items->method('access')->with('view', NULL, TRUE)->willReturn(AccessResult::allowed());
    $items->method('referencedEntities')->willReturn([$file]);
    $item = $this->createMock(FieldItemInterface::class);
    $item->method('__get')->with('description')->willReturn('');
    $items->method('get')->with(0)->willReturn($item);

    return [$media, $file, $items];
  }

}
