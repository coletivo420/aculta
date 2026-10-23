<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\UploadPolicy as ImageUploadPolicy;
use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\aculta_wiki\Upload\WikiLeadImageUploader;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers the lead image upload of Wiki entries.
 */
#[CoversClass(WikiLeadImageUploader::class)]
#[Group('aculta_wiki')]
final class WikiLeadImageUploaderTest extends UnitTestCase {

  /**
   * Uma imagem raster com descrição passa na validação.
   */
  public function testAcceptsOneValidImageWithAltText(): void {
    $form_state = $this->formState(['file' => [5], 'alt' => 'Gato malhado']);
    $form = [];

    $this->uploader($this->file())->validateUpload($form, $form_state);

    self::assertSame([], $form_state->getErrors());
  }

  /**
   * A descrição é obrigatória ao enviar uma imagem.
   */
  public function testRequiresAltTextForNewImage(): void {
    $form_state = $this->formState(['file' => [5], 'alt' => '  ']);
    $form = [];

    $this->uploader($this->file())->validateUpload($form, $form_state);

    self::assertArrayHasKey('wiki_lead_image][alt', $form_state->getErrors());
  }

  /**
   * A descrição segue o limite de 512 caracteres do Fórum.
   */
  public function testRejectsAltTextLongerThanTheForumLimit(): void {
    $form_state = $this->formState(['file' => [5], 'alt' => str_repeat('a', 513)]);
    $form = [];

    $this->uploader($this->file())->validateUpload($form, $form_state);

    self::assertArrayHasKey('wiki_lead_image][alt', $form_state->getErrors());
  }

  /**
   * Apenas uma imagem por envio.
   */
  public function testRejectsMoreThanOneFile(): void {
    $form_state = $this->formState(['file' => [5, 6], 'alt' => 'Gato']);
    $form = [];

    $this->uploader($this->file())->validateUpload($form, $form_state);

    self::assertArrayHasKey('wiki_lead_image][file', $form_state->getErrors());
  }

  /**
   * Um documento disfarçado de imagem é recusado.
   */
  public function testRejectsFileOutsideImageExtensions(): void {
    $form_state = $this->formState(['file' => [5], 'alt' => 'Relatório']);
    $form = [];

    $this->uploader($this->file('relatorio.pdf'))->validateUpload($form, $form_state);

    self::assertArrayHasKey('wiki_lead_image][file', $form_state->getErrors());
  }

  /**
   * A aba intocada não bloqueia o formulário.
   */
  public function testIgnoresTheTabWhenNoFileWasUploaded(): void {
    $form_state = $this->formState(['alt' => '']);
    $form = [];

    $this->uploader()->validateUpload($form, $form_state);

    self::assertSame([], $form_state->getErrors());
  }

  /**
   * Media publication follows the submitted revision; failures roll back.
   */
  #[DataProvider('submissionPublicationStates')]
  public function testImageAndNodeShareTransactionAcrossPublicationStates(bool $published, bool $rebuild): void {
    $file = $this->file();
    $file->expects(self::once())->method('isTemporary')->willReturn(TRUE);
    $file->expects(self::once())->method('setPermanent');
    $file->expects(self::once())->method('save');
    $database = $this->createMock(Connection::class);
    $transaction = new class() extends Transaction {

      /**
       * Whether the test transaction was committed.
       */
      public bool $committed = FALSE;

      /**
       * Whether the test transaction was rolled back.
       */
      public bool $rolledBack = FALSE;

      public function __construct() {}

      /**
       * {@inheritdoc}
       */
      public function __destruct() {}

      /**
       * {@inheritdoc}
       */
      public function commitOrRelease(): void {
        $this->committed = TRUE;
      }

      /**
       * {@inheritdoc}
       */
      public function rollBack() {
        $this->rolledBack = TRUE;
      }

    };
    $database->expects(self::once())->method('startTransaction')->willReturn($transaction);
    $media = $this->createMock(MediaInterface::class);
    $media->method('id')->willReturn(12);
    $mediaValues = [];
    $media->expects(self::exactly(2))->method('set')->willReturnCallback(
      static function (string $field, mixed $value) use (&$mediaValues, $media): MediaInterface {
        $mediaValues[$field] = $value;
        return $media;
      },
    );
    $media->expects(self::exactly(2))->method('save');
    $media_storage = $this->createMock(EntityStorageInterface::class);
    $media_storage->expects(self::once())->method('create')->with([
      'bundle' => 'image',
      'name' => 'gato.png',
      'uid' => 7,
      'status' => FALSE,
    ])->willReturn($media);
    $media_storage->expects(self::once())->method('load')->with(12)->willReturn($media);
    $form_state = $this->formState(['file' => [5], 'alt' => 'Gato malhado']);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('target_id')->willReturn(12);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('wiki_entry');
    $node->method('hasField')->with(WikiLeadImageUploader::FIELD)->willReturn(TRUE);
    $node->method('get')->with(WikiLeadImageUploader::FIELD)->willReturn($field);
    $node->method('isPublished')->willReturn($published);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($node);
    $form_object->expects(self::once())->method('save')->willReturnCallback(
      static function (array $form, FormStateInterface $state) use ($rebuild): void {
        if ($rebuild) {
          $state->setRebuild();
        }
      },
    );
    $form_state->setFormObject($form_object);
    $form = [];

    $uploader = $this->uploader($file, $media_storage, $database);
    $uploader->applyUpload($form, $form_state);
    $uploader->prepareNodeSave($form, $form_state);
    $uploader->saveNode($form, $form_state);

    self::assertSame([
      'field_media_image' => ['target_id' => 5, 'alt' => 'Gato malhado'],
      'status' => $published,
    ], $mediaValues);
    self::assertSame(!$rebuild, $transaction->committed);
    self::assertSame($rebuild, $transaction->rolledBack);
    self::assertSame([['target_id' => 12]], $form_state->getValue([WikiLeadImageUploader::FIELD]));
    self::assertSame(['fid' => 5, 'media_id' => 12], $form_state->get(WikiLeadImageUploader::STORAGE_KEY));
  }

