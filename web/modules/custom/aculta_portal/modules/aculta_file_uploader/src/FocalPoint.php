<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

/**
 * Matemática do ponto focal: coordenadas em percentual e recorte centrado.
 *
 * Parte pura, sem dependência do Drupal, para ser testada sem Runtime. A
 * gravação na Crop API e o efeito de imagem são etapas separadas.
 */
final class FocalPoint {

  /**
   * Limita uma coordenada percentual ao intervalo 0..100.
   */
  public static function clampPercent(float $value): float {
    return max(0.0, min(100.0, $value));
  }

  /**
   * Calcula o maior recorte com a proporção alvo que mantém o ponto focal.
   *
   * O recorte fica o mais centrado possível no ponto focal, sem sair da imagem.
   *
   * @param int $imageWidth
   *   Largura da imagem em pixels.
   * @param int $imageHeight
   *   Altura da imagem em pixels.
   * @param float $focalX
   *   Posição horizontal do ponto focal, em percentual (0..100).
   * @param float $focalY
   *   Posição vertical do ponto focal, em percentual (0..100).
   * @param float $ratio
   *   Proporção alvo, largura dividida pela altura. Deve ser maior que zero.
   *
   * @return array{x: int, y: int, width: int, height: int}
   *   Retângulo do recorte em pixels.
   *
   * @throws \InvalidArgumentException
   *   Quando as dimensões ou a proporção não são positivas.
   */
  public static function cropRectangle(int $imageWidth, int $imageHeight, float $focalX, float $focalY, float $ratio): array {
    if ($imageWidth < 1 || $imageHeight < 1 || $ratio <= 0.0) {
      throw new \InvalidArgumentException('Dimensões e proporção do recorte precisam ser positivas.');
    }

    // Maior recorte com a proporção alvo que cabe dentro da imagem.
    if ($imageWidth / $imageHeight > $ratio) {
      $height = $imageHeight;
      $width = (int) round($height * $ratio);
    }
    else {
      $width = $imageWidth;
      $height = (int) round($width / $ratio);
    }
    $width = min($width, $imageWidth);
    $height = min($height, $imageHeight);

    // Centraliza o recorte no ponto focal, sem sair dos limites da imagem.
    $focalPxX = self::clampPercent($focalX) / 100 * $imageWidth;
    $focalPxY = self::clampPercent($focalY) / 100 * $imageHeight;
    $x = (int) round($focalPxX - $width / 2);
    $y = (int) round($focalPxY - $height / 2);
    $x = max(0, min($x, $imageWidth - $width));
    $y = max(0, min($y, $imageHeight - $height));

    return ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
  }

}
