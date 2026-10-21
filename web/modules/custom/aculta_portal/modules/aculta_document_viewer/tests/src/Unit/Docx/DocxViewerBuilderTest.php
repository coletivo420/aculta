<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Docx;

use Drupal\aculta_document_viewer\Docx\DocxPackageValidator;
use Drupal\aculta_document_viewer\Docx\DocxViewerBuilder;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Regras de recusa do builder do leitor DOCX (sem container Drupal).
 */
#[CoversClass(DocxViewerBuilder::class)]
#[Group('aculta_document_viewer')]
final class DocxViewerBuilderTest extends UnitTestCase {

  /**
   * Diretório temporário do teste.
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/aculta-docx-builder-' . bin2hex(random_bytes(4));
    mkdir($this->dir);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    array_map('unlink', glob($this->dir . '/*') ?: []);
    rmdir($this->dir);
    parent::tearDown();
  }

  /**
   * Cria o builder com o validador real e um realpath apontando para $path.
   */
  private function builder(string $path): DocxViewerBuilder {
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($path);
    return new DocxViewerBuilder(
      $this->createMock(FileUrlGeneratorInterface::class),
      $fileSystem,
      new DocxPackageValidator(),
      $this->createMock(ModuleExtensionList::class),
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Cria um arquivo com o URI indicado.
   */
  private function file(string $uri): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn($uri);
    $file->method('id')->willReturn(1);
    return $file;
  }

  /**
   * URI remoto nunca recebe viewer, mesmo que o conteúdo fosse um DOCX.
   */
  public function testRemoteSchemeIsRefused(): void {
    $path = $this->dir . '/valido.docx';
    file_put_contents($path, 'PK');
    $this->assertNull($this->builder($path)->build($this->file('https://exemplo.invalid/a.docx'), 'Título'));
  }

  /**
   * Arquivo que não passa na validação estrutural não recebe viewer.
   */
  public function testInvalidPackageIsRefused(): void {
    $path = $this->dir . '/falso.docx';
    file_put_contents($path, 'isto não é um zip');
    $this->assertNull($this->builder($path)->build($this->file('public://falso.docx'), 'Título'));
  }

  /**
   * Caminho que o Core não resolve não recebe viewer.
   */
  public function testUnresolvedPathIsRefused(): void {
    $this->assertNull($this->builder($this->dir . '/nao-existe.docx')->build($this->file('private://x.docx'), 'Título'));
  }

}
