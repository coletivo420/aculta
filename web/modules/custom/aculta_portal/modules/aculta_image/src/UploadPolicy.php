<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

/**
 * Política única de upload de imagem do ACULTA (aculta_image, seção 3.1 do plano).
 *
 * Extensões e tamanho máximo de todo campo ou formulário de imagem. Não inclui dimensão máxima. O limite de
 * 2 MB e 4096 px da conquista (aculta_game) é exceção aprovada e fica só nesse formulário.
 */
final class UploadPolicy {

  /** Extensões aceitas. */
  public const EXTENSIONS = 'png gif jpg jpeg webp';

  /** Tamanho máximo do arquivo enviado, em bytes (10 MB). */
  public const MAX_BYTES = 10485760;

  /**
   * Builds Form API validators for the central or a scoped image policy.
   *
   * Scoped extensions must be a subset of the central image catalog. A
   * feature may raise the size limit explicitly while retaining this check.
   *
   * @param int|null $maxBytes
   *   Maximum bytes. Defaults to 10 MB.
   * @param string|null $extensions
   *   Space-separated subset of the central image extensions.
   *
   * @return array<string, array<string, int|string>>
   *   Validators for the Managed File API.
   *
   * @throws \InvalidArgumentException
   *   When the scoped limit or extensions are invalid.
   */
  public function validators(?int $maxBytes = NULL, ?string $extensions = NULL): array {
    $maxBytes ??= self::MAX_BYTES;
    $extensions ??= self::EXTENSIONS;
    $allowedExtensions = array_values(array_unique(preg_split('/\s+/', trim($extensions)) ?: []));
    $centralExtensions = explode(' ', self::EXTENSIONS);
    if ($maxBytes < 1 || $allowedExtensions === []
      || array_diff($allowedExtensions, $centralExtensions) !== []) {
      throw new \InvalidArgumentException('A política contextual de imagem excede o catálogo ACULTA.');
    }
    return [
      'FileExtension' => ['extensions' => implode(' ', $allowedExtensions)],
      'FileSizeLimit' => ['fileLimit' => $maxBytes],
    ];
  }

}
