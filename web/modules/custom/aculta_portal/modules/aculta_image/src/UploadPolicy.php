<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

/**
 * Política única de upload de imagem do ACULTA (aculta_image, seção 3.1 do plano).
 *
 * Extensões e tamanho máximo de todo campo ou formulário de imagem. Não inclui dimensão máxima: o limite de
 * 2 MB e 4096 px da conquista (aculta_game) é exceção aprovada e fica só nesse formulário.
 */
final class UploadPolicy {

  /** Extensões aceitas. */
  public const EXTENSIONS = 'png jpg jpeg webp';

  /** Tamanho máximo do arquivo enviado, em bytes (10 MB). */
  public const MAX_BYTES = 10485760;

  /** Validadores do Form API (managed_file / image) para um upload de imagem conforme a política. */
  public function validators(): array {
    return [
      'FileExtension' => ['extensions' => self::EXTENSIONS],
      'FileSizeLimit' => ['fileLimit' => self::MAX_BYTES],
    ];
  }

}
