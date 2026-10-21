<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Value;

/**
 * Formatos de imagem do gerador de QR Code.
 *
 * PNG e WebP são gravados como derivados temporários pelo `aculta_image`
 * quando exportados (ver `QrCodeExporter`). SVG nunca vira raster. PDF não é
 * formato de imagem: é gerado pelo Entity Print e entregue sem gravação.
 */
enum QrCodeFormat: string {

  case Svg = 'svg';

  case Png = 'png';

  case Webp = 'webp';

  /**
   * Retorna o tipo MIME da representação gerada.
   */
  public function mimeType(): string {
    return match ($this) {
      self::Svg => 'image/svg+xml',
      self::Png => 'image/png',
      self::Webp => 'image/webp',
    };
  }

}
