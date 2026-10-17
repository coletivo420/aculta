<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Unit\Plugin\Field\FieldFormatter;

use Drupal\aculta_pdf_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\aculta_pdf_viewer\Plugin\Field\FieldFormatter\AcultaPdfFileFormatter;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the shared formatter for institutional File fields.
 */
final class AcultaPdfFileFormatterTest extends UnitTestCase {

  /**
   * Tests that a public PDF file field uses the shared viewer builder.
   */
  public function testAuthorizedPdfUsesSharedViewerBuilder(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('access')->with('view', NULL, TRUE)->willReturn(AccessResult::allowed());
    $node->method('label')->willReturn('Documento institucional');
    $node->method('getCacheTags')->willReturn(['node:123']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->with('download', NULL, TRUE)->willReturn(AccessResult::allowed());
    $file->method('getCacheTags')->willReturn(['file:456']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('documento.pdf');

    $items = $this->createMock(FileFieldItemList::class);
    $items->method('getEntity')->willReturn($node);
    $items->method('access')->with('view', NULL, TRUE)->willReturn(AccessResult::allowed());
    $items->method('referencedEntities')->willReturn([$file]);

    $viewer = ['#type' => 'container', '#attributes' => ['data-aculta-pdf-viewer' => 'true']];
    $builder = $this->createMock(PdfViewerBuilderInterface::class);
    $builder->expects(self::once())
      ->method('buildFile')
      ->with(
        $file,
        'Documento institucional',
        'public',
        self::callback(static fn ($cacheability): bool =>
          count(array_intersect(['file:456', 'node:123'], $cacheability->getCacheTags())) === 2
        ),
        FALSE,
      )
      ->willReturn($viewer);

    $formatter = new AcultaPdfFileFormatter(
      'aculta_pdf_file',
      [],
      $this->createMock(FieldDefinitionInterface::class),
      [],
      'hidden',
      'default',
      [],
      $builder,
    );

    self::assertSame($viewer, $formatter->viewElements($items, 'pt-br')[0]);
  }

  /**
   * Tests that a denied containing entity prevents viewer and file access.
   */
  public function testDeniedEntityDoesNotBuildViewer(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('access')->with('view', NULL, TRUE)
      ->willReturn(AccessResult::forbidden()->addCacheContexts(['user.permissions']));
    $node->method('getCacheTags')->willReturn(['node:124']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);

    $items = $this->createMock(FileFieldItemList::class);
    $items->method('getEntity')->willReturn($node);
    $items->method('access')->with('view', NULL, TRUE)->willReturn(AccessResult::allowed());
    $items->expects(self::never())->method('referencedEntities');

    $builder = $this->createMock(PdfViewerBuilderInterface::class);
    $builder->expects(self::never())->method('buildFile');
    $formatter = $this->createFormatter($builder);

    $elements = $formatter->viewElements($items, 'pt-br');

    self::assertFalse($elements[0]['#access']);
    self::assertContains('user.permissions', $elements[0]['#cache']['contexts']);
    self::assertContains('node:124', $elements[0]['#cache']['tags']);
  }

  /**
   * Tests that non-PDF files retain the Core file link formatter.
   */
  public function testNonPdfRetainsCoreFileLink(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('access')->with('view', NULL, TRUE)->willReturn(AccessResult::allowed());
    $node->method('label')->willReturn('Documento institucional');
    $node->method('getCacheTags')->willReturn(['node:125']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->with('download', NULL, TRUE)->willReturn(AccessResult::allowed());
    $file->method('getCacheTags')->willReturn(['file:457']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('documento.txt');

    $items = $this->createMock(FileFieldItemList::class);
    $items->method('getEntity')->willReturn($node);
    $items->method('access')->with('view', NULL, TRUE)->willReturn(AccessResult::allowed());
    $items->method('referencedEntities')->willReturn([$file]);
    $items->method('get')->willReturn((object) ['description' => 'Baixar documento']);

    $builder = $this->createMock(PdfViewerBuilderInterface::class);
    $builder->expects(self::never())->method('buildFile');
    $formatter = $this->createFormatter($builder);

    $elements = $formatter->viewElements($items, 'pt-br');

    self::assertSame('file_link', $elements[0]['#theme']);
    self::assertSame($file, $elements[0]['#file']);
    self::assertSame('Baixar documento', $elements[0]['#description']);
    self::assertContains('node:125', $elements[0]['#cache']['tags']);
    self::assertContains('file:457', $elements[0]['#cache']['tags']);
  }

  /**
   * Tests that private PDFs keep the Core link rather than the public viewer.
   */
  public function testPrivatePdfRetainsCoreFileLink(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('access')->with('view', NULL, TRUE)->willReturn(AccessResult::allowed());
    $node->method('label')->willReturn('Documento restrito');
    $node->method('getCacheTags')->willReturn(['node:126']);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->with('download', NULL, TRUE)->willReturn(AccessResult::allowed());
    $file->method('getCacheTags')->willReturn(['file:458']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('documento.pdf');

    $items = $this->createMock(FileFieldItemList::class);
    $items->method('getEntity')->willReturn($node);
    $items->method('access')->with('view', NULL, TRUE)->willReturn(AccessResult::allowed());
    $items->method('referencedEntities')->willReturn([$file]);
    $items->method('get')->willReturn((object) ['description' => 'Abrir arquivo']);

    $builder = $this->createMock(PdfViewerBuilderInterface::class);
    $builder->expects(self::once())
      ->method('buildFile')
      ->with(
        $file,
        'Documento restrito',
        'public',
        self::isInstanceOf(CacheableMetadata::class),
        FALSE,
      )
      ->willReturn([
        '#access' => FALSE,
        '#cache' => ['contexts' => ['user.permissions'], 'tags' => ['file:458'], 'max-age' => -1],
      ]);
    $formatter = $this->createFormatter($builder);

    $elements = $formatter->viewElements($items, 'pt-br');

    self::assertSame('file_link', $elements[0]['#theme']);
    self::assertSame($file, $elements[0]['#file']);
    self::assertContains('user.permissions', $elements[0]['#cache']['contexts']);
  }

  /**
   * Creates the field formatter with its shared builder dependency.
   */
  private function createFormatter(PdfViewerBuilderInterface $builder): AcultaPdfFileFormatter {
    return new AcultaPdfFileFormatter(
      'aculta_pdf_file',
      [],
      $this->createMock(FieldDefinitionInterface::class),
      [],
      'hidden',
      'default',
      [],
      $builder,
    );
  }

}
