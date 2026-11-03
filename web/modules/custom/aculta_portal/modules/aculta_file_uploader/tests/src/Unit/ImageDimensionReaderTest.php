<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_file_uploader\Unit;

use Drupal\aculta_file_uploader\ImageDimensionReader;
use Drupal\aculta_file_uploader\ImageDimensionReaderInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cobre os dois cenários do leitor de dimensões.
 *
 * Com aculta_image ativo delega ao inspetor; sem ele usa getimagesize().
 */
#[CoversClass(ImageDimensionReader::class)]
#[Group('aculta_file_uploader')]
final class ImageDimensionReaderTest extends UnitTestCase {

  /**
   * PNG 1x1 decodificável.
   */
  private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQImWP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

  /**
   * Com inspetor do aculta_image, a leitura é delegada.
   */
  public function testDelegatesToImageInspectorWhenPresent(): void {
    $inspector = $this->createMock(ImageDimensionReaderInterface::class);
    $inspector->expects(self::once())
      ->method('dimensions')
      ->with('private://qualquer/arquivo.png')
      ->willReturn(['width' => 5, 'height' => 6]);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->expects(self::never())->method('realpath');

    $reader = new ImageDimensionReader($fileSystem, $inspector);
    self::assertSame(['width' => 5, 'height' => 6], $reader->dimensions('private://qualquer/arquivo.png'));
  }

  /**
   * Sem aculta_image, um PNG válido é lido pelo getimagesize().
   */
  public function testReadsValidPngWithoutInspector(): void {
    $path = $this->tempFile((string) base64_decode(self::PNG_1X1, TRUE));
    try {
      $reader = new ImageDimensionReader($this->fileSystem($path));
      self::assertSame(['width' => 1, 'height' => 1], $reader->dimensions('private://teste/valida.png'));
    }
    finally {
      unlink($path);
    }
  }

  /**
   * Sem aculta_image, um texto renomeado para .png não tem dimensões.
   */
  public function testRejectsTextRenamedAsPngWithoutInspector(): void {
    $path = $this->tempFile('isto nao e uma imagem');
    try {
      $reader = new ImageDimensionReader($this->fileSystem($path));
      self::assertNull($reader->dimensions('private://teste/falsa.png'));
    }
    finally {
      unlink($path);
    }
  }

  /**
   * Sem inspetor, arquivo inexistente não tem dimensões.
   */
  public function testMissingFileHasNoDimensions(): void {
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn(FALSE);
    $reader = new ImageDimensionReader($fileSystem);
    self::assertNull($reader->dimensions('private://teste/ausente.png'));
  }

  /**
   * File system double que resolve qualquer URI para o caminho informado.
   */
  private function fileSystem(string $path): FileSystemInterface {
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($path);
    return $fileSystem;
  }

  /**
   * Cria um arquivo temporário com o conteúdo informado.
   */
  private function tempFile(string $contents): string {
    $path = (string) tempnam(sys_get_temp_dir(), 'aculta-dim-');
    file_put_contents($path, $contents);
    return $path;
  }

}
