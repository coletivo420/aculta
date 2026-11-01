<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Value;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Endroid\QrCode\ErrorCorrectionLevel;

/**
 * Aparência de um QR Code com validação de legibilidade.
 *
 * O contraste mínimo de 4,5:1 (razão WCAG para texto normal) é a regra do
 * ACULTA para que o código seja lido em telas e em impressão comum. Presets
 * abaixo desse limite não existem.
 */
final readonly class QrCodePreset {

  public const MIN_SIZE = 128;

  public const MAX_SIZE = 2048;

  public const MAX_MARGIN = 200;

  public const MIN_CONTRAST_RATIO = 4.5;

  /**
   * Cria o preset validando identificador, cores, dimensões e contraste.
   *
   * @param string $id
   *   Identificador de máquina, minúsculo, com até 32 caracteres.
   * @param string $foreground
   *   Cor dos módulos em hexadecimal `#RRGGBB`.
   * @param string $background
   *   Cor de fundo em hexadecimal `#RRGGBB`.
   * @param int $size
   *   Lado da imagem em pixels, entre MIN_SIZE e MAX_SIZE.
   * @param int $margin
   *   Margem em pixels, entre 0 e MAX_MARGIN.
   * @param \Endroid\QrCode\ErrorCorrectionLevel $errorCorrection
   *   Nível de correção de erro.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   */
  public function __construct(
    public string $id,
    public string $foreground,
    public string $background,
    public int $size,
    public int $margin,
    public ErrorCorrectionLevel $errorCorrection,
  ) {
    if (preg_match('/^[a-z][a-z0-9_]{2,31}$/', $id) !== 1) {
      throw new InvalidQrCodeException('Identificador de preset inválido.');
    }
    foreach (['foreground' => $foreground, 'background' => $background] as $name => $color) {
      if (preg_match('/^#[0-9a-f]{6}$/i', $color) !== 1) {
        throw new InvalidQrCodeException("Cor de {$name} deve estar no formato #RRGGBB.");
      }
    }
    if ($size < self::MIN_SIZE || $size > self::MAX_SIZE) {
      throw new InvalidQrCodeException('Tamanho fora do intervalo permitido.');
    }
    if ($margin < 0 || $margin > self::MAX_MARGIN) {
      throw new InvalidQrCodeException('Margem fora do intervalo permitido.');
    }
    if (self::contrastRatio($foreground, $background) < self::MIN_CONTRAST_RATIO) {
      throw new InvalidQrCodeException('Contraste insuficiente para leitura do QR Code.');
    }
  }

  /**
   * Retorna a cor de módulos como componentes RGB.
   *
   * @return array{int, int, int}
   *   Componentes vermelho, verde e azul.
   */
  public function foregroundRgb(): array {
    return self::hexToRgb($this->foreground);
  }

  /**
   * Retorna a cor de fundo como componentes RGB.
   *
   * @return array{int, int, int}
   *   Componentes vermelho, verde e azul.
   */
  public function backgroundRgb(): array {
    return self::hexToRgb($this->background);
  }

  /**
   * Calcula a razão de contraste WCAG entre duas cores em hexadecimal.
   */
  public static function contrastRatio(string $first, string $second): float {
    $lighter = self::relativeLuminance($first);
    $darker = self::relativeLuminance($second);
    if ($lighter < $darker) {
      [$lighter, $darker] = [$darker, $lighter];
    }
    return ($lighter + 0.05) / ($darker + 0.05);
  }

  /**
   * Calcula a luminância relativa de uma cor conforme WCAG 2.x.
   */
  private static function relativeLuminance(string $hex): float {
    $channels = array_map(
      static function (int $value): float {
        $channel = $value / 255;
        return $channel <= 0.03928
          ? $channel / 12.92
          : (($channel + 0.055) / 1.055) ** 2.4;
      },
      self::hexToRgb($hex),
    );
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
  }

  /**
   * Converte `#RRGGBB` em componentes inteiros.
   *
   * @return array{int, int, int}
   *   Componentes vermelho, verde e azul.
   */
  private static function hexToRgb(string $hex): array {
    return [
      (int) hexdec(substr($hex, 1, 2)),
      (int) hexdec(substr($hex, 3, 2)),
      (int) hexdec(substr($hex, 5, 2)),
    ];
  }

}
