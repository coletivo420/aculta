<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\Validation\Constraint;

use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\UploadPolicy;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/** Applies the central ACULTA image policy to image attachments. */
final class LmsSubmissionImageValidator extends ConstraintValidator {

  public function __construct(
    #[Autowire(service: 'aculta_image.inspector')]
    private readonly ImageInspector $imageInspector,
    #[Autowire(service: 'file_system')]
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /** {@inheritdoc} */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof LmsSubmissionImage) {
      throw new UnexpectedTypeException($constraint, LmsSubmissionImage::class);
    }
    if (!$value instanceof FileInterface) {
      throw new UnexpectedTypeException($value, FileInterface::class);
    }

    $extension = strtolower(pathinfo($value->getFilename(), PATHINFO_EXTENSION));
    $extensions = [
      'gif' => 'image/gif',
      'jpeg' => 'image/jpeg',
      'jpg' => 'image/jpeg',
      'png' => 'image/png',
      'webp' => 'image/webp',
    ];
    if (!isset($extensions[$extension])) {
      return;
    }

    $path = $this->fileSystem->realpath($value->getFileUri());
    $bytes = $path !== FALSE && is_readable($path) ? @file_get_contents($path) : FALSE;
    $image = is_string($bytes) ? $this->imageInspector->fromBytes($bytes) : NULL;
    if ($value->getSize() > UploadPolicy::MAX_BYTES
      || !is_string($bytes)
      || strlen($bytes) > UploadPolicy::MAX_BYTES
      || $image === NULL
      || ($image['mime'] ?? NULL) !== $extensions[$extension]) {
      $this->context->addViolation($constraint->message);
    }
  }

}