  /**
   * Publication and failed-save cases for the shared image transaction.
   *
   * @return array<string, array{0: bool, 1: bool}>
   *   Submitted publication state and whether the node form rebuilds on error.
   */
  public static function submissionPublicationStates(): array {
    return [
      'published node commits published media' => [TRUE, FALSE],
      'draft node commits unpublished media' => [FALSE, FALSE],
      'failed node save rolls back staged media' => [TRUE, TRUE],
    ];
  }

  /**
   * Reenvio após erro reutiliza a mídia já criada.
   */
  public function testReusesTheMediaCreatedEarlierInTheSameFormState(): void {
    $media = $this->createMock(MediaInterface::class);
    $media->method('id')->willReturn(12);
    $media_storage = $this->createMock(EntityStorageInterface::class);
    $media_storage->expects(self::never())->method('create');
    $media_storage->method('load')->with(12)->willReturn($media);
    $form_state = $this->formState(['file' => [5], 'alt' => 'Gato malhado']);
    $form = [];
    $form_state->set(WikiLeadImageUploader::STORAGE_KEY, ['fid' => 5, 'media_id' => 12]);

    $this->uploader($this->file(), $media_storage)->applyUpload($form, $form_state);

    self::assertSame([['target_id' => 12]], $form_state->getValue([WikiLeadImageUploader::FIELD]));
  }

  /**
   * A caixa marcada limpa a imagem atual.
   */
  public function testRemovesTheCurrentImageOnlyWhenTheBoxIsChecked(): void {
    $form_state = $this->formState(['remove' => 1]);
    $form = [];

    $this->uploader()->applyUpload($form, $form_state);

    self::assertSame([], $form_state->getValue([WikiLeadImageUploader::FIELD]));
  }

  /**
   * Sem envio e sem remoção, o campo não é alterado.
   */
  public function testKeepsTheCurrentImageWhenNothingWasSent(): void {
    $form_state = $this->formState([]);
    $form = [];

    $this->uploader()->applyUpload($form, $form_state);

    self::assertNull($form_state->getValue([WikiLeadImageUploader::FIELD]));
  }

  /**
   * Builds an uploader over the real Portal policy and a fixed image mock.
   */
  private function uploader(?FileInterface $file = NULL, ?EntityStorageInterface $media_storage = NULL, ?Connection $database = NULL): WikiLeadImageUploader {
    $file_storage = $this->createMock(EntityStorageInterface::class);
    $file_storage->method('load')->with(5)->willReturn($file);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['file', $file_storage],
      ['media', $media_storage ?? $this->createMock(EntityStorageInterface::class)],
    ]);

    $image = $this->createMock(ImageInterface::class);
    $image->method('isValid')->willReturn(TRUE);
    $image->method('getWidth')->willReturn(800);
    $image->method('getHeight')->willReturn(600);
    $image_factory = $this->createMock(ImageFactory::class);
    $image_factory->method('get')->willReturn($image);

    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(static fn (string $key) => match ($key) {
      'allowed_extensions' => 'png jpg jpeg gif webp pdf docx xls xlsx',
      'max_size_mb' => 10,
      default => NULL,
    });
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('aculta_portal.user_upload')->willReturn($config);
    $policy = new UserUploadPolicy($config_factory, new ImageUploadPolicy(), new ImageInspector($image_factory));

    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('id')->willReturn(7);
    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('prepareDirectory')->willReturn(TRUE);

    return new WikiLeadImageUploader(
      $entity_type_manager,
      $database ?? $this->createMock(Connection::class),
      $file_system,
      $policy,
      $current_user,
      $this->getStringTranslationStub(),
    );
  }

  /**
   * Builds a form state with values inside the "Imagem de destaque" tab.
   */
  private function formState(array $tab): FormState {
    $form_state = new FormState();
    $form_state->setValues([WikiLeadImageUploader::TAB => $tab]);
    return $form_state;
  }

  /**
   * Returns a temporary PNG upload that passes the policy inspection.
   */
  private function file(string $filename = 'gato.png'): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn($filename);
    $file->method('getSize')->willReturn(1000);
    $file->method('getFileUri')->willReturn('private://wiki/imagens/' . $filename);
    $file->method('id')->willReturn(5);
    return $file;
  }

}
