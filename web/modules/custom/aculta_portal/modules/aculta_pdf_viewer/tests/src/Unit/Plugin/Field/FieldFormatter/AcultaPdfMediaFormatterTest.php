<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Unit\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\aculta_pdf_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\aculta_pdf_viewer\Plugin\Field\FieldFormatter\AcultaPdfMediaFormatter;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Media document formatter's viewer and fallback behavior.
 */
final class AcultaPdfMediaFormatterTest extends UnitTestCase {

  /**
   * Tests that authorized PDFs use the shared viewer builder.
   */
  public function testAuthorizedPdfUsesSharedViewerBuilder(): void {
    $media = $this->createMock(MediaInterface::class);
    $media->method('access')->willReturn(AccessResult::allowed());
    $media->method('getCacheTags')->willReturn(['media:7']);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);
    $media->method('bundle')->willReturn('document');

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->with('download', NULL, TRUE)->willReturn(AccessResult::allowed());
    $file->method('getCacheTags')->willReturn(['file:11']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('documento.pdf');

    $items = $this->fieldItems($media, $file);

    $viewer = ['#type' => 'container', '#attributes' => ['data-aculta-pdf-viewer' => 'true']];
    $builder = $this->createMock(PdfViewerBuilderInterface::class);
    $builder->expects(self::once())->method('build')->with($media)->willReturn($viewer);

    $elements = $this->formatter($builder)->viewElements($items, 'pt-br');

    self::assertSame($viewer, $elements[0]);
  }

  /**
   * Tests that non-PDF documents retain the Core file link.
   */
  public function testNonPdfKeepsCoreFileLink(): void {
    $media = $this->createMock(MediaInterface::class);
    $media->method('access')->willReturn(AccessResult::allowed());
    $media->method('getCacheTags')->willReturn(['media:8']);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);
    $media->method('bundle')->willReturn('document');

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->with('download', NULL, TRUE)->willReturn(AccessResult::allowed());
    $file->method('getCacheTags')->willReturn(['file:12']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getMimeType')->willReturn('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $file->method('getFilename')->willReturn('documento.docx');

    $items = $this->fieldItems($media, $file);

    $builder = $this->createMock(PdfViewerBuilderInterface::class);
    $builder->expects(self::never())->method('build');

    $elements = $this->formatter($builder)->viewElements($items, 'pt-br');

    self::assertSame('file_link', $elements[0]['#theme']);
    self::assertSame($file, $elements[0]['#file']);
    self::assertContains('media:8', $elements[0]['#cache']['tags']);
    self::assertContains('file:12', $elements[0]['#cache']['tags']);
  }

  /**
   * Tests that an unavailable viewer keeps its authorized download fallback.
   */
  public function testPdfWithoutViewerKeepsDownloadFallback(): void {
    $media = $this->createMock(MediaInterface::class);
    $media->method('access')->willReturn(AccessResult::allowed());
    $media->method('getCacheTags')->willReturn(['media:9']);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);
    $media->method('bundle')->willReturn('document');

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->with('download', NULL, TRUE)->willReturn(AccessResult::allowed());
    $file->method('getCacheTags')->willReturn(['file:13']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('privado.pdf');
    $items = $this->fieldItems($media, $file);

    $builder = $this->createMock(PdfViewerBuilderInterface::class);
    $builder->expects(self::once())->method('build')->with($media)->willReturn([
      '#access' => FALSE,
      '#cache' => ['contexts' => ['user.permissions']],
    ]);

    $elements = $this->formatter($builder)->viewElements($items, 'pt-br');

    self::assertSame('file_link', $elements[0]['#theme']);
    self::assertSame($file, $elements[0]['#file']);
    self::assertContains('media:9', $elements[0]['#cache']['tags']);
    self::assertContains('file:13', $elements[0]['#cache']['tags']);
    self::assertContains('user.permissions', $elements[0]['#cache']['contexts']);
  }

  /**
   * Creates a formatter using the provided viewer builder.
   */
  private function formatter(PdfViewerBuilderInterface $builder): AcultaPdfMediaFormatter {
    return new AcultaPdfMediaFormatter(
      'aculta_pdf_media',
      [],
      $this->createMock(FieldDefinitionInterface::class),
      [],
      'hidden',
      'default',
      [],
      $builder,
    );
  }

  /**
   * Creates a file field list referencing the given media and file.
   */
  private function fieldItems(MediaInterface $media, FileInterface $file): FileFieldItemList {
    $items = $this->createMock(FileFieldItemList::class);
    $items->method('getEntity')->willReturn($media);
    $items->method('access')->with('view', NULL, TRUE)->willReturn(AccessResult::allowed());
    $items->method('referencedEntities')->willReturn([$file]);
    $item = $this->createMock(FieldItemInterface::class);
    $item->method('__get')->with('description')->willReturn('');
    $items->method('get')->with(0)->willReturn($item);
    return $items;
  }

}
