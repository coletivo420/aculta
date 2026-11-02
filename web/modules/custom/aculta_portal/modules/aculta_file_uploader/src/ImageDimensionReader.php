<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\Core\File\FileSystemInterface;

/**
 * Lê dimensões de imagem para validação de upload, sem ImageFactory.
 *
 * Camada abaixo do aculta_image: não depende dele (aculta_image depende deste módulo). Usa getimagesize() nativo
 * sobre o caminho real do arquivo. Retorna NULL quando o arquivo não é uma imagem com dimensões positivas.
 */
class ImageDimensionReader {

  public function __construct(private readonly FileSystemInterface $fileSystem) {}

  /**
   * @return array{width: int, height: int}|null
   */
  public function dimensions(string $uri): ?array {
    $path = $this->fileSystem->realpath($uri);
    if ($path === FALSE || $path === '' || !is_file($path)) {
      return NULL;
    }
    $info = @getimagesize($path);
    if ($info === FALSE || $info[0] < 1 || $info[1] < 1) {
      return NULL;
    }
    return ['width' => (int) $info[0], 'height' => (int) $info[1]];
  }

}
