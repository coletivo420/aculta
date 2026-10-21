<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Upload;

use Drupal\aculta_portal\Upload\UserUploadDialogBuilder;
use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\aculta_portal\Form\UserUploadSettingsForm;
use Drupal\aculta_gallery\Hook\GalleryImagePolicyHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media_gallery\Entity\MediaGallery;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the shared user-upload policy against Drupal's real Kernel services.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class UserUploadPolicyKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'file',
    'image',
    'media',
    'media_library',
    'photoswipe',
    'media_gallery',
    'views',
    'entity_usage',
    'media_duplicates',
    'aculta_image',
    'aculta_portal_upload_test',
  ];

  /**
   * Shared upload policy under test.
   *
   * @var \Drupal\aculta_portal\Upload\UserUploadPolicy
   */
  private UserUploadPolicy $policy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'system', 'user', 'file', 'image', 'media', 'media_library', 'views',
      'field', 'text', 'photoswipe', 'media_gallery', 'entity_usage', 'media_duplicates', 'aculta_image',
      'aculta_portal_upload_test',
    ]);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('media_gallery');
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('file', ['file_usage']);
    $this->configureForumGalleryFields();
    $this->policy = new UserUploadPolicy(
      $this->container->get('config.factory'),
      $this->container->get('aculta_image.upload_policy'),
      $this->container->get('aculta_image.inspector'),
    );
  }

  /**
   * The global default is safe and consumers inherit it unless overridden. */
  public function testGlobalPolicyDefaultsAndConsumerInheritance(): void {
    self::assertSame(10 * 1024 * 1024, $this->policy->maxBytes());
    self::assertSame(['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'docx', 'xls', 'xlsx'], $this->policy->allowedExtensions());

    $this->config('aculta_portal.user_upload')
      ->set('allowed_extensions', 'png pdf docx')
      ->set('max_size_mb', 25)
      ->set('consumers.forum', ['inherit_global' => TRUE])
      ->save();

    self::assertSame(['png', 'pdf', 'docx'], $this->policy->allowedExtensions('forum'));
    self::assertSame(25 * 1024 * 1024, $this->policy->maxBytes('forum'));
    self::assertSame('png', $this->policy->imageExtensions('forum'));
    self::assertSame('pdf docx', $this->policy->documentExtensions('forum'));
  }

  /**
   * Consumer overrides and invalid configuration are resolved predictably. */
  public function testConsumerOverrideAndInvalidValues(): void {
    $this->config('aculta_portal.user_upload')
      ->set('allowed_extensions', 'png pdf')
      ->set('max_size_mb', 20)
      ->set('consumers.wiki', [
        'inherit_global' => FALSE,
        'allowed_extensions' => 'jpg xlsx',
        'max_size_mb' => 50,
      ])
      ->set('consumers.lms', [
        'inherit_global' => FALSE,
        'allowed_extensions' => 'exe',
        'max_size_mb' => 0,
      ])
      ->save();

    self::assertSame(['jpg', 'xlsx'], $this->policy->allowedExtensions('wiki'));
    self::assertSame(50 * 1024 * 1024, $this->policy->maxBytes('wiki'));
    self::assertSame(['png', 'pdf'], $this->policy->allowedExtensions('lms'));
    self::assertSame(20 * 1024 * 1024, $this->policy->maxBytes('lms'));
  }

  /**
   * Image and document controls use the configured Core validators. */
  public function testUploadDialogUsesPolicyAndDrupalManagedFileValidators(): void {
    $this->config('aculta_portal.user_upload')
      ->set('allowed_extensions', 'png pdf docx')
      ->set('max_size_mb', 35)
      ->save();
    $builder = new UserUploadDialogBuilder($this->policy, $this->container->get('string_translation'));
    $form = [];

    $builder->wrap($form, 'forum-upload-dialog', 'forum', 'forum-attachment-dialog');
    $builder->addFileControls($form, 'forum', 'private://forum/user-uploads', 'images', 'documents', TRUE);

    self::assertSame([], $form['#parents']);
    self::assertStringContainsString('data-upload-consumer="forum"', $form['#prefix']);
    self::assertStringContainsString('user-upload-dialog forum-attachment-dialog', $form['#prefix']);
    self::assertContains('editor/drupal.editor.dialog', $form['#attached']['library']);
    self::assertContains('core/drupal.dialog.ajax', $form['#attached']['library']);
    self::assertSame('private://forum/user-uploads', $form['images']['#upload_location']);
    self::assertTrue($form['images']['#multiple']);
    self::assertSame([
      'FileExtension' => ['extensions' => 'png'],
      'FileSizeLimit' => ['fileLimit' => 35 * 1024 * 1024],
      'FileIsImage' => [],
    ], $form['images']['#upload_validators']);
    self::assertSame([
      'FileExtension' => ['extensions' => 'pdf docx'],
      'FileSizeLimit' => ['fileLimit' => 35 * 1024 * 1024],
    ], $form['documents']['#upload_validators']);
  }

  /**
   * The administrative form exposes global and consumer policy tabs. */
  public function testAdministrativeSettingsFormBuildsGlobalAndForumTabs(): void {
    $form_object = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(UserUploadSettingsForm::class);
    $form = $this->container->get('form_builder')->getForm($form_object);

    self::assertArrayHasKey('policy_tabs', $form);
    self::assertSame('vertical_tabs', $form['policy_tabs']['#type']);
    self::assertSame('Política global', (string) $form['global']['#title']);
    self::assertSame('Fórum', (string) $form['forum']['#title']);
    self::assertSame(10, $form['global']['max_size_mb']['#default_value']);
    self::assertSame(TRUE, $form['forum']['inherit_global']['#default_value']);
  }

  /**
   * Aculta_image validates real raster bytes before a Forum Gallery uses them. */
  public function testAcultaImageAndGalleryAcceptOnlyContextualForumImages(): void {
    $this->config('aculta_portal.user_upload')
      ->set('allowed_extensions', 'png pdf')
      ->set('max_size_mb', 10)
      ->save();
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/G2cAAAAASUVORK5CYII=', TRUE);
    $file = $this->container->get('file.repository')->writeData($png, 'public://forum-user-upload.png');
    self::assertTrue($this->policy->isValidImage($file, 'forum'));

    $media = Media::create([
      'bundle' => 'forum_attachment',
      'name' => 'forum-user-upload.png',
      'status' => FALSE,
      'field_media_forum_attachment' => ['target_id' => $file->id()],
      'field_media_forum_image' => ['target_id' => $file->id(), 'alt' => 'Imagem de teste'],
    ]);
    $media->save();
    $gallery = MediaGallery::create([
      'title' => 'Galeria contextual do Fórum',
      'status' => FALSE,
      'field_gallery_purpose' => 'forum',
      'images' => [['target_id' => $media->id()]],
    ]);
    $gallery->save();

    $policy_hooks = new GalleryImagePolicyHooks();
    $policy_hooks->validateGalleryImages($gallery);
    self::assertFalse($media->isPublished(), 'Upload e galeria permanecem não publicados antes do comentário.');

    $gallery->set('field_gallery_purpose', '');
    $this->expectException(\InvalidArgumentException::class);
    $policy_hooks->validateGalleryImages($gallery);
  }

  /**
   * Installs the same field contracts used by ACULTA Forum Gallery. */
  private function configureForumGalleryFields(): void {
    MediaType::create([
      'id' => 'forum_attachment',
      'label' => 'Fórum',
      'source' => 'file',
      'source_configuration' => ['source_field' => 'field_media_forum_attachment'],
      'field_map' => ['name' => 'name'],
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_forum_attachment',
      'type' => 'file',
      'settings' => ['uri_scheme' => 'private'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media',
      'bundle' => 'forum_attachment',
      'field_name' => 'field_media_forum_attachment',
      'label' => 'Arquivo',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_forum_image',
      'type' => 'image',
      'settings' => ['uri_scheme' => 'public'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media',
      'bundle' => 'forum_attachment',
      'field_name' => 'field_media_forum_image',
      'label' => 'Imagem',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'media_gallery',
      'field_name' => 'field_gallery_purpose',
      'type' => 'string',
      'settings' => ['max_length' => 32, 'is_ascii' => TRUE, 'case_sensitive' => FALSE],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media_gallery',
      'bundle' => 'media_gallery',
      'field_name' => 'field_gallery_purpose',
      'label' => 'Finalidade',
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

}
