<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\UploadPolicy as ImageUploadPolicy;
use Drupal\aculta_portal\Upload\UserUploadDialogBuilder;
use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\aculta_wiki\Hook\WikiEntryImageHooks;
use Drupal\aculta_wiki\Upload\WikiLeadImageUploader;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers the "Imagem de destaque" tab added to the verbete form.
 */
#[CoversClass(WikiEntryImageHooks::class)]
#[Group('aculta_wiki')]
final class WikiEntryImageHooksTest extends UnitTestCase {

  /**
   * A aba substitui o autocomplete do Media e usa a política da Wiki.
   */
  public function testAddsTheUploadTabAndHidesTheCoreWidget(): void {
    $form = $this->entryForm();

    $this->hooks('png jpg jpeg gif webp pdf docx', 10)->formAlter($form, $this->formState());

    self::assertFalse($form['field_wiki_image']['#access']);
    self::assertSame('vertical_tabs', $form['advanced']['#type']);
    self::assertSame('details', $form['wiki_lead_image']['#type']);
    self::assertSame('advanced', $form['wiki_lead_image']['#group']);
    self::assertTrue($form['wiki_lead_image']['#tree']);
    self::assertSame('managed_file', $form['wiki_lead_image']['file']['#type']);
    self::assertSame('private://wiki/imagens', $form['wiki_lead_image']['file']['#upload_location']);
    self::assertFalse($form['wiki_lead_image']['file']['#multiple']);
    self::assertSame([
      'FileExtension' => ['extensions' => 'png jpg jpeg gif webp'],
      'FileSizeLimit' => ['fileLimit' => 10 * 1048576],
      'FileIsImage' => [],
    ], $form['wiki_lead_image']['file']['#upload_validators']);
    self::assertSame(512, $form['wiki_lead_image']['alt']['#maxlength']);
    self::assertSame('', $form['wiki_lead_image']['alt']['#default_value']);
    self::assertArrayNotHasKey('remove', $form['wiki_lead_image']);
  }

  /**
   * Validação e envio rodam antes de salvar o verbete.
   */
  public function testWiresValidationAndSubmissionAroundTheNodeSave(): void {
    $form = $this->entryForm();

    $this->hooks('png jpg jpeg gif webp', 10)->formAlter($form, $this->formState());

    self::assertSame([
      'aculta_wiki.wiki_lead_image_uploader:validateUpload',
    ], $form['#validate']);
    self::assertSame([
      'aculta_wiki.wiki_lead_image_uploader:applyUpload',
      '::submitForm',
      'aculta_wiki.wiki_lead_image_uploader:prepareNodeSave',
      'aculta_wiki.wiki_lead_image_uploader:saveNode',
    ], $form['actions']['submit']['#submit']);
  }

  /**
   * Com imagem atual, a aba mostra a descrição e a opção de remover.
   */
  public function testKeepsTheTabReadableWhenAnImageIsAlreadySet(): void {
    $form = $this->entryForm();

    $this->hooks('png jpg jpeg gif webp', 10)->formAlter($form, $this->formState($this->nodeWithImage()));

    self::assertSame('Gato malhado', $form['wiki_lead_image']['alt']['#default_value']);
    self::assertSame('checkbox', $form['wiki_lead_image']['remove']['#type']);
    self::assertSame('item', $form['wiki_lead_image']['current']['#type']);
  }

  /**
   * Sem extensão de imagem habilitada, o campo central continua visível.
   */
  public function testLeavesTheCoreWidgetWhenThePolicyHasNoImageExtension(): void {
    $form = $this->entryForm();

    $this->hooks('pdf docx', 10)->formAlter($form, $this->formState());

    self::assertArrayNotHasKey('wiki_lead_image', $form);
    self::assertTrue($form['field_wiki_image']['#access']);
  }

  /**
   * Keeps the Core widget if this form cannot wrap its entity save handler.
   */
  public function testLeavesTheCoreWidgetWithoutAnEntitySaveHandler(): void {
    $form = $this->entryForm();
    $form['actions']['submit']['#submit'] = ['::submitForm'];

    $this->hooks('png jpg jpeg gif webp', 10)->formAlter($form, $this->formState());

    self::assertTrue($form['field_wiki_image']['#access']);
    self::assertArrayNotHasKey(WikiLeadImageUploader::TAB, $form);
  }

