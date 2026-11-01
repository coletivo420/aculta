<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\Visual\DHash;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Impressão digital visual de imagens.
 *
 * Imagens iguais têm distância zero, redimensionadas ficam perto e diferentes
 * ficam longe.
 */
#[Group('aculta_image')]
class DHashTest extends UnitTestCase {

  /**
   * Imagem de teste com gradiente horizontal e um bloco escuro em um canto. */
  private function gradiente(int $largura, int $altura, bool $inverter = FALSE): \GdImage {
    $img = imagecreatetruecolor($largura, $altura);
    for ($x = 0; $x < $largura; $x++) {
      $v = (int) (255 * $x / max(1, $largura - 1));
      if ($inverter) {
        $v = 255 - $v;
      }
      $cor = imagecolorallocate($img, $v, $v, $v);
      imageline($img, $x, 0, $x, $altura - 1, $cor);
    }
    return $img;
  }

  /**
   * Confirms identical images have zero hash distance.
   */
  public function testImagensIguaisTemDistanciaZero(): void {
    $a = DHash::deGd($this->gradiente(200, 120));
    $b = DHash::deGd($this->gradiente(200, 120));
    $this->assertSame(0, DHash::distancia($a, $b));
  }

  /**
   * Confirms resized images have similar hashes.
   */
  public function testRedimensionadaFicaPerto(): void {
    $grande = DHash::deGd($this->gradiente(400, 240));
    $pequena = DHash::deGd($this->gradiente(160, 96));
    $this->assertLessThanOrEqual(DHash::LIMIAR, DHash::distancia($grande, $pequena));
  }

  /**
   * Confirms different images have distant hashes.
   */
  public function testImagemDiferenteFicaLonge(): void {
    $a = DHash::deGd($this->gradiente(200, 120));
    $b = DHash::deGd($this->gradiente(200, 120, TRUE));
    $this->assertGreaterThan(DHash::LIMIAR, DHash::distancia($a, $b));
  }

  /**
   * Confirms the generated image hash format.
   */
  public function testFormatoDaImpressao(): void {
    $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', DHash::deGd($this->gradiente(50, 50)));
  }

}
