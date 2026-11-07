<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Hook\SubmissionImageHooks;
use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_file_uploader\MimeTypeVerifier;
use Drupal\aculta_file_uploader\UploadPolicy as FileUploadPolicy;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\aculta_file_uploader\ImageDimensionReader;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Testa os hooks de imagem de envio de respostas do LMS.
 */
#[CoversClass(SubmissionImageHooks::class)]
#[Group('aculta_lms')]
final class SubmissionImageHooksTest extends UnitTestCase {

  /**
   * Confirma que a restrição de imagem é adicionada ao envio de resposta.
   */
  public function testAddsAcultaImageConstraintToManagedAnswerUpload(): void {
    $form = [
      'file_upload' => [
        '#type' => 'managed_file',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'png jpg pdf'],
        ],
      ],
    ];

    $this->hooks('png jpg pdf docx', 10)->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertArrayHasKey('AcultaLmsSubmissionImage', $form['file_upload']['#upload_validators']);
    self::assertSame([], $form['file_upload']['#upload_validators']['AcultaLmsSubmissionImage']);
    self::assertSame('png jpg pdf', $form['file_upload']['#upload_validators']['FileExtension']['extensions']);
  }

  /**
   * Confirma que o texto de ajuda lista apenas as extensões aceitas.
   */
  public function testHelpTextListsOnlyTheExtensionsAccepted(): void {
    $form = [
      'file_upload' => [
        '#type' => 'managed_file',
        '#description' => 'Envie um arquivo. Extensões permitidas: png exe pdf txt.',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'png exe pdf txt'],
        ],
      ],
    ];

    $this->hooks('png pdf docx', 10)->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame('Envie um arquivo. Extensões permitidas: png pdf.', (string) $form['file_upload']['#description']);
  }

  /**
   * Confirma que a atividade não amplia extensões nem tamanho além da política do LMS.
   */
  public function testActivityCannotWidenExtensionsOrSizeBeyondTheLmsPolicy(): void {
    $form = [
      'file_upload' => [
        '#type' => 'managed_file',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'pdf exe'],
          'FileSizeLimit' => ['fileLimit' => 50 * 1024 * 1024],
        ],
      ],
    ];

    $this->hooks('pdf docx', 5)->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame('pdf', $form['file_upload']['#upload_validators']['FileExtension']['extensions']);
    self::assertSame(5 * 1048576, $form['file_upload']['#upload_validators']['FileSizeLimit']['fileLimit']);
  }

  /**
   * Confirma que formulários sem upload gerenciado não são alterados.
   */
  public function testLeavesFormsWithoutManagedUploadAlone(): void {
    $form = ['answer' => ['#type' => 'textarea']];
    $original = $form;

    $this->hooks('pdf', 5)->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame($original, $form);
  }

  /**
   * Confirma que os validadores nativos são mantidos sem a política do Portal.
   */
  public function testKeepsNativeValidatorsWhenThePortalPolicyIsAbsent(): void {
    $form = [
      'file_upload' => [
        '#type' => 'managed_file',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'pdf exe'],
        ],
      ],
    ];

    (new SubmissionImageHooks($this->getStringTranslationStub(), NULL))->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame('pdf exe', $form['file_upload']['#upload_validators']['FileExtension']['extensions']);
    self::assertArrayHasKey('AcultaLmsSubmissionImage', $form['file_upload']['#upload_validators']);
  }

  /**
   * Monta o hook sobre a política real do Portal com configuração fixa.
   *
   * @param string $extensions
   *   Extensões permitidas na configuração do site.
   * @param int $megabytes
   *   Tamanho máximo em megabytes.
   *
   * @return \Drupal\aculta_lms\Hook\SubmissionImageHooks
   *   Hook configurado para os testes.
   */
  private function hooks(string $extensions, int $megabytes): SubmissionImageHooks {
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(static fn (string $key) => match ($key) {
      'allowed_extensions' => $extensions,
      'max_size_mb' => $megabytes,
      default => NULL,
    });
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_file_uploader.settings')->willReturn($config);

    $policy = new ConsumerUploadPolicy($configFactory, new FileUploadPolicy(), new MimeTypeVerifier(), $this->createMock(FileSystemInterface::class), $this->createMock(ImageDimensionReader::class));
    return new SubmissionImageHooks($this->getStringTranslationStub(), $policy);
  }

}
