<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileExists;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\DrupalKernel;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterPluginCollection;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

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
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\\Core\\StreamWrapper\\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

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
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'user', 'media', 'filter', 'image', 'node']);
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_pdf_viewer'], TRUE));

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

    $course_media_type = MediaType::create([
      'id' => 'course_document',
      'label' => 'Course document',
      'source' => 'file',
      'source_configuration' => ['source_field' => 'field_media_course_document'],
    ]);
    $course_media_type->getSource()->setConfiguration(['source_field' => 'field_media_course_document']);
    $course_media_type->save();
    $course_field_storage = FieldStorageConfig::create([
      'field_name' => 'field_media_course_document',
      'entity_type' => 'media',
      'type' => 'file',
      'cardinality' => 1,
      'settings' => ['uri_scheme' => 'private'],
    ]);
    $course_field_storage->save();
    FieldConfig::create([
      'field_storage' => $course_field_storage,
      'bundle' => 'course_document',
      'label' => 'Course PDF',
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
    EntityViewDisplay::create([
      'targetEntityType' => 'media',
      'bundle' => 'course_document',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_media_course_document', [
      'type' => 'aculta_pdf_media',
      'label' => 'visually_hidden',
      'settings' => [],
      'third_party_settings' => [],
      'weight' => 0,
      'region' => 'content',
    ])->save();
  }

  /**
   * Tests the shared formatter on an institutional document's File field.
   */
  public function testInstitutionalDocumentFileFieldUsesSharedPdfViewer(): void {
    $node_type = \Drupal\node\Entity\NodeType::create([
      'type' => 'document',
      'name' => 'Institutional document',
    ]);
    $node_type->save();
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_document',
      'entity_type' => 'node',
      'type' => 'file',
      'cardinality' => 1,
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'document',
      'label' => 'PDF',
      'settings' => ['file_extensions' => 'pdf'],
    ])->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'document',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_document', [
      'type' => 'aculta_pdf_file',
      'label' => 'hidden',
      'settings' => [],
      'third_party_settings' => [],
      'weight' => 0,
      'region' => 'content',
    ])->save();

    $file_uri = $this->container->get('file_system')->saveData(
      "%PDF-1.4\n% ACULTA institutional PDF Kernel fixture\n",
      'public://aculta-pdf-institutional-fixture.pdf',
      FileExists::Replace,
    );
    $file = File::create([
      'uri' => $file_uri,
      'filename' => 'aculta-pdf-institutional-fixture.pdf',
      'filemime' => 'application/pdf',
      'uid' => $this->container->get('current_user')->id(),
    ]);
    $file->setPermanent();
    $file->save();

    $node = \Drupal\node\Entity\Node::create([
      'type' => 'document',
      'title' => 'Institutional PDF fixture',
      'status' => TRUE,
      'field_document' => [['target_id' => $file->id(), 'description' => 'Open the PDF']],
    ]);
    $node->save();
    $render = $this->container->get('entity_type.manager')->getViewBuilder('node')->view($node);
    $context = new RenderContext();
    $markup = $this->container->get('renderer')->executeInRenderContext($context, fn () => $this->container->get('renderer')->renderRoot($render));

    $this->assertStringContainsString('data-aculta-pdf-viewer="true"', (string) $markup);
    $this->assertStringContainsString('aculta-pdf-institutional-fixture.pdf', (string) $markup);
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
    $filter_collection = new FilterPluginCollection($this->container->get('plugin.manager.filter'), [
      'media_embed' => [
        'id' => 'media_embed',
        'provider' => 'media',
        'status' => TRUE,
        'weight' => 20,
        'settings' => [
          'default_view_mode' => 'default',
          'allowed_view_modes' => [],
          'allowed_media_types' => ['document' => 'document'],
        ],
      ],
    ]);
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

  /**
   * Tests that a Wiki text format renders a PDF through the shared formatter.
   */
  public function testWikiFormatUsesSharedPdfFormatter(): void {
    $file_uri = $this->container->get('file_system')->saveData(
      "%PDF-1.4\n% ACULTA Wiki PDF Kernel fixture\n",
      'public://aculta-pdf-wiki-fixture.pdf',
      FileExists::Replace,
    );
    $user = $this->container->get('current_user')->getAccount();
    $file = File::create([
      'uri' => $file_uri,
      'filename' => 'aculta-pdf-wiki-fixture.pdf',
      'filemime' => 'application/pdf',
      'uid' => $user->id(),
    ]);
    $file->setPermanent();
    $file->save();

    $media = Media::create([
      'bundle' => 'document',
      'name' => 'Wiki PDF Kernel fixture',
      'status' => TRUE,
      'field_media_document' => [['target_id' => $file->id()]],
    ]);
    $media->setOwner($user);
    $media->save();

    $format = FilterFormat::create([
      'format' => 'wiki_pdf_test',
      'name' => 'Wiki PDF test',
      'filters' => [
        'filter_html' => [
          'id' => 'filter_html',
          'provider' => 'filter',
          'status' => TRUE,
          'weight' => -10,
          'settings' => [
            'allowed_html' => '<drupal-media data-entity-type data-entity-uuid data-view-mode data-align data-caption alt>',
            'filter_html_help' => FALSE,
            'filter_html_nofollow' => FALSE,
          ],
        ],
        'media_embed' => [
          'id' => 'media_embed',
          'provider' => 'media',
          'status' => TRUE,
          'weight' => 40,
          'settings' => [
            'default_view_mode' => 'default',
            'allowed_view_modes' => [],
            'allowed_media_types' => ['document' => 'document'],
          ],
        ],
      ],
    ]);
    $format->save();

    $text = sprintf(
      '<drupal-media data-entity-type="media" data-entity-uuid="%s"></drupal-media>',
      $media->uuid(),
    );
    $build = [
      '#type' => 'processed_text',
      '#text' => $text,
      '#format' => 'wiki_pdf_test',
      '#langcode' => 'pt-br',
    ];
    $processed = $this->container->get('renderer')->renderRoot($build);

    self::assertStringNotContainsString('<drupal-media', (string) $processed);
    self::assertStringContainsString('data-aculta-pdf-viewer="true"', (string) $processed);
    self::assertStringContainsString('aculta-pdf-wiki-fixture.pdf', (string) $processed);
  }

  /**
   * Verifies private PDF requests are rechecked by Drupal Core.
   */
  public function testPrivatePdfUsesCoreRouteAndRespectsMediaAccess(): void {
    $site_path = DrupalKernel::findSitePath(Request::create('/'));
    $private_directory = $site_path . '/private';
    $fixture_directory = $private_directory . '/pdf-viewer';
    mkdir($fixture_directory, 0775, TRUE);
    $this->setSetting('file_private_path', $private_directory);
    $private_path = $fixture_directory . '/aculta-private-fixture.pdf';
    file_put_contents($private_path, "%PDF-1.4\n% private test fixture\n");

    $user = $this->container->get('current_user')->getAccount();
    $file = File::create([
      'uri' => 'private://pdf-viewer/aculta-private-fixture.pdf',
      'filename' => 'aculta-private-fixture.pdf',
      'filemime' => 'application/pdf',
      'uid' => $user->id(),
    ]);
    $file->setPermanent();
    $file->save();
    $media = Media::create([
      'bundle' => 'course_document',
      'name' => 'PDF privado Kernel fixture',
      'status' => TRUE,
      'field_media_course_document' => [['target_id' => $file->id()]],
    ]);
    $media->setOwner($user);
    $media->save();

    $build = $this->container->get('aculta_pdf_viewer.builder')->build($media, 'field_media_course_document');
    $private_url = $build['#attributes']['data-aculta-pdf-url'];
    $this->assertSame('/system/files?file=pdf-viewer/aculta-private-fixture.pdf', $private_url);
    $this->assertSame(0, $build['#cache']['max-age']);
    $this->assertArrayNotHasKey('thumbnail', $build);

    $kernel = $this->container->get('http_kernel');
    $authorized_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(200, $authorized_response->getStatusCode());
    $this->assertStringContainsString('private', strtolower($authorized_response->headers->get('Cache-Control') ?? ''));

    $this->container->get('current_user')->setAccount($this->createUser());
    $unauthorized_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(403, $unauthorized_response->getStatusCode());

    $this->container->get('current_user')->setAccount(new \Drupal\Core\Session\AnonymousUserSession());
    $denied_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(403, $denied_response->getStatusCode());

    // Private storage is still governed by Media access. In the production
    // Config Sync the anonymous role has 'view media', which grants access to
    // published Media and therefore to its referenced private file.
    $anonymous_role = Role::load('anonymous') ?? Role::create([
      'id' => 'anonymous',
      'label' => 'Anonymous',
      'weight' => 0,
      'is_admin' => FALSE,
      'permissions' => [],
    ]);
    $anonymous_role->grantPermission('view media')->save();
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getAccessControlHandler('media')->resetCache();
    $entity_type_manager->getAccessControlHandler('file')->resetCache();
    $anonymous_with_media_access = new \Drupal\Core\Session\AnonymousUserSession();
    $this->container->get('current_user')->setAccount($anonymous_with_media_access);
    $this->assertTrue($anonymous_with_media_access->hasPermission('view media'));
    $this->assertTrue($media->access('view', $anonymous_with_media_access, TRUE)->isAllowed());
    $this->assertTrue($file->access('download', $anonymous_with_media_access, TRUE)->isAllowed());
    $anonymous_allowed_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(200, $anonymous_allowed_response->getStatusCode());

    $media_viewer = $this->createUser(['view media']);
    $this->container->get('current_user')->setAccount($media_viewer);
    $published_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(200, $published_response->getStatusCode());

    $media->setUnpublished();
    $media->save();
    // Model a fresh request after the entity's access state changed.
    $entity_type_manager->getAccessControlHandler('media')->resetCache();
    $entity_type_manager->getAccessControlHandler('file')->resetCache();
    $unpublished_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(403, $unpublished_response->getStatusCode());
  }

}
