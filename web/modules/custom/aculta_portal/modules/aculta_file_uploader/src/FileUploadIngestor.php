<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\Core\File\FileExists;
use Drupal\file\Upload\FileUploadHandlerInterface;
use Drupal\file\Upload\FileUploadResult;
use Drupal\file\Upload\UploadedFileInterface;

/**
 * Ingestão única de uploads do ACULTA: política, conteúdo e destino.
 *
 * Evolução do ImageUploadIngestor do aculta_image. Nesta fase o núcleo ainda
 * não tem consumidores migrados; o aculta_image mantém a sua cópia até a
 * migração da Fase 4.
 */
final class FileUploadIngestor {

  /**
   * Tipos raster aceitos, como constantes do getimagesize.
   */
  private const RASTER_TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

  public function __construct(
    private readonly UploadPolicy $policy,
    private readonly MimeTypeVerifier $mimeVerifier,
    private readonly DestinationResolver $destinations,
    private readonly FileUploadHandlerInterface $uploadHandler,
  ) {}

  /**
   * Ingere uma imagem raster no destino resolvido por tokens.
   *
   * @param array<string, mixed> $tokenData
   *   Dados de contexto para os tokens do destino.
   * @param string|null $tokenType
   *   Tipo de token do Core usado pelo destino.
   *
   * @throws \InvalidArgumentException
   *   Quando o arquivo não atende à política, ao conteúdo ou ao destino.
   */
  public function upload(
    UploadedFileInterface $uploadedFile,
    string $destinationPattern,
    array $tokenData = [],
    ?string $tokenType = NULL,
    ?int $maxBytes = NULL,
    ?string $extensions = NULL,
  ): FileUploadResult {
    $extension = strtolower(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION));
    $validators = $this->policy->validators($maxBytes, $extensions);
    $allowed = explode(' ', $validators['FileExtension']['extensions']);
    $size = $uploadedFile->getSize();
    if (!in_array($extension, $allowed, TRUE) || $size <= 0 || $size > $validators['FileSizeLimit']['fileLimit']) {
      throw new \InvalidArgumentException('O arquivo não atende à política de upload do ACULTA.');
    }

    $bytes = @file_get_contents((string) $uploadedFile->getRealPath());
    if (!is_string($bytes) || !$this->isRaster($bytes) || !$this->mimeVerifier->matches($bytes, $extension)) {
      throw new \InvalidArgumentException('O conteúdo do arquivo não corresponde à extensão enviada.');
    }

    $destination = $this->destinations->resolve($destinationPattern, $tokenData, $tokenType);

    return $this->uploadHandler->handleFileUpload(
      $uploadedFile,
      $validators,
      $destination,
      FileExists::Rename,
    );
  }

  /**
   * Indica se os bytes são um raster aceito pela política.
   */
  private function isRaster(string $bytes): bool {
    $info = @getimagesizefromstring($bytes);
    return $info !== FALSE && in_array($info[2] ?? 0, self::RASTER_TYPES, TRUE);
  }

}
