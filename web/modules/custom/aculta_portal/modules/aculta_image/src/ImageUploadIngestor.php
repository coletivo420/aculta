<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\Core\File\FileExists;
use Drupal\file\Upload\FileUploadHandlerInterface;
use Drupal\file\Upload\FileUploadResult;
use Drupal\file\Upload\UploadedFileInterface;

/** Validates image uploads through ACULTA policy and Drupal's managed upload API. */
final class ImageUploadIngestor {

  public function __construct(
    private readonly UploadPolicy $policy,
    private readonly ImageInspector $inspector,
    private readonly FileUploadHandlerInterface $uploadHandler,
  ) {}

  /**
   * Ingests a raster image into a caller-selected managed-file directory.
   *
   * The caller supplies a private or public destination appropriate to the
   * owning feature. ACULTA image extension/size and raster validation remain
   * centralized here; Drupal Core creates and validates the managed file.
   */
  public function upload(UploadedFileInterface $uploadedFile, string $destination, array $allowedMimes = []): FileUploadResult {
    $extension = strtolower(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION));
    if (!ImageIngester::extensaoAceita($uploadedFile->getClientOriginalName())
      || !ImageIngester::tamanhoAceito($uploadedFile->getSize())) {
      throw new \InvalidArgumentException('A imagem não atende à política de upload do ACULTA.');
    }
    $bytes = @file_get_contents((string) $uploadedFile->getRealPath());
    $image = is_string($bytes) ? $this->inspector->fromBytes($bytes) : NULL;
    if ($image === NULL
      || !$this->matchesExtension($extension, (string) ($image['mime'] ?? ''))
      || ($allowedMimes !== [] && !in_array($image['mime'], $allowedMimes, TRUE))) {
      throw new \InvalidArgumentException('A imagem não atende à política de upload do ACULTA.');
    }

    return $this->uploadHandler->handleFileUpload(
      $uploadedFile,
      $this->policy->validators(),
      $destination,
      FileExists::Rename,
    );
  }

  private function matchesExtension(string $extension, string $mime): bool {
    $allowed = [
      'image/png' => ['png'],
      'image/jpeg' => ['jpg', 'jpeg'],
      'image/gif' => ['gif'],
      'image/webp' => ['webp'],
    ];
    return in_array($extension, $allowed[$mime] ?? [], TRUE);
  }

}
