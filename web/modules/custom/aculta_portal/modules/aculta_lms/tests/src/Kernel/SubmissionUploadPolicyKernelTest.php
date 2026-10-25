<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\aculta_lms\Hook\SubmissionImageHooks;
use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Regressão do teto central de upload nas respostas com arquivo do LMS.
 *
 * O aculta_portal e o aculta_lms não são habilitados no Kernel: a política é
 * montada com os mesmos serviços reais do aculta_image que o container do site
 * injeta, e o hook é o mesmo registrado em aculta_lms.services.yml.
 */
#[Group('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class SubmissionUploadPolicyKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'image',
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
      'system', 'user', 'file', 'image', 'aculta_image', 'aculta_portal_upload_test',
    ]);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->policy = new UserUploadPolicy(
      $this->container->get('config.factory'),
      $this->container->get('aculta_image.upload_policy'),
      $this->container->get('aculta_image.inspector'),
    );
  }

  /**
   * The effective LMS policy stays identical while the tab inherits global. */
  public function testInheritedPolicyKeepsTheEffectivePolicyUnchanged(): void {
    self::assertSame(['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'docx', 'xls', 'xlsx'], $this->policy->allowedExtensions('lms'));
    self::assertSame(10 * 1024 * 1024, $this->policy->maxBytes('lms'));

    $element = $this->alterForm([
      'FileExtension' => ['extensions' => 'png pdf docx'],
      'FileSizeLimit' => ['fileLimit' => 10 * 1024 * 1024],
    ]);

    self::assertArrayHasKey('AcultaLmsSubmissionImage', $element['#upload_validators']);
    self::assertSame('png pdf docx', $element['#upload_validators']['FileExtension']['extensions']);
    self::assertSame(10 * 1024 * 1024, $element['#upload_validators']['FileSizeLimit']['fileLimit']);
    self::assertSame('Envie um arquivo. Extensões permitidas: png pdf docx.', (string) $element['#description']);
  }

  /**
   * An activity cannot widen extensions or raise the size ceiling. */
  public function testActivityCannotWidenTheInheritedPolicy(): void {
    $element = $this->alterForm([
      'FileExtension' => ['extensions' => 'png exe txt'],
      'FileSizeLimit' => ['fileLimit' => 50 * 1024 * 1024],
    ]);

    self::assertSame('png', $element['#upload_validators']['FileExtension']['extensions']);
    self::assertSame(10 * 1024 * 1024, $element['#upload_validators']['FileSizeLimit']['fileLimit']);
    self::assertSame('Envie um arquivo. Extensões permitidas: png.', (string) $element['#description']);
  }

  /**
   * A saved LMS tab caps the response without touching the global policy. */
  public function testSavedLmsOverrideCapsTheResponse(): void {
    $this->config('aculta_portal.user_upload')
      ->set('allowed_extensions', 'png jpg pdf docx')
      ->set('max_size_mb', 20)
      ->set('consumers.lms', [
        'inherit_global' => FALSE,
        'allowed_extensions' => 'pdf docx',
        'max_size_mb' => 5,
      ])
      ->save();

    self::assertSame(['pdf', 'docx'], $this->policy->allowedExtensions('lms'));
    self::assertSame(5 * 1024 * 1024, $this->policy->maxBytes('lms'));

    $element = $this->alterForm([
      'FileExtension' => ['extensions' => 'png pdf docx'],
      'FileSizeLimit' => ['fileLimit' => 20 * 1024 * 1024],
    ]);
    self::assertSame('pdf docx', $element['#upload_validators']['FileExtension']['extensions']);
    self::assertSame(5 * 1024 * 1024, $element['#upload_validators']['FileSizeLimit']['fileLimit']);
    self::assertSame('Envie um arquivo. Extensões permitidas: pdf docx.', (string) $element['#description']);

    self::assertSame(['png', 'jpg', 'pdf', 'docx'], $this->policy->allowedExtensions());
    self::assertSame(20 * 1024 * 1024, $this->policy->maxBytes());
  }

  /**
   * Forms without a managed upload element are left untouched. */
  public function testFormsWithoutManagedUploadAreUntouched(): void {
    $form = ['file_upload' => ['#type' => 'managed_file']];
    $this->hooks($this->policy)->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame(['#type' => 'managed_file'], $form['file_upload']);
  }

  /**
   * Without the policy service, native validators and the image check remain. */
  public function testMissingPolicyKeepsNativeValidators(): void {
    $form = [
      'file_upload' => [
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'png pdf exe'],
          'FileSizeLimit' => ['fileLimit' => 50 * 1024 * 1024],
        ],
      ],
    ];
    $this->hooks(NULL)->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame([
      'FileExtension' => ['extensions' => 'png pdf exe'],
      'FileSizeLimit' => ['fileLimit' => 50 * 1024 * 1024],
      'AcultaLmsSubmissionImage' => [],
    ], $form['file_upload']['#upload_validators']);
    self::assertArrayNotHasKey('#description', $form['file_upload']);
  }

  /**
   * Builds the production hook with the given policy. */
  private function hooks(?UserUploadPolicy $policy): SubmissionImageHooks {
    return new SubmissionImageHooks($this->container->get('string_translation'), $policy);
  }

  /**
   * Applies the hook to a minimal answer form and returns the upload element. */
  private function alterForm(array $validators): array {
    $form = ['file_upload' => ['#upload_validators' => $validators]];
    $this->hooks($this->policy)->answerFormAlter($form, $this->createMock(FormStateInterface::class));
    return $form['file_upload'];
  }

}
