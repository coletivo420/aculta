<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Kernel;

use Drupal\aculta_document_viewer\Docx\DocxViewerBuilderInterface;
use Drupal\aculta_document_viewer\Form\DocumentViewerSettingsForm;
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
final class AcultaDocumentViewerInstallTest extends KernelTestBase {

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
    $this->assertFalse($module_handler->moduleExists('pdfpreview'));
    $this->assertInstanceOf(
      PdfViewerBuilderInterface::class,
      $container->get('aculta_document_viewer.pdf_builder'),
    );
    $this->assertInstanceOf(
      PdfPreviewThumbnailBuilderInterface::class,
      $container->get('aculta_document_viewer.pdf_thumbnail_builder'),
    );
    $this->assertInstanceOf(
      DocxViewerBuilderInterface::class,
      $container->get('aculta_document_viewer.doc_builder'),
    );
    $this->assertInstanceOf(
      SpreadsheetViewerBuilderInterface::class,
      $container->get('aculta_document_viewer.sheet_builder'),
    );
    $definitions = $container->get('plugin.manager.field.formatter')->getDefinitions();
    $this->assertArrayHasKey('aculta_document_viewer_pdf', $definitions);
    $this->assertArrayHasKey('aculta_document_viewer_pdf_file', $definitions);
    $this->assertArrayHasKey('aculta_document_viewer_doc', $definitions);
    $this->assertArrayHasKey('aculta_document_viewer_sheet', $definitions);
    $this->assertArrayHasKey('aculta_document_viewer_media', $definitions);

    $settings = $container->get('config.factory')->get('aculta_document_viewer.settings');
    $this->assertTrue($settings->get('show_download'));
    $this->assertSame('fit-width', $settings->get('default_zoom'));
    $this->assertTrue($settings->get('docx_render_headers'));
    $this->assertFalse($settings->get('docx_respect_page_breaks'));
    $this->assertSame('sheet', $settings->get('spreadsheet_default_mode'));
    $this->assertSame(50, $settings->get('spreadsheet_page_size'));

    $route_provider = $container->get('router.route_provider');
    foreach ([
      'aculta_document_viewer.settings' => [
        '/painel-administrativo/aculta/leitor-documentos',
        'aculta_document_viewer_pdf',
      ],
      'aculta_document_viewer.settings_doc' => [
        '/painel-administrativo/aculta/leitor-documentos/documentos',
        'aculta_document_viewer_doc',
      ],
      'aculta_document_viewer.settings_sheet' => [
        '/painel-administrativo/aculta/leitor-documentos/planilhas',
        'aculta_document_viewer_sheet',
      ],
    ] as $route_name => [$expected_path, $expected_section]) {
      $route = $route_provider->getRouteByName($route_name);
      $this->assertSame($expected_path, $route->getPath());
      $this->assertSame('administer site configuration', $route->getRequirement('_permission'));
      $this->assertSame($expected_section, $route->getDefault('section'));
    }

    $form_builder = $container->get('form_builder');
    $pdf_form = $form_builder->getForm(DocumentViewerSettingsForm::class, 'aculta_document_viewer_pdf');
    $this->assertArrayHasKey('aculta_document_viewer_pdf', $pdf_form);
    $this->assertArrayNotHasKey('aculta_document_viewer_doc', $pdf_form);
    $this->assertArrayNotHasKey('aculta_document_viewer_sheet', $pdf_form);
    $this->assertSame('aculta_document_viewer.settings:show_download', $pdf_form['aculta_document_viewer_pdf']['controls']['show_download']['#config_target']);

    $doc_form = $form_builder->getForm(DocumentViewerSettingsForm::class, 'aculta_document_viewer_doc');
    $this->assertArrayHasKey('aculta_document_viewer_doc', $doc_form);
    $this->assertArrayNotHasKey('aculta_document_viewer_pdf', $doc_form);
    $this->assertArrayNotHasKey('aculta_document_viewer_sheet', $doc_form);
    $this->assertSame('aculta_document_viewer.settings:docx_render_headers', $doc_form['aculta_document_viewer_doc']['docx_render_headers']['#config_target']);
    $this->assertSame('aculta_document_viewer.settings:docx_respect_page_breaks', $doc_form['aculta_document_viewer_doc']['docx_respect_page_breaks']['#config_target']);

    $sheet_form = $form_builder->getForm(DocumentViewerSettingsForm::class, 'aculta_document_viewer_sheet');
    $this->assertArrayHasKey('aculta_document_viewer_sheet', $sheet_form);
    $this->assertArrayNotHasKey('aculta_document_viewer_pdf', $sheet_form);
    $this->assertArrayNotHasKey('aculta_document_viewer_doc', $sheet_form);
    $this->assertSame('aculta_document_viewer.settings:spreadsheet_default_mode', $sheet_form['aculta_document_viewer_sheet']['spreadsheet_default_mode']['#config_target']);
    $this->assertSame('aculta_document_viewer.settings:spreadsheet_page_size', $sheet_form['aculta_document_viewer_sheet']['spreadsheet_page_size']['#config_target']);
    $this->assertSame([25 => 25, 50 => 50, 100 => 100], $sheet_form['aculta_document_viewer_sheet']['spreadsheet_page_size']['#options']);

    $this->assertFalse($module_handler->hasImplementations('file_download', ['aculta_document_viewer']));
    $this->assertFalse($module_handler->hasImplementations('install', ['aculta_document_viewer']));
  }

}
