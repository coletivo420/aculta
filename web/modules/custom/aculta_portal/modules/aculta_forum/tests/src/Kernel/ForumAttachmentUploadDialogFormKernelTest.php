<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Form\ForumAttachmentUploadDialogForm;
use Drupal\aculta_portal\Upload\UserUploadDialogBuilder;
use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\UploadPolicy as ImageUploadPolicy;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the attachment dialog creates unpublished Media from one valid file.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumAttachmentUploadDialogFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'file',
    'image',
    'media',
    'editor',
  ];

  /**
   * Member account.
   */
  private User $member;

  /**
   * Policy shared by the form and the dialog builder.
   */
  private UserUploadPolicy $policy;

  /**
   * Form under test.
   */
  private ForumAttachmentUploadDialogForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig([
      'system',
      'user',
      'field',
      'filter',
      'text',
      'file',
      'image',
      'media',
      'editor',
    ]);
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_forum_attachment',
      'type' => 'file',
      'settings' => [
        'target_type' => 'file',
        'display_field' => FALSE,
        'display_default' => FALSE,
        'uri_scheme' => 'private',
      ],
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_forum_image',
      'type' => 'image',
      'settings' => [
        'uri_scheme' => 'private',
        'file_extensions' => 'png jpg jpeg gif webp',
        'alt_field' => TRUE,
        'alt_field_required' => TRUE,
      ],
    ])->save();
    MediaType::create([
      'id' => 'forum_attachment',
      'label' => 'Anexo do Fórum',
      'source' => 'file',
      'source_configuration' => [
        'source_field' => 'field_media_forum_attachment',
      ],
      'field_map' => ['name' => 'name'],
      'queue_thumbnail_downloads' => FALSE,
      'new_revision' => TRUE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media',
      'bundle' => 'forum_attachment',
      'field_name' => 'field_media_forum_attachment',
      'label' => 'Arquivo',
      'settings' => [
        'handler' => 'default:file',
        'handler_settings' => [],
        'file_directory' => 'forum/anexos',
        'file_extensions' => 'png jpg jpeg gif webp pdf docx xls xlsx',
        'max_filesize' => '',
        'description_field' => FALSE,
      ],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media',
      'bundle' => 'forum_attachment',
      'field_name' => 'field_media_forum_image',
      'label' => 'Imagem',
      'settings' => [
        'handler' => 'default:file',
        'handler_settings' => [],
        'file_directory' => 'forum/anexos',
        'file_extensions' => 'png jpg jpeg gif webp',
        'max_filesize' => '',
        'description_field' => FALSE,
      ],
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->member = User::create(['name' => 'membro', 'status' => 1]);
    $this->member->save();
    $this->container->get('current_user')->setAccount($this->member);

    $this->policy = new UserUploadPolicy(
      $this->container->get('config.factory'),
      new ImageUploadPolicy(),
      new ImageInspector($this->container->get('image.factory')),
    );
    $this->form = new ForumAttachmentUploadDialogForm(
      $this->container->get('entity_type.manager'),
      $this->container->get('current_user'),
      $this->policy,
      new UserUploadDialogBuilder($this->policy, $this->container->get('string_translation')),
    );
  }

  /**
   * One valid document becomes an unpublished Media and a permanent file.
   */
  public function testDocumentCreatesUnpublishedMedia(): void {
    $file = $this->file('anexo.pdf', 'documento de teste');
    $state = new FormState();
    $state->setValue('document_file', [$file->id()]);

    $form = [];
    $response = $this->form->ajaxSubmit($form, $state);

    self::assertFalse($state->hasAnyErrors());
    $media = Media::loadMultiple();
    self::assertCount(1, $media);
    $media = reset($media);
    self::assertSame('forum_attachment', $media->bundle());
    self::assertFalse($media->isPublished());
    self::assertSame((int) $file->id(), (int) $media->get('field_media_forum_attachment')->target_id);
    self::assertSame((int) $this->member->id(), (int) $media->getOwnerId());
    $stored = File::load($file->id());
    self::assertNotNull($stored);
    self::assertTrue($stored->isPermanent(), 'The managed upload survives temporary-file cleanup.');

    $commands = $response->getCommands();
    $names = array_column($commands, 'command');
    self::assertContains('editorDialogSave', $names);
    self::assertContains('closeDialog', $names);
    $save = $commands[array_search('editorDialogSave', $names, TRUE)];
    self::assertSame('media', $save['values']['attributes']['data-entity-type']);
    self::assertSame($media->uuid(), $save['values']['attributes']['data-entity-uuid']);
    self::assertSame(
      'entity_reference:entity_reference_entity_view',
      $save['values']['attributes']['data-entity-embed-display'],
    );
  }

  /**
   * More than one file, missing file and incompatible types are refused.
   */
  public function testInvalidSubmissionsAreRefused(): void {
    $document = $this->file('anexo.pdf', 'documento');
    $image = $this->file('foto.png', 'imagem falsa');

    $twoFiles = new FormState();
    $twoFiles->setValue('document_file', [$document->id()]);
    $twoFiles->setValue('image_file', [$image->id()]);
    $build = [];
    $this->form->validateForm($build, $twoFiles);
    self::assertArrayHasKey('image_file', $twoFiles->getErrors());

    $missing = new FormState();
    $missing->setValue('document_file', [999999]);
    $build = [];
    $this->form->validateForm($build, $missing);
    self::assertArrayHasKey('document_file', $missing->getErrors());

    $executable = $this->file('programa.exe', 'executável');
    $badType = new FormState();
    $badType->setValue('document_file', [$executable->id()]);
    $build = [];
    $this->form->validateForm($build, $badType);
    self::assertArrayHasKey('document_file', $badType->getErrors());

    $noAlt = new FormState();
    $noAlt->setValue('image_file', [$image->id()]);
    $noAlt->setValue('image_alt', '   ');
    $build = [];
    $this->form->validateForm($build, $noAlt);
    self::assertArrayHasKey('image_alt', $noAlt->getErrors());

    self::assertSame([], Media::loadMultiple());
  }

  /**
   * Creates a stored file with the requested name and content size.
   */
  private function file(string $filename, string $contents): File {
    $directory = 'public://forum-anexos-test';
    $this->container->get('file_system')->prepareDirectory($directory, 0775 | 1);
    file_put_contents($directory . '/' . $filename, $contents);
    $file = File::create([
      'uri' => $directory . '/' . $filename,
      'filename' => $filename,
      'filemtime' => time(),
      'filesize' => strlen($contents),
      'status' => 0,
    ]);
    $file->save();
    return $file;
  }

}
