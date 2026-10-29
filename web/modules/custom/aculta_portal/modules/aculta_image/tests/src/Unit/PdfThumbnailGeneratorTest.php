<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\PdfThumbnailGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests PDF thumbnail generation through the PHP Imagick extension.
 */
#[Group('aculta_image')]
final class PdfThumbnailGeneratorTest extends UnitTestCase {

  /**
   * Generates only the first PDF page and preserves its aspect ratio.
   */
  public function testGeneratesBoundedJpegThumbnail(): void {
    if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
      self::markTestSkipped('The PHP Imagick extension is unavailable.');
    }
    if (!in_array('PDF', \Imagick::queryFormats('PDF'), TRUE)) {
      self::markTestSkipped('The Imagick PDF coder is unavailable.');
    }

    $directory = sys_get_temp_dir() . '/aculta-pdf-thumbnail-' . bin2hex(random_bytes(6));
    self::assertTrue(mkdir($directory, 0700));
    $sourcePath = $directory . '/source.pdf';
    $pdf = $this->minimalPdf();
    self::assertSame(strlen($pdf), file_put_contents($sourcePath, $pdf));

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturnCallback(static fn (string $uri): string|false => match ($uri) {
      'public://source.pdf' => $sourcePath,
      'public://pdfpreview' => $directory,
      default => FALSE,
    });
    $fileSystem->method('dirname')->willReturn('public://pdfpreview');
    $fileSystem->method('prepareDirectory')->willReturn(TRUE);
    $fileSystem->method('getTempDirectory')->willReturn($directory);
    $fileSystem->method('chmod')->willReturn(TRUE);

    $previewConfig = $this->createMock(ImmutableConfig::class);
    $previewConfig->method('get')->willReturnMap([
      ['path', 'pdfpreview'],
      ['size', '480x640'],
      ['quality', 82],
      ['type', 'jpg'],
      ['filenames', 'md5'],
    ]);
    $fileConfig = $this->createMock(ImmutableConfig::class);
    $fileConfig->method('get')->with('default_scheme')->willReturn('public');
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnCallback(static fn (string $name): ImmutableConfig => match ($name) {
      'pdfpreview.settings' => $previewConfig,
      'system.file' => $fileConfig,
      default => throw new \InvalidArgumentException('Unexpected configuration. ' . $name),
    });

    $file = $this->createMock(File::class);
    $file->method('id')->willReturn('42');
    $file->method('isPermanent')->willReturn(TRUE);
    $file->method('getFileUri')->willReturn('public://source.pdf');
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('source.pdf');
    $file->method('getSize')->willReturn(strlen($pdf));

    $originalMemoryLimit = \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_MEMORY);
    $originalTimeLimit = \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_TIME);
    try {
      $uri = (new PdfThumbnailGenerator($configFactory, $fileSystem))->generate($file);

      self::assertSame('public://pdfpreview/' . md5('pdfpreview42') . '.jpg', $uri);
      $thumbnailPath = $directory . '/' . basename((string) $uri);
      $imageInfo = getimagesize($thumbnailPath);
      self::assertIsArray($imageInfo);
      self::assertSame(IMAGETYPE_JPEG, $imageInfo[2]);
      self::assertLessThanOrEqual(480, $imageInfo[0]);
      self::assertLessThanOrEqual(640, $imageInfo[1]);
      self::assertEqualsWithDelta(1.5, $imageInfo[0] / $imageInfo[1], 0.02);
      self::assertSame($originalMemoryLimit, \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_MEMORY));
      self::assertSame($originalTimeLimit, \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_TIME));
      self::assertSame(['.', '..', basename((string) $uri), 'source.pdf'], scandir($directory));
    }
    finally {
      foreach (glob($directory . '/*') ?: [] as $path) {
        unlink($path);
      }
      rmdir($directory);
    }
  }

  /**
   * Returns a small synthetic one-page PDF fixture.
   */
  private function minimalPdf(): string {
    $objects = [
      "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
      "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
      "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n",
      "4 0 obj\n<< /Length 39 >>\nstream\nBT /F1 18 Tf 24 120 Td (ACULTA test) Tj ET\nendstream\nendobj\n",
      "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
      $offsets[] = strlen($pdf);
      $pdf .= $object;
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
      $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    return $pdf . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";
  }

}
