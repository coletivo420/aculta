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
use Drupal\Core\File\FileExists;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Image\ImageInterface;
use Drupal\file\FileInterface;
use Drupal\file\Upload\FileUploadHandlerInterface;
use Drupal\file\Upload\FileUploadResult;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(H5PEditorUploadManager::class)]
final class H5PEditorUploadManagerTest extends UnitTestCase {

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

  public function testStoresRasterUploadThroughAcultaImageAndCoreFileApi(): void {
    $path = tempnam(sys_get_temp_dir(), 'aculta-h5p-image-');
    self::assertNotFalse($path);
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/c9sAAAAASUVORK5CYII=', TRUE));
    $managedFile = $this->createMock(FileInterface::class);
    $managedFile->method('getFilename')->willReturn('capa.png');
    $managedFile->method('getMimeType')->willReturn('image/png');
    $managedFile->method('getFileUri')->willReturn('private://h5p-custom/editor/images/capa.png');
    $uploadResult = (new FileUploadResult())->setFile($managedFile);
    $handler = $this->createMock(FileUploadHandlerInterface::class);
    $handler->expects(self::once())
      ->method('handleFileUpload')
      ->with(
        self::isInstanceOf(\Drupal\file\Upload\FormUploadedFile::class),
        ['FileExtension' => ['extensions' => 'png gif jpg jpeg webp'], 'FileSizeLimit' => ['fileLimit' => 10485760]],
        'private://h5p-custom/editor/images',
        FileExists::Rename,
      )
      ->willReturn($uploadResult);
    $image = $this->createMock(ImageInterface::class);
    $image->method('isValid')->willReturn(TRUE);
    $image->method('getWidth')->willReturn(1);
    $image->method('getHeight')->willReturn(1);
    $imageFactory = $this->createMock(ImageFactory::class);
    $imageFactory->method('get')->with('private://h5p-custom/editor/images/capa.png')->willReturn($image);
    $inspector = new ImageInspector($imageFactory);
    $imageIngestor = new ImageUploadIngestor(new UploadPolicy(), $inspector, $handler);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->expects(self::once())->method('prepareDirectory')->willReturn(TRUE);
    $config = $this->createMock(\Drupal\Core\Config\ImmutableConfig::class);
    $config->method('get')->with('aculta_lms_h5p_default_path')->willReturn('h5p-custom');
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_lms_h5p.settings')->willReturn($config);
    $manager = new H5PEditorUploadManager(
      $this->createMock(FileUploadHandlerInterface::class),
      $imageIngestor,
      $inspector,
      $fileSystem,
      $configFactory,
      $this->framework(),
    );
    try {
      $result = $manager->upload(
        new UploadedFile($path, 'capa.png', 'image/png', UPLOAD_ERR_OK, TRUE),
        ['type' => 'image', 'mimes' => ['image/png']],
      );
      self::assertSame(['path' => 'images/capa.png#tmp', 'mime' => 'image/png', 'width' => 1, 'height' => 1], $result);
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
      $this->framework(),
    );
  }

  private function framework(): H5PDrupal {
    $framework = $this->createMock(H5PDrupal::class);
    $framework->method('getWhitelist')->willReturn(\H5PCore::$defaultContentWhitelist);
    return $framework;
  }

}
