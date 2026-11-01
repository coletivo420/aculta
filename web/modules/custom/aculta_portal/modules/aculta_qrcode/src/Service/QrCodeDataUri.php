<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Service;

/**
 * Monta um `data:` URI de imagem SVG a partir do conteúdo gerado.
 *
 * O SVG vai como imagem (`<img>`), não como markup inline: o navegador não executa nada dele.
 */
final class QrCodeDataUri {

  /**
   * URI `data:image/svg+xml;base64,...` do conteúdo informado.
   */
  public static function svg(string $svg): string {
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
  }

}
