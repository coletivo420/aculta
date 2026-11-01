<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Visual;

/**
 * Calculates 64-bit difference hashes for visual image comparisons.
 *
 * The implementation uses GD and has no Composer dependency.
 *
 * A imagem é reduzida a 9×8 tons de cinza; cada bit indica se um pixel é mais
 * claro que o seguinte. Imagens iguais
 * ou só redimensionadas, recomprimidas ou levemente editadas ficam a poucos
 * bits de distância. Resultado é sugestão:
 * nada é alterado a partir dele.
 */
final class DHash {

  /**
   * Define a distância máxima para pares parecidos.
   *
   * A comparação usa os bits diferentes entre 64 no relatório.
   */
  public const LIMIAR = 6;

  /**
   * Calcula a impressão digital da imagem em GD.
   *
   * Retorna até 16 caracteres hexadecimais ou NULL.
   */
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

  /**
   * Calcula a impressão digital de um arquivo de imagem.
   *
   * Retorna NULL se não for possível ler o arquivo.
   */
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

  /**
   * Quantidade de bits diferentes entre duas impressões digitais. */
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

  /**
   * Calculates the luminance of a pixel.
   */
  private static function luminancia(\GdImage $imagem, int $x, int $y): float {
    $cor = imagecolorat($imagem, $x, $y);
    $r = ($cor >> 16) & 0xFF;
    $g = ($cor >> 8) & 0xFF;
    $b = $cor & 0xFF;
    return 0.299 * $r + 0.587 * $g + 0.114 * $b;
  }

}
