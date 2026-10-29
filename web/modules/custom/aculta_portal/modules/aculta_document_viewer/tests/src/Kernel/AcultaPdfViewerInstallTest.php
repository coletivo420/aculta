<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Kernel;

use Drupal\aculta_document_viewer\Docx\DocxViewerBuilderInterface;
use Drupal\aculta_document_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\aculta_document_viewer\Pdf\PdfPreviewThumbnailBuilderInterface;
use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetViewerBuilderInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once __DIR__ . '/DocumentViewerKernelClosureTrait.php';

/**
 * Verifies extension discovery, plugin discovery, and service registration.
 */
#[Group('aculta_document_viewer')]
#[RunTestsInSeparateProcesses]
final class AcultaPdfViewerInstallTest extends KernelTestBase {

  use DocumentViewerKernelClosureTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = self::DOCUMENT_VIEWER_KERNEL_CLOSURE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Language's modules_installed hook reads its negotiation config.
    $this->installConfig(['language']);
  }

  /**
   * Tests service and formatter registration without module-owned storage.
   */
  public function testInstallWorksWithoutPdfPreviewAndRegistersFormatter(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_document_viewer'], TRUE));
    $container = \Drupal::getContainer();
    $module_handler = $container->get('module_handler');

    $this->assertTrue($module_handler->moduleExists('aculta_document_viewer'));
    $this->assertTrue($module_handler->moduleExists('aculta_pdf_viewer'));
    $this->assertFalse($module_handler->moduleExists('pdfpreview'));
    $this->assertInstanceOf(
      PdfViewerBuilderInterface::class,
      $container->get('aculta_document_viewer.builder'),
    );
    $this->assertInstanceOf(
      PdfPreviewThumbnailBuilderInterface::class,
      $container->get('aculta_document_viewer.thumbnail_builder'),
    );
    $this->assertInstanceOf(
      DocxViewerBuilderInterface::class,
      $container->get('aculta_document_viewer.docx_builder'),
    );
    $this->assertInstanceOf(
      SpreadsheetViewerBuilderInterface::class,
      $container->get('aculta_document_viewer.spreadsheet_builder'),
    );
    $this->assertArrayHasKey(
      'aculta_document_pdf_media',
      $container->get('plugin.manager.field.formatter')->getDefinitions(),
    );
    $this->assertArrayHasKey(
      'aculta_document_media',
      $container->get('plugin.manager.field.formatter')->getDefinitions(),
    );
    $settings = $container->get('config.factory')->get('aculta_document_viewer.settings');
    $this->assertTrue($settings->get('show_download'));
    $this->assertSame('fit-width', $settings->get('default_zoom'));
    $this->assertTrue($settings->get('docx_render_headers'));
    $this->assertFalse($settings->get('docx_respect_page_breaks'));
    $this->assertSame('sheet', $settings->get('spreadsheet_default_mode'));
    $this->assertSame(50, $settings->get('spreadsheet_page_size'));
    $route = $container->get('router.route_provider')->getRouteByName('aculta_document_viewer.settings');
    $this->assertSame('administer site configuration', $route->getRequirement('_permission'));
    $this->assertFalse($module_handler->hasImplementations('file_download', ['aculta_document_viewer']));
    $this->assertFalse($module_handler->hasImplementations('install', ['aculta_document_viewer']));
  }

}