  /**
   * Campo sem acesso não recebe a aba.
   */
  public function testLeavesTheFormAloneWhenTheFieldIsNotEditable(): void {
    $form = $this->entryForm();
    $form['field_wiki_image']['#access'] = FALSE;

    $this->hooks('png jpg jpeg gif webp', 10)->formAlter($form, $this->formState());

    self::assertArrayNotHasKey('wiki_lead_image', $form);
  }

  /**
   * Formulários sem o campo de imagem não mudam.
   */
  public function testLeavesTheFormAloneWithoutTheLeadImageField(): void {
    $form = ['title' => ['#type' => 'textfield']];
    $original = $form;

    $this->hooks('png jpg jpeg gif webp', 10)->formAlter($form, $this->formState());

    self::assertSame($original, $form);
  }

  /**
   * Builds the hooks over the real Portal policy and the real dialog builder.
   */
  private function hooks(string $extensions, int $megabytes): WikiEntryImageHooks {
    $policy = $this->policy($extensions, $megabytes);
    return new WikiEntryImageHooks(
      $this->getStringTranslationStub(),
      $policy,
      new UserUploadDialogBuilder($policy, $this->getStringTranslationStub()),
      $this->uploader($policy),
    );
  }

  /**
   * Builds the uploader the hook hands the submit and validate callbacks.
   */
  private function uploader(UserUploadPolicy $policy): WikiLeadImageUploader {
    return new WikiLeadImageUploader(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(Connection::class),
      $this->createMock(FileSystemInterface::class),
      $policy,
      $this->createMock(AccountProxyInterface::class),
      $this->getStringTranslationStub(),
    );
  }

  /**
   * Builds the central upload policy over a fixed configuration.
   */
  private function policy(string $extensions, int $megabytes): UserUploadPolicy {
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(static fn (string $key) => match ($key) {
      'allowed_extensions' => $extensions,
      'max_size_mb' => $megabytes,
      default => NULL,
    });
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('aculta_portal.user_upload')->willReturn($config);

    return new UserUploadPolicy($config_factory, new ImageUploadPolicy(), new ImageInspector($this->createMock(ImageFactory::class)));
  }

  /**
   * Returns a minimal verbete form with the Media autocomplete and the buttons.
   */
  private function entryForm(): array {
    return [
      'title' => ['#type' => 'textfield'],
      'field_wiki_image' => [
        '#type' => 'entity_reference_autocomplete',
        '#access' => TRUE,
      ],
      'actions' => [
        'submit' => [
          '#type' => 'submit',
          '#submit' => ['::submitForm', '::save'],
        ],
      ],
    ];
  }

  /**
   * Returns the form state, optionally exposing a node as the form entity.
   */
  private function formState(?NodeInterface $node = NULL): FormStateInterface {
    $form_state = $this->createMock(FormStateInterface::class);
    if ($node !== NULL) {
      $form_object = $this->createMock(ContentEntityFormInterface::class);
      $form_object->method('getEntity')->willReturn($node);
      $form_state->method('getFormObject')->willReturn($form_object);
    }
    return $form_state;
  }

  /**
   * Returns a node whose lead image already points at an image Media.
   */
  private function nodeWithImage(): NodeInterface {
    $media = $this->createMock(MediaInterface::class);
    $media->method('label')->willReturn('gato.png');
    $media->method('hasField')->with('field_media_image')->willReturn(TRUE);
    $media_field = $this->createMock(FieldItemListInterface::class);
    $media_field->method('first')->willReturn($this->altItem('Gato malhado'));
    $media->method('get')->with('field_media_image')->willReturn($media_field);

    $reference = $this->createMock(ComplexDataInterface::class);
    $reference->method('getValue')->willReturn($media);
    $item = $this->createMock(FieldItemInterface::class);
    $item->method('get')->with('entity')->willReturn($reference);

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('field_wiki_image')->willReturn(TRUE);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('first')->willReturn($item);
    $node->method('get')->with('field_wiki_image')->willReturn($field);

    return $node;
  }

  /**
   * Returns a media image item whose alt text is the given string.
   */
  private function altItem(string $alt): FieldItemInterface {
    $alt_field = $this->createMock(ComplexDataInterface::class);
    $alt_field->method('getValue')->willReturn($alt);
    $item = $this->createMock(FieldItemInterface::class);
    $item->method('get')->with('alt')->willReturn($alt_field);
    return $item;
  }

}
