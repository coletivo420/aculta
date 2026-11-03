<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\aculta_file_uploader\UploadPolicy as FileUploadPolicy;

/**
 * Define a política única de upload de imagem do ACULTA.
 *
 * A seção 3.1 do plano descreve a política.
 *
 * Extensões e tamanho máximo de todo campo ou formulário de imagem. Não inclui
 * dimensão máxima. O limite de
 * 2 MB e 4096 px da conquista (aculta_game) é exceção aprovada e fica só nesse
 * formulário.
 */
final class UploadPolicy {

  /**
   * Extensões aceitas. Fonte única: aculta_file_uploader.
   */
  public const EXTENSIONS = FileUploadPolicy::EXTENSIONS;

  /**
   * Tamanho máximo do arquivo enviado, em bytes (10 MB). Fonte única:
   * aculta_file_uploader.
   */
  public const MAX_BYTES = FileUploadPolicy::MAX_BYTES;

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
    // Delegação: a regra vive no aculta_file_uploader (Fase 4, passo 1).
    return (new FileUploadPolicy())->validators($maxBytes, $extensions);
  }

}
