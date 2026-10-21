<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\UploadPolicy as ImageUploadPolicy;
use Drupal\aculta_lms\Hook\SubmissionImageHooks;
use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/** @coversClass(\Drupal\aculta_lms\Hook\SubmissionImageHooks::class) */
#[CoversClass(SubmissionImageHooks::class)]
#[Group('aculta_lms')]
final class SubmissionImageHooksTest extends UnitTestCase {

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

  public function testLeavesFormsWithoutManagedUploadAlone(): void {
    $form = ['answer' => ['#type' => 'textarea']];
    $original = $form;

    $this->hooks('pdf', 5)->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame($original, $form);
  }

  public function testKeepsNativeValidatorsWhenThePortalPolicyIsAbsent(): void {
    $form = [
      'file_upload' => [
        '#type' => 'managed_file',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'pdf exe'],
        ],
      ],
    ];

    (new SubmissionImageHooks(NULL))->answerFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame('pdf exe', $form['file_upload']['#upload_validators']['FileExtension']['extensions']);
    self::assertArrayHasKey('AcultaLmsSubmissionImage', $form['file_upload']['#upload_validators']);
  }

  /**
   * Builds the hook over the real Portal policy with a fixed site-wide config. */
  private function hooks(string $extensions, int $megabytes): SubmissionImageHooks {
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(static fn (string $key) => match ($key) {
      'allowed_extensions' => $extensions,
      'max_size_mb' => $megabytes,
      default => NULL,
    });
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_portal.user_upload')->willReturn($config);

    $policy = new UserUploadPolicy($configFactory, new ImageUploadPolicy(), new ImageInspector($this->createMock(ImageFactory::class)));
    return new SubmissionImageHooks($policy);
  }

}
