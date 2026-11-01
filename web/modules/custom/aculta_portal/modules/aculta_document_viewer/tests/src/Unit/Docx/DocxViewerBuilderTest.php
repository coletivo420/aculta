<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Docx;

use Drupal\aculta_document_viewer\Docx\DocxPackageValidator;
use Drupal\aculta_document_viewer\Docx\DocxViewerBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
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
   *
   * @param string $path
   *   Caminho absoluto resolvido para o arquivo.
   * @param array<string, mixed> $settings
   *   Valores retornados por aculta_document_viewer.settings.
   */
  private function builder(string $path, array $settings = []): DocxViewerBuilder {
    $container = new ContainerBuilder();
    $assembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    $assembler->method('assemble')->willReturnCallback(
      static fn(string $uri): string => '/' . substr($uri, strlen('base:')),
    );
    $container->set('unrouted_url_assembler', $assembler);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($path);
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $fileUrlGenerator->method('generateString')->willReturn('/sites/default/files/valido.docx');
    $moduleList = $this->createMock(ModuleExtensionList::class);
    $moduleList->method('getPath')->with('aculta_document_viewer')->willReturn('modules/custom/aculta_document_viewer');
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      static fn(string $key): mixed => $settings[$key] ?? NULL,
    );
    $config->method('getCacheTags')->willReturn(['config:aculta_document_viewer.settings']);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('aculta_document_viewer.settings')->willReturn($config);
    return new DocxViewerBuilder(
      $fileUrlGenerator,
      $this->createMock(UrlGeneratorInterface::class),
      $fileSystem,
      new DocxPackageValidator(),
      $moduleList,
      $this->createMock(LoggerInterface::class),
      $config_factory,
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

  /**
   * Propagates configured DOCX rendering flags and settings cache tags.
   */
  public function testValidDocxPropagatesConfiguredRenderOptions(): void {
    $path = $this->dir . '/valido.docx';
    $zip = new \ZipArchive();
    $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<Types><Override PartName="/word/document.xml" ContentType="' . DocxPackageValidator::MAIN_CONTENT_TYPE . '"/></Types>');
    $zip->addFromString('word/document.xml', '<w:document/>');
    $zip->close();

    $render = $this->builder($path, [
      'docx_render_headers' => FALSE,
      'docx_render_footers' => TRUE,
      'docx_render_footnotes' => FALSE,
      'docx_render_endnotes' => TRUE,
      'docx_respect_page_breaks' => TRUE,
    ])->build($this->file('public://valido.docx'), 'Ata da reunião');

    $this->assertNotNull($render);
    $this->assertSame(['config:aculta_document_viewer.settings'], $render['#cache']['tags']);
    $options = json_decode((string) $render['#attributes']['data-aculta-docx-render-options'], TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame([
      'renderHeaders' => FALSE,
      'renderFooters' => TRUE,
      'renderFootnotes' => FALSE,
      'renderEndnotes' => TRUE,
      'respectPageBreaks' => TRUE,
    ], $options);
  }

}
