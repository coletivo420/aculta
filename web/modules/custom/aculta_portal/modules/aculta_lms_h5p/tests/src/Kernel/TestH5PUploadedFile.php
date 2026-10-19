<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\file\Upload\UploadedFileInterface;
use Drupal\file\Validation\Constraint\UploadedFileConstraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Test upload adapter that exercises managed file movement without PHP HTTP. */
final class TestH5PUploadedFile implements UploadedFileInterface {

  public function __construct(private readonly UploadedFile $uploadedFile) {}

  public function getClientOriginalName(): string {
    return $this->uploadedFile->getClientOriginalName();
  }

  public function getSize(): int {
    return $this->uploadedFile->getSize();
  }

  public function getRealPath(): string|false {
    return $this->uploadedFile->getRealPath();
  }

  public function getPathname(): string {
    return $this->uploadedFile->getPathname();
  }

  public function getFilename(): string {
    return $this->uploadedFile->getFilename();
  }

  public function validate(ValidatorInterface $validator, array $options = []): ConstraintViolationListInterface {
    return $validator->validate($this->uploadedFile, new UploadedFileConstraint(...$options));
  }

}
