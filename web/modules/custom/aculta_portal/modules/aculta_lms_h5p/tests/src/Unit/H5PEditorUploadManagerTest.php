<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\ImageUploadIngestor;
use Drupal\aculta_image\UploadPolicy;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\aculta_lms_h5p\H5PEditor\H5PEditorUploadManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\Upload\FileUploadHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** @coversDefaultClass \Drupal\aculta_lms_h5p\H5PEditor\H5PEditorUploadManager */
final class H5PEditorUploadManagerTest extends UnitTestCase {

  /** @covers ::upload */
  public function testRejectsSvgAsAnUploadedContentImage(): void {
    $path = tempnam(sys_get_temp_dir(), 'aculta-h5p-svg-');
    self::assertNotFalse($path);
    file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    try {
      $manager = $this->manager();
      $result = $manager->upload(new UploadedFile($path, 'unsafe.svg', 'image/svg+xml', UPLOAD_ERR_OK, TRUE), ['type' => 'file']);
      self::assertFalse($result['success']);
      self::assertSame('Este campo aceita somente imagens raster permitidas.', $result['message']);
    }
    finally {
      unlink($path);
    }
  }

  /** @covers ::upload */
  public function testRejectsMissingH5pFieldType(): void {
    $path = tempnam(sys_get_temp_dir(), 'aculta-h5p-file-');
    self::assertNotFalse($path);
    file_put_contents($path, 'data');
    try {
      $result = $this->manager()->upload(new UploadedFile($path, 'data.txt', 'text/plain', UPLOAD_ERR_OK, TRUE), []);
      self::assertFalse($result['success']);
    }
    finally {
      unlink($path);
    }
  }

  private function manager(): H5PEditorUploadManager {
    $inspector = new ImageInspector($this->createMock(ImageFactory::class));
    return new H5PEditorUploadManager(
      $this->createMock(FileUploadHandlerInterface::class),
      new ImageUploadIngestor(new UploadPolicy(), $inspector, $this->createMock(FileUploadHandlerInterface::class)),
      $inspector,
      $this->createMock(FileSystemInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new H5PDrupal(),
    );
  }

}
