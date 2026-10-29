<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Spreadsheet;

use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetFileValidator;
use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetViewerBuilder;
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
 * Tests spreadsheet viewer render array construction and refusal rules.
 */
#[CoversClass(SpreadsheetViewerBuilder::class)]
#[Group('aculta_document_viewer')]
final class SpreadsheetViewerBuilderTest extends UnitTestCase {

  /**
   * Temporary directory for test fixtures.
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/aculta-sheet-builder-' . bin2hex(random_bytes(4));
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
   * Creates the spreadsheet builder with real validation and injected config.
   *
   * @param string $path
   *   Resolved file path on disk.
   * @param array<string, mixed> $settings
   *   Values returned by aculta_document_viewer.settings.
   */
  private function builder(string $path, array $settings = []): SpreadsheetViewerBuilder {
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
    $fileUrlGenerator->method('generateString')->willReturn('/sites/default/files/planilha.csv');
    $moduleList = $this->createMock(ModuleExtensionList::class);
    $moduleList->method('getPath')->with('aculta_document_viewer')->willReturn('modules/custom/aculta_document_viewer');
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      static fn(string $key): mixed => $settings[$key] ?? NULL,
    );
    $config->method('getCacheTags')->willReturn(['config:aculta_document_viewer.settings']);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_document_viewer.settings')->willReturn($config);
    return new SpreadsheetViewerBuilder(
      $fileUrlGenerator,
      $this->createMock(UrlGeneratorInterface::class),
      $fileSystem,
      $moduleList,
      new SpreadsheetFileValidator(),
      $this->createMock(LoggerInterface::class),
      $configFactory,
    );
  }

  /**
   * Creates a mock file entity.
   */
  private function file(string $uri, string $filename = 'planilha.csv'): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn($uri);
    $file->method('getFilename')->willReturn($filename);
    $file->method('id')->willReturn(1);
    return $file;
  }

  /**
   * Remote URIs are refused before reading disk.
   */
  public function testRemoteSchemeIsRefused(): void {
    $path = $this->dir . '/planilha.csv';
    file_put_contents($path, "coluna_a,coluna_b\n1,2\n");
    $this->assertNull($this->builder($path)->build($this->file('https://exemplo.invalid/planilha.csv'), 'Planilha'));
  }

  /**
   * Propagates configured default mode, page size, and fallback page size.
   */
  public function testBuildPropagatesModeAndValidatedPageSize(): void {
    $path = $this->dir . '/planilha.csv';
    file_put_contents($path, "nome,valor\nitem,10\n");

    $tableRender = $this->builder($path, [
      'spreadsheet_default_mode' => 'table',
      'spreadsheet_page_size' => 25,
    ])->build($this->file('public://planilha.csv'), 'Orçamento');

    $this->assertNotNull($tableRender);
    $this->assertSame('table', $tableRender['#attributes']['data-aculta-sheet-default-mode']);
    $this->assertSame('25', $tableRender['#attributes']['data-aculta-sheet-page-size']);
    $this->assertSame(['config:aculta_document_viewer.settings'], $tableRender['#cache']['tags']);

    $fallbackRender = $this->builder($path, [
      'spreadsheet_default_mode' => 'invalid',
      'spreadsheet_page_size' => 15,
    ])->build($this->file('public://planilha.csv'), 'Orçamento');

    $this->assertNotNull($fallbackRender);
    $this->assertSame('sheet', $fallbackRender['#attributes']['data-aculta-sheet-default-mode']);
    $this->assertSame('50', $fallbackRender['#attributes']['data-aculta-sheet-page-size']);
  }

}
