<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Drupal\file\Upload\UploadedFileInterface;
use Drupal\file\Validation\Constraint\UploadedFileConstraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 *
 * Test upload adapter that exercises managed file movement without PHP HTTP. */
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
final class Testaculta_H5PUploadedFile implements UploadedFileInterface {

  public function __construct(private readonly UploadedFile $uploadedFile) {}

  /**
   * {@inheritdoc} */
  public function getClientOriginalName(): string {
    return $this->uploadedFile->getClientOriginalName();
  }

  /**
   * {@inheritdoc} */
  public function getSize(): int {
    return $this->uploadedFile->getSize();
  }

  /**
   * {@inheritdoc} */
  public function getRealPath(): string|false {
    return $this->uploadedFile->getRealPath();
  }

  /**
   * {@inheritdoc} */
  public function getPathname(): string {
    return $this->uploadedFile->getPathname();
  }

  /**
   * {@inheritdoc} */
  public function getFilename(): string {
    return $this->uploadedFile->getFilename();
  }

  /**
   * {@inheritdoc} */
  public function validate(ValidatorInterface $validator, array $options = []): ConstraintViolationListInterface {
    return $validator->validate($this->uploadedFile, new UploadedFileConstraint(...$options));
  }

}
