<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Unit\Pdf;

use Drupal\aculta_image\GeneratedImageFileManager;
use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\ImageRenderer;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\aculta_pdf_viewer\Pdf\PdfPreviewThumbnailBuilder;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\file\Entity\File;
use Drupal\imagemagick\ImagemagickExecManagerInterface;
use Drupal\pdfpreview\PDFPreviewGenerator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests safe source handling for PDF thumbnail generation.
 */
#[Group('aculta_pdf_viewer')]
final class PdfPreviewThumbnailBuilderTest extends UnitTestCase {

  /**
   * Missing PDF source files fail soft without calling PDFPreview.
   */
  public function testMissingSourceSkipsPreviewGeneration(): void {
    $file = $this->createMock(File::class);
    $file->method('isPermanent')->willReturn(TRUE);
    $file->method('getFileUri')->willReturn('public://documento.pdf');
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('documento.pdf');
    $file->method('getSize')->willReturn(1024);

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->expects(self::once())
      ->method('realpath')
      ->with('public://documento.pdf')
      ->willReturn(FALSE);

    $preview_generator = $this->getMockBuilder(PDFPreviewGenerator::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getPDFPreview'])
      ->getMock();
    $preview_generator->expects(self::never())->method('getPDFPreview');

    $exec_manager = $this->createMock(ImagemagickExecManagerInterface::class);
    $exec_manager->expects(self::never())->method('setTimeout');

    $builder = new PdfPreviewThumbnailBuilder(
      $preview_generator,
      $this->createMock(ConfigFactoryInterface::class),
      new GeneratedImageFileManager(
        $this->createMock(EntityTypeManagerInterface::class),
        $file_system,
        $this->createMock(MimeTypeGuesser::class),
        new ImageInspector($this->createMock(ImageFactory::class)),
        $this->getMockBuilder(TimeInterface::class)->getMock(),
      ),
      new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class)),
      new ImageInspector($this->createMock(ImageFactory::class)),
      $file_system,
      $this->createMock(LoggerInterface::class),
      $exec_manager,
    );

    self::assertNull($builder->build($file, 'Documento'));
  }

  /**
   * A stale File entity size cannot bypass the source processing limit.
   */
  public function testOversizedSourceSkipsPreviewGeneration(): void {
    $path = tempnam(sys_get_temp_dir(), 'aculta_pdf_preview_');
    self::assertIsString($path);
    try {
      $handle = fopen($path, 'wb');
      self::assertIsResource($handle);
      self::assertTrue(ftruncate($handle, 20_971_521));
      fclose($handle);

      $file = $this->createMock(File::class);
      $file->method('isPermanent')->willReturn(TRUE);
      $file->method('getFileUri')->willReturn('public://documento.pdf');
      $file->method('getMimeType')->willReturn('application/pdf');
      $file->method('getFilename')->willReturn('documento.pdf');
      // Entity metadata is stale and understates the actual file size.
      $file->method('getSize')->willReturn(1024);

      $file_system = $this->createMock(FileSystemInterface::class);
      $file_system->expects(self::once())
        ->method('realpath')
        ->with('public://documento.pdf')
        ->willReturn($path);

      $preview_generator = $this->getMockBuilder(PDFPreviewGenerator::class)
        ->disableOriginalConstructor()
        ->onlyMethods(['getPDFPreview'])
        ->getMock();
      $preview_generator->expects(self::never())->method('getPDFPreview');

      $exec_manager = $this->createMock(ImagemagickExecManagerInterface::class);
      $exec_manager->expects(self::never())->method('setTimeout');

      $builder = new PdfPreviewThumbnailBuilder(
        $preview_generator,
        $this->createMock(ConfigFactoryInterface::class),
        new GeneratedImageFileManager(
          $this->createMock(EntityTypeManagerInterface::class),
          $file_system,
          $this->createMock(MimeTypeGuesser::class),
          new ImageInspector($this->createMock(ImageFactory::class)),
          $this->getMockBuilder(TimeInterface::class)->getMock(),
        ),
        new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class)),
        new ImageInspector($this->createMock(ImageFactory::class)),
        $file_system,
        $this->createMock(LoggerInterface::class),
        $exec_manager,
      );

      self::assertNull($builder->build($file, 'Documento'));
    }
    finally {
      unlink($path);
    }
  }

}
