<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Value;

/**
 * Formatos de saída do gerador de QR Code na versão 0.2.0.
 *
 * PNG e WebP são exportação com política de arquivo do aculta_image (0.4.0);
 * nesta versão somente a geração em memória existe.
 */
enum QrCodeFormat: string {

  case Svg = 'svg';

  case Png = 'png';

  /**
   * Retorna o tipo MIME da representação gerada.
   */
  public function mimeType(): string {
    return match ($this) {
      self::Svg => 'image/svg+xml',
      self::Png => 'image/png',
    };
  }

}
