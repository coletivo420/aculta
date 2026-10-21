<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\ImageUploadIngestor;
use Drupal\aculta_image\UploadPolicy;
use Drupal\file\Upload\FileUploadHandlerInterface;
use Drupal\file\Upload\FileUploadResult;
use Drupal\file\Upload\FormUploadedFile;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** @coversDefaultClass \Drupal\aculta_image\ImageUploadIngestor */
final class ImageUploadIngestorTest extends UnitTestCase {

  /** @covers ::upload */
  public function testRejectsAnImageWithMismatchedExtension(): void {
    $path = $this->pngFile();
    $handler = $this->createMock(FileUploadHandlerInterface::class);
    $handler->expects(self::never())->method('handleFileUpload');
    try {
      $this->ingestor($handler)->upload(
        new FormUploadedFile(new UploadedFile($path, 'image.jpg', 'image/jpeg', UPLOAD_ERR_OK, TRUE)),
        'private://h5p/editor/images',
      );
      self::fail('Mismatched image extension was accepted.');
    }
    catch (\InvalidArgumentException) {
      self::assertTrue(TRUE);
    }
    finally {
      unlink($path);
    }
  }

  /** @covers ::upload */
  public function testUsesTheSharedPolicyAndManagedFileHandler(): void {
    $path = $this->pngFile();
    $handler = $this->createMock(FileUploadHandlerInterface::class);
    $handler->expects(self::once())
      ->method('handleFileUpload')
      ->with(
        self::isInstanceOf(FormUploadedFile::class),
        ['FileExtension' => ['extensions' => UploadPolicy::EXTENSIONS], 'FileSizeLimit' => ['fileLimit' => UploadPolicy::MAX_BYTES]],
        'private://h5p/editor/images',
        \Drupal\Core\File\FileExists::Rename,
      )
      ->willReturn(new FileUploadResult());
    try {
      $this->ingestor($handler)->upload(
        new FormUploadedFile(new UploadedFile($path, 'image.png', 'image/png', UPLOAD_ERR_OK, TRUE)),
        'private://h5p/editor/images',
        ['image/png'],
      );
    }
    finally {
      unlink($path);
    }
  }

  /**
   * A Forum-specific limit still passes through the central image policy.
   */
  public function testUsesValidatedContextualPolicy(): void {
    $path = $this->pngFile();
    $handler = $this->createMock(FileUploadHandlerInterface::class);
    $handler->expects(self::once())
      ->method('handleFileUpload')
      ->with(
        self::isInstanceOf(FormUploadedFile::class),
        ['FileExtension' => ['extensions' => 'png'], 'FileSizeLimit' => ['fileLimit' => 20 * 1024 * 1024]],
        'private://forum/attachments',
        \Drupal\Core\File\FileExists::Rename,
      )
      ->willReturn(new FileUploadResult());
    try {
      $this->ingestor($handler)->upload(
        new FormUploadedFile(new UploadedFile($path, 'image.png', 'image/png', UPLOAD_ERR_OK, TRUE)),
        'private://forum/attachments',
        ['image/png'],
        20 * 1024 * 1024,
        'png',
      );
    }
    finally {
      unlink($path);
    }
  }

  private function ingestor(FileUploadHandlerInterface $handler): ImageUploadIngestor {
    return new ImageUploadIngestor(
      new UploadPolicy(),
      new ImageInspector($this->createMock(\Drupal\Core\Image\ImageFactory::class)),
      $handler,
    );
  }

  private function pngFile(): string {
    $path = tempnam(sys_get_temp_dir(), 'aculta-image-upload-');
    self::assertNotFalse($path);
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/c9sAAAAASUVORK5CYII=', TRUE));
    return $path;
  }

}
