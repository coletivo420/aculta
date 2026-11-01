<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_file_uploader\MimeTypeVerifier;
use Drupal\aculta_file_uploader\UploadPolicy;
use Drupal\aculta_lms\Plugin\Validation\Constraint\LmsSubmissionImage;
use Drupal\aculta_lms\Plugin\Validation\Constraint\LmsSubmissionImageValidator;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\Image;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[CoversClass(LmsSubmissionImageValidator::class)]
final class LmsSubmissionImageValidatorTest extends TestCase {

  private string $path;

  protected function setUp(): void {
    parent::setUp();
    $this->path = tempnam(sys_get_temp_dir(), 'aculta-lms-image-');
    self::assertNotFalse($this->path);
  }

  protected function tearDown(): void {
    if (isset($this->path) && is_file($this->path)) {
      unlink($this->path);
    }
    parent::tearDown();
  }

  public function testValidRasterImagePassesAndNonImageUsesNativeValidation(): void {
    file_put_contents($this->path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=', TRUE));
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::never())->method('addViolation');
    $validator = $this->validator();
    $validator->initialize($context);

    $validator->validate($this->file('answer.png', filesize($this->path)), new LmsSubmissionImage());
    $validator->validate($this->file('answer.pdf', filesize($this->path)), new LmsSubmissionImage());
  }

  public function testInvalidImageAndOversizedImageAreRejected(): void {
    file_put_contents($this->path, 'not an image');
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects(self::exactly(2))->method('addViolation');
    $validator = $this->validator();
    $validator->initialize($context);

    $validator->validate($this->file('answer.png', filesize($this->path)), new LmsSubmissionImage());
    $validator->validate($this->file('answer.png', UploadPolicy::MAX_BYTES + 1), new LmsSubmissionImage());
  }

  private function validator(): LmsSubmissionImageValidator {
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(static fn (string $key) => match ($key) {
      'allowed_extensions' => 'png gif jpg jpeg webp pdf docx xls xlsx',
      'max_size_mb' => 10,
      default => NULL,
    });
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('aculta_file_uploader.settings')->willReturn($config);

    $image = $this->createMock(Image::class);
    $image->method('isValid')->willReturn(TRUE);
    $image->method('getWidth')->willReturn(1);
    $image->method('getHeight')->willReturn(1);
    $image_factory = $this->createMock(ImageFactory::class);
    $image_factory->method('get')->willReturn($image);

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->with('private://lms_file_upload/answer')->willReturn($this->path);

    return new LmsSubmissionImageValidator(new ConsumerUploadPolicy($config_factory, new UploadPolicy(), new MimeTypeVerifier(), $file_system, $image_factory));
  }

  private function file(string $name, int|false $size): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn($name);
    $file->method('getFileUri')->willReturn('private://lms_file_upload/answer');
    $file->method('getSize')->willReturn($size === FALSE ? 0 : $size);
    return $file;
  }

}
