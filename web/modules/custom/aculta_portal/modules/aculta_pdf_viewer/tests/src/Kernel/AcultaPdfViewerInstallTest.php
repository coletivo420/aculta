<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Kernel;

use Drupal\aculta_pdf_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\aculta_pdf_viewer\Pdf\PdfPreviewThumbnailBuilderInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies extension discovery, plugin discovery, and service registration.
 */
#[Group('aculta_pdf_viewer')]
#[RunTestsInSeparateProcesses]
final class AcultaPdfViewerInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'block',
    'comment',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_store',
    'ctools',
    'ctools_views',
    'datetime',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'entity',
    'entity_reference_revisions',
    'entity_usage',
    'field',
    'file',
    'filter',
    'flexible_permissions',
    'group',
    'image',
    'inline_entity_form',
    'key',
    'language',
    'link',
    'lms',
    'login_emailusername',
    'media',
    'media_duplicates',
    'metatag',
    'text',
    'node',
    'options',
    'path',
    'path_alias',
    'physical',
    'profile',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_metatag',
    'schema_organization',
    'schema_product',
    'schema_web_page',
    'schema_web_site',
    'search_api',
    'search_api_db',
    'social_api',
    'social_auth',
    'state_machine',
    'system',
    'taxonomy',
    'token',
    'user',
    'views',
    'vvj_core',
    'vvjs',
    'aculta_image',
    'aculta_mail_confirmer',
    'aculta_mail_confirmer_user',
    'aculta_mailer',
    'aculta_portal',
    'aculta_portal_cep',
    'aculta_user',
  ];

  /**
   * Tests service and formatter registration without module-owned storage.
   */
  public function testInstallWorksWithoutPdfPreviewAndRegistersFormatter(): void {
    $this->installConfig(['language']);
    $this->container->get('config.factory')->getEditable('system.menu.admin')->setData([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'admin',
      'label' => 'Administration',
      'description' => 'Administrative task links',
      'locked' => TRUE,
    ])->save();
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_pdf_viewer'], TRUE));
    $container = \Drupal::getContainer();
    $module_handler = $container->get('module_handler');

    $this->assertTrue($module_handler->moduleExists('aculta_pdf_viewer'));
    $this->assertFalse($module_handler->moduleExists('pdfpreview'));
    $this->assertInstanceOf(
      PdfViewerBuilderInterface::class,
      $container->get('aculta_pdf_viewer.builder'),
    );
    $this->assertInstanceOf(
      PdfPreviewThumbnailBuilderInterface::class,
      $container->get('aculta_pdf_viewer.thumbnail_builder'),
    );
    $this->assertArrayHasKey(
      'aculta_pdf_media',
      $container->get('plugin.manager.field.formatter')->getDefinitions(),
    );
    $this->assertTrue($container->get('config.storage')->exists('aculta_pdf_viewer.settings'));
    $this->assertFalse($module_handler->hasImplementations('file_download', ['aculta_pdf_viewer']));
    $this->assertFalse($module_handler->hasImplementations('install', ['aculta_pdf_viewer']));
  }

}
