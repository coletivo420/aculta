<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_file_uploader\Unit;

use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_file_uploader\ImageDimensionReader;
use Drupal\aculta_file_uploader\MimeTypeVerifier;
use Drupal\aculta_file_uploader\UploadPolicy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cobre a política por integração: herança, limites, documentos e conteúdo.
 */
#[Group('aculta_file_uploader')]
final class ConsumerUploadPolicyTest extends UnitTestCase {

  /**
   * PNG válido de 1×1 pixel.
   */
  private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

  /**
   * Cria a política com valores de configuração e um caminho físico fixo.
   *
   * @param array<string, mixed> $values
   *   Valores de aculta_file_uploader.settings, com chaves pontilhadas.
   * @param string|null $realpath
   *   Caminho físico devolvido pelo sistema de arquivos.
   *
   * @return \Drupal\aculta_file_uploader\ConsumerUploadPolicy
   *   Política de upload configurada para o teste.
   */
  private function policy(array $values, ?string $realpath = NULL): ConsumerUploadPolicy {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn (string $key) => $values[$key] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_file_uploader.settings')->willReturn($config);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($realpath);
    $dimensionReader = $this->createMock(ImageDimensionReader::class);
    $dimensionReader->method('dimensions')->willReturn(['width' => 1, 'height' => 1]);
    return new ConsumerUploadPolicy($factory, new UploadPolicy(), new MimeTypeVerifier(), $fileSystem, $dimensionReader);
  }

  /**
   * Cria um File de teste com nome, tamanho e URI definidos.
   */
  private function file(string $name, int $size): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn($name);
    $file->method('getSize')->willReturn($size);
    $file->method('getFileUri')->willReturn('public://' . $name);
    return $file;
  }

  /**
   * Escreve bytes em um arquivo temporário e devolve o caminho.
   */
  private function tempFile(string $bytes): string {
    $path = tempnam(sys_get_temp_dir(), 'aculta_upload_');
    self::assertIsString($path);
    file_put_contents($path, $bytes);
    return $path;
  }

  /**
   * Mantém a ordem do catálogo e ignora entradas fora dele.
   */
  public function testGlobalExtensionsKeepCatalogOrderAndIgnoreUnknownEntries(): void {
    $policy = $this->policy(['allowed_extensions' => 'pdf jpg png docx']);
    self::assertSame(['png', 'jpg'], $policy->imageExtensions());
  }

  /**
   * Integração restringe a política, mas não amplia o catálogo central.
   */
  public function testConsumerOverrideNarrowsButNeverWidens(): void {
    $policy = $this->policy([
      'allowed_extensions' => 'png gif jpg jpeg webp',
      'max_size_mb' => 10,
      'consumers.wiki' => ['inherit_global' => FALSE, 'allowed_extensions' => 'png webp', 'max_size_mb' => 50],
    ]);
    self::assertSame(['png', 'webp'], $policy->imageExtensions('wiki'));
    self::assertSame(UploadPolicy::MAX_BYTES, $policy->maxBytes('wiki'));
  }

  /**
   * Integração que herda a global ignora os próprios valores.
   */
  public function testInheritGlobalIgnoresConsumerValues(): void {
    $policy = $this->policy([
      'allowed_extensions' => 'gif',
      'max_size_mb' => 2,
      'consumers.forum' => ['inherit_global' => TRUE, 'allowed_extensions' => 'png', 'max_size_mb' => 1],
    ]);
    self::assertSame(['gif'], $policy->imageExtensions('forum'));
    self::assertSame(2 * 1024 * 1024, $policy->maxBytes('forum'));
  }

  /**
   * Lista sem nenhuma extensão de imagem fecha a política de imagens.
   */
  public function testEmptyImageCatalogFailsClosed(): void {
    $policy = $this->policy(['allowed_extensions' => 'pdf docx']);
    self::assertSame([], $policy->imageExtensions());
    self::assertSame([], $policy->imageValidators());
  }

  /**
   * Arquivo com extensão de PNG mas conteúdo de texto é recusado.
   */
  public function testRejectsContentThatDoesNotMatchExtension(): void {
    $path = $this->tempFile('isto não é uma imagem');
    try {
      $policy = $this->policy(['allowed_extensions' => 'png'], $path);
      self::assertFalse($policy->isValidImage($this->file('foto.png', (int) filesize($path))));
    }
    finally {
      @unlink($path);
    }
  }

  /**
   * PNG com conteúdo compatível, dentro do limite, é aceito.
   */
  public function testAcceptsMatchingImageWithinLimit(): void {
    $path = $this->tempFile(base64_decode(self::PNG_1X1));
    try {
      $policy = $this->policy(['allowed_extensions' => 'png', 'max_size_mb' => 10], $path);
      self::assertTrue($policy->isValidImage($this->file('foto.png', (int) filesize($path))));
    }
    finally {
      @unlink($path);
    }
  }

  /**
   * Arquivo acima do limite é recusado antes de ler o conteúdo.
   */
  public function testRejectsFileAboveLimit(): void {
    $policy = $this->policy(['allowed_extensions' => 'png', 'max_size_mb' => 1], '/nao/usado');
    self::assertFalse($policy->isValidImage($this->file('grande.png', 1024 * 1024 + 1)));
  }

  /**
   * Imagens e documentos têm catálogos separados.
   */
  public function testDocumentsAreSeparateFromImages(): void {
    $policy = $this->policy(['allowed_extensions' => 'png pdf docx']);
    self::assertSame(['png'], $policy->imageExtensions());
    self::assertSame(['pdf', 'docx'], $policy->documentExtensions());
  }

  /**
   * PDF com conteúdo compatível é aceito como documento.
   */
  public function testAcceptsPdfContentAsDocument(): void {
    $path = $this->tempFile("%PDF-1.4\n1 0 obj\n<< >>\nendobj\n%%EOF\n");
    try {
      $policy = $this->policy(['allowed_extensions' => 'pdf'], $path);
      self::assertTrue($policy->isValidDocument($this->file('contrato.pdf', (int) filesize($path))));
    }
    finally {
      @unlink($path);
    }
  }

  /**
   * Validadores da atividade são estreitados e o limite nunca sobe.
   */
  public function testRestrictValidatorsNarrowsExtensionsAndCapsSize(): void {
    $policy = $this->policy(['allowed_extensions' => 'png pdf', 'max_size_mb' => 2]);
    $validators = $policy->restrictValidators([
      'FileExtension' => ['extensions' => 'png gif pdf docx'],
      'FileSizeLimit' => ['fileLimit' => 50 * 1024 * 1024],
      'Extra' => [],
    ], 'lms');
    self::assertSame('png pdf', $policy->acceptedExtensions($validators));
    self::assertSame(2 * 1024 * 1024, $validators['FileSizeLimit']['fileLimit']);
    self::assertArrayHasKey('Extra', $validators);
  }

}
