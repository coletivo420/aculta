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
   * @param \Drupal\file\Upload\UploadedFileInterface $uploadedFile
   *   Arquivo enviado a ingerir.
   * @param string $destinationPattern
   *   Padrão do destino com tokens.
   * @param array<string, mixed> $tokenData
   *   Dados de contexto para os tokens do destino.
   * @param string|null $tokenType
   *   Tipo de token do Core usado pelo destino.
   * @param int|null $maxBytes
   *   Tamanho máximo em bytes, ou NULL para o padrão da política.
   * @param string|null $extensions
   *   Extensões permitidas separadas por espaço, ou NULL para o padrão.
   * @param array $allowedMimes
   *   Tipos MIME aceitos para o conteúdo, quando houver restrição.
   *
   * @return \Drupal\file\Upload\FileUploadResult
   *   Resultado da ingestão do arquivo.
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
    array $allowedMimes = [],
  ): FileUploadResult {
    $extension = strtolower(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION));
    $validators = $this->policy->validators($maxBytes, $extensions);
    $allowed = explode(' ', $validators['FileExtension']['extensions']);
    $size = $uploadedFile->getSize();
    if (!in_array($extension, $allowed, TRUE) || $size <= 0 || $size > $validators['FileSizeLimit']['fileLimit']) {
      throw new \InvalidArgumentException('O arquivo não atende à política de upload do ACULTA.');
    }

    $bytes = @file_get_contents((string) $uploadedFile->getRealPath());
    $rasterMime = is_string($bytes) ? $this->rasterMime($bytes) : NULL;
    if ($rasterMime === NULL || !$this->mimeVerifier->matches($bytes, $extension)) {
      throw new \InvalidArgumentException('O conteúdo do arquivo não corresponde à extensão enviada.');
    }
    if ($allowedMimes !== [] && !in_array($rasterMime, $allowedMimes, TRUE)) {
      throw new \InvalidArgumentException('O tipo de imagem não corresponde ao campo.');
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
   * Retorna o MIME do raster aceito pela política, ou NULL se não for raster.
   */
  private function rasterMime(string $bytes): ?string {
    $info = @getimagesizefromstring($bytes);
    if ($info === FALSE || !in_array($info[2] ?? 0, self::RASTER_TYPES, TRUE)) {
      return NULL;
    }
    $mime = (string) ($info['mime'] ?? '');
    return $mime !== '' ? $mime : NULL;
  }

}
