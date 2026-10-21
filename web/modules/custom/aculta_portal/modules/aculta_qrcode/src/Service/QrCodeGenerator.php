<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Service;

use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Drupal\aculta_qrcode\Value\QrCodePreset;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;

/**
 * Gera QR Codes em memória a partir de um conteúdo já validado.
 *
 * O serviço não grava arquivos e não consulta serviços externos. A exportação
 * de arquivo, planejada para 0.4.0, seguirá a política do `aculta_image`.
 */
final class QrCodeGenerator {

  /**
   * Gera a representação do QR Code.
   *
   * @param string $payload
   *   Conteúdo já validado pelo `QrCodePayloadValidator`.
   * @param \Drupal\aculta_qrcode\Value\QrCodePreset $preset
   *   Aparência e nível de correção de erro.
   * @param \Drupal\aculta_qrcode\Value\QrCodeFormat $format
   *   Formato de saída.
   *
   * @return string
   *   Conteúdo binário ou XML da imagem.
   */
  public function generate(string $payload, QrCodePreset $preset, QrCodeFormat $format): string {
    return $this->writer($format)->write($this->code($payload, $preset))->getString();
  }

  /**
   * Monta o modelo do QR Code com as cores e dimensões do preset.
   */
  private function code(string $payload, QrCodePreset $preset): QrCode {
    [$fr, $fg, $fb] = $preset->foregroundRgb();
    [$br, $bg, $bb] = $preset->backgroundRgb();

    return new QrCode(
      data: $payload,
      encoding: new Encoding('UTF-8'),
      errorCorrectionLevel: $preset->errorCorrection,
      size: $preset->size,
      margin: $preset->margin,
      roundBlockSizeMode: RoundBlockSizeMode::Margin,
      foregroundColor: new Color($fr, $fg, $fb),
      backgroundColor: new Color($br, $bg, $bb),
    );
  }

  /**
   * Escolhe o writer do formato solicitado.
   */
  private function writer(QrCodeFormat $format): WriterInterface {
    return match ($format) {
      QrCodeFormat::Svg => new SvgWriter(),
      QrCodeFormat::Png => new PngWriter(),
    };
  }

}
