<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\Core\Image\ImageFactory;

/**
 * Leitura de dimensões de imagem (aculta_image). Único acesso a ImageFactory do site.
 */
final class ImageInspector {

  public function __construct(private readonly ImageFactory $imageFactory) {}

  /** Dimensões e MIME de uma imagem em memória, ou NULL fora da política raster. */
  public function fromBytes(string $bytes): ?array {
    $info = @getimagesizefromstring($bytes);
    if ($info === FALSE || !in_array($info[2] ?? 0, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP], TRUE)) {
      return NULL;
    }
    return [
      'width' => (int) $info[0],
      'height' => (int) $info[1],
      'mime' => (string) ($info['mime'] ?? ''),
    ];
  }

  /** Largura e altura da imagem em $uri, ou NULL se o arquivo não é uma imagem válida. */
  public function dimensions(string $uri): ?array {
    $image = $this->imageFactory->get($uri);
    if (!$image->isValid() || $image->getWidth() < 1 || $image->getHeight() < 1) {
      return NULL;
    }
    return ['width' => $image->getWidth(), 'height' => $image->getHeight()];
  }

}
