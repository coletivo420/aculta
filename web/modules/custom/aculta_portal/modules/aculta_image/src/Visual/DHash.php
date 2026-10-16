<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Visual;

/**
 * Impressão digital visual (dHash de 64 bits) com GD (aculta_image, fase M9 do plano). Código próprio, sem
 * dependência Composer.
 *
 * A imagem é reduzida a 9×8 tons de cinza; cada bit indica se um pixel é mais claro que o seguinte. Imagens iguais
 * ou só redimensionadas, recomprimidas ou levemente editadas ficam a poucos bits de distância. Resultado é sugestão:
 * nada é alterado a partir dele.
 */
final class DHash {

  /** Distância máxima (bits diferentes, de 64) para considerar um par parecido no relatório. */
  public const LIMIAR = 6;

  /** Impressão digital (16 caracteres hexadecimais) de uma imagem em GD, ou NULL. */
  public static function deGd(\GdImage $imagem): string {
    $pequena = imagecreatetruecolor(9, 8);
    imagecopyresampled($pequena, $imagem, 0, 0, 0, 0, 9, 8, imagesx($imagem), imagesy($imagem));
    $bits = '';
    for ($y = 0; $y < 8; $y++) {
      for ($x = 0; $x < 8; $x++) {
        $bits .= self::luminancia($pequena, $x, $y) > self::luminancia($pequena, $x + 1, $y) ? '1' : '0';
      }
    }
    $hex = '';
    foreach (str_split($bits, 4) as $quarteto) {
      $hex .= dechex((int) bindec($quarteto));
    }
    return $hex;
  }

  /** Impressão digital de um arquivo de imagem em $caminho, ou NULL se não for possível ler. */
  public static function deArquivo(string $caminho): ?string {
    $conteudo = is_file($caminho) ? file_get_contents($caminho) : FALSE;
    if ($conteudo === FALSE) {
      return NULL;
    }
    $imagem = @imagecreatefromstring($conteudo);
    if ($imagem === FALSE) {
      return NULL;
    }
    return self::deGd($imagem);
  }

  /** Quantidade de bits diferentes entre duas impressões digitais. */
  public static function distancia(string $a, string $b): int {
    $distancia = 0;
    for ($i = 0; $i < min(strlen($a), strlen($b)); $i++) {
      $xor = hexdec($a[$i]) ^ hexdec($b[$i]);
      while ($xor) {
        $distancia += $xor & 1;
        $xor >>= 1;
      }
    }
    return $distancia;
  }

  private static function luminancia(\GdImage $imagem, int $x, int $y): float {
    $cor = imagecolorat($imagem, $x, $y);
    $r = ($cor >> 16) & 0xFF;
    $g = ($cor >> 8) & 0xFF;
    $b = $cor & 0xFF;
    return 0.299 * $r + 0.587 * $g + 0.114 * $b;
  }

}
