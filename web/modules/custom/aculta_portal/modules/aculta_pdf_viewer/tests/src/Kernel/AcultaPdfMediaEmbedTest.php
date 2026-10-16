<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\File\FileExists;
use Drupal\Core\Render\RenderContext;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filter\FilterPluginCollection;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the CKEditor Media Embed to shared PDF viewer rendering path.
 */
#[Group('aculta_pdf_viewer')]
#[RunTestsInSeparateProcesses]
final class AcultaPdfMediaEmbedTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'filter',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'user', 'media', 'filter', 'image']);
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_pdf_viewer'], FALSE));

    $media_type = MediaType::create([
      'id' => 'document',
      'label' => 'Document',
      'source' => 'file',
      'source_configuration' => ['source_field' => 'field_media_document'],
    ]);
    $media_type->getSource()->setConfiguration(['source_field' => 'field_media_document']);
    $media_type->save();
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_media_document',
      'entity_type' => 'media',
      'type' => 'file',
      'cardinality' => 1,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'document',
      'label' => 'Document file',
      'required' => TRUE,
      'settings' => ['file_extensions' => 'pdf'],
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('current_user')->setAccount($this->createUser(['view media']));

    EntityViewDisplay::create([
      'targetEntityType' => 'media',
      'bundle' => 'document',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_media_document', [
      'type' => 'aculta_pdf_media',
      'label' => 'visually_hidden',
      'settings' => [],
      'third_party_settings' => [],
      'weight' => 0,
      'region' => 'content',
    ])->save();
  }

  /**
   * Tests that CKEditor Media Embed invokes the shared PDF formatter.
   */
  public function testCkeditorMediaEmbedUsesSharedPdfFormatter(): void {
    $file_uri = $this->container->get('file_system')->saveData(
      "%PDF-1.4\n% ACULTA PDF viewer Kernel fixture\n",
      'public://aculta-pdf-viewer-fixture.pdf',
      FileExists::Replace,
    );
    $user = $this->container->get('current_user')->getAccount();
    $file = File::create([
      'uri' => $file_uri,
      'filename' => 'aculta-pdf-viewer-fixture.pdf',
      'filemime' => 'application/pdf',
      'uid' => $user->id(),
    ]);
    $file->setPermanent();
    $file->save();

    $media = Media::create([
      'bundle' => 'document',
      'name' => 'PDF viewer Kernel fixture',
      'status' => TRUE,
      'field_media_document' => [['target_id' => $file->id()]],
    ]);
    $media->setOwner($user);
    $media->save();

    $this->assertTrue($media->hasField('field_media_document'));
    $this->assertSame(
      'aculta_pdf_media',
      EntityViewDisplay::load('media.document.default')->getComponent('field_media_document')['type'] ?? NULL,
    );

    $markup = sprintf(
      '<drupal-media data-entity-type="media" data-entity-uuid="%s"></drupal-media>',
      $media->uuid(),
    );
    $filter_collection = new FilterPluginCollection($this->container->get('plugin.manager.filter'), []);
    $filter = $filter_collection->get('media_embed');
    $render_context = new RenderContext();
    $result = $this->container->get('renderer')->executeInRenderContext($render_context, function () use ($filter, $markup) {
      return $filter->process($markup, 'en');
    });
    if (!$render_context->isEmpty()) {
      $result = $result->merge($render_context->pop());
    }

    $processed = $result->getProcessedText();
    $this->assertStringNotContainsString('<drupal-media', $processed);
    $this->assertStringContainsString('data-aculta-pdf-viewer="true"', $processed);
    $this->assertStringContainsString('data-aculta-pdf-control="page"', $processed);
    $this->assertStringContainsString('data-aculta-pdf-control="zoom-in"', $processed);
    $this->assertStringContainsString('data-aculta-pdf-control="zoom-reset"', $processed);
    $this->assertStringContainsString('data-aculta-pdf-control="search-input"', $processed);
    $this->assertStringContainsString('data-aculta-pdf-control="fullscreen"', $processed);
    $this->assertContains('aculta_pdf_viewer/viewer', $result->getAttachments()['library'] ?? []);
    $this->assertContains('media:' . $media->id(), $result->getCacheTags());
  }

}
