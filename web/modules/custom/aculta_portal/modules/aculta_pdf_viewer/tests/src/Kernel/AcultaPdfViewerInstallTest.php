<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Verifies extension discovery, plugin discovery, and service registration. */
#[Group('aculta_pdf_viewer')]
#[RunTestsInSeparateProcesses]
final class AcultaPdfViewerInstallTest extends KernelTestBase {

  protected static $modules = ['system', 'file', 'media'];

  public function testInstallRegistersFormatterAndBuilderWithoutHooksOrStorage(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_pdf_viewer'], FALSE));
    $container = \Drupal::getContainer();
    $module_handler = $container->get('module_handler');

    $this->assertTrue($module_handler->moduleExists('aculta_pdf_viewer'));
    $this->assertInstanceOf(
      \Drupal\aculta_pdf_viewer\Pdf\PdfViewerBuilderInterface::class,
      $container->get('aculta_pdf_viewer.builder'),
    );
    $this->assertArrayHasKey(
      'aculta_pdf_media',
      $container->get('plugin.manager.field.formatter')->getDefinitions(),
    );
    $this->assertFalse($container->get('config.storage')->exists('aculta_pdf_viewer.settings'));
    $this->assertFalse($module_handler->hasImplementations('file_download', ['aculta_pdf_viewer']));
    $this->assertFalse($module_handler->hasImplementations('install', ['aculta_pdf_viewer']));
  }

}
