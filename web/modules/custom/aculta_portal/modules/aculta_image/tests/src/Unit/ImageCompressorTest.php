<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\Compression\ImageCompressor;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests configurable inline image processing.
 */
#[Group('aculta_image')]
class ImageCompressorTest extends UnitTestCase {

  /**
   * Confirms disabled processing leaves the file untouched.
   */
  public function testDisabledProcessingLeavesFileUntouched(): void {
    $compressor = $this->compressor(['compression_enabled' => FALSE]);
    $file = $this->createMock(FileInterface::class);
    $file->expects($this->never())->method('save');
    $this->assertFalse($compressor->processFile($file, '/not-used.png'));
  }

  /**
   * Converts a PNG to WebP and updates the managed file metadata.
   */
  public function testWebpConversionUpdatesFileEntity(): void {
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
      $this->markTestSkipped('GD com WebP não está disponível neste PHP.');
    }
    $directory = sys_get_temp_dir() . '/aculta-image-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $path = $directory . '/inline.png';
    $source = imagecreatetruecolor(48, 24);
    imagepng($source, $path);
    imagedestroy($source);

    $file = $this->createMock(FileInterface::class);
    $file->method('getMimeType')->willReturn('image/png');
    $file->method('getFileUri')->willReturn('public://inline-images/inline.png');
    $file->expects($this->once())->method('setFileUri')->with('public://inline-images/inline.webp');
    $file->expects($this->once())->method('setFilename')->with('inline.webp');
    $file->expects($this->once())->method('setMimeType')->with('image/webp');
    $file->expects($this->once())->method('save');

    try {
      $compressor = $this->compressor([
        'backend' => 'gd',
        'supported_mime_types' => ['image/png'],
        'webp_enabled' => TRUE,
        'webp_quality' => 82,
        'max_width' => 1200,
      ]);
      $this->assertTrue($compressor->processFile($file, $path));
      $this->assertFileExists($directory . '/inline.webp');
      $this->assertSame('image/webp', mime_content_type($directory . '/inline.webp'));
      $this->assertFileDoesNotExist($path);
    }
    finally {
      foreach (glob($directory . '/*') ?: [] as $temporary) {
        unlink($temporary);
      }
      rmdir($directory);
    }
  }

  /**
   * Creates the service with isolated settings.
   *
   * @param array<string, mixed> $settings
   *   Configuration overrides.
   */
  private function compressor(array $settings): ImageCompressor {
    $config = new class($settings) {

      public function __construct(private readonly array $settings) {}

      /**
       * Returns the fixture configuration.
       */
      public function getRawData(): array {
        return $this->settings;
      }

    };
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('aculta_image.settings')->willReturn($config);
    return new ImageCompressor($config_factory, $this->createMock(LoggerChannelFactoryInterface::class));
  }

}
