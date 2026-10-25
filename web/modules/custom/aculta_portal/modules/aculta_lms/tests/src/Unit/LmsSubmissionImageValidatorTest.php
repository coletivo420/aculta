<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_lms\Plugin\Validation\Constraint\LmsSubmissionImage;
use Drupal\aculta_lms\Plugin\Validation\Constraint\LmsSubmissionImageValidator;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** @coversClass(\Drupal\aculta_lms\Plugin\Validation\Constraint\LmsSubmissionImageValidator::class) */
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
    $validator->validate($this->file('answer.png', 10485761), new LmsSubmissionImage());
  }

  private function validator(): LmsSubmissionImageValidator {
    $image_factory = $this->createMock(\Drupal\Core\Image\ImageFactory::class);
    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->with('private://lms_file_upload/answer')->willReturn($this->path);
    return new LmsSubmissionImageValidator(new ImageInspector($image_factory), $file_system);
  }

  private function file(string $name, int|false $size): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn($name);
    $file->method('getFileUri')->willReturn('private://lms_file_upload/answer');
    $file->method('getSize')->willReturn($size === FALSE ? 0 : $size);
    return $file;
  }

}
