<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_file_uploader\Unit;

use Drupal\aculta_file_uploader\FocalPoint;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifica o recorte centrado no ponto focal.
 */
#[Group('aculta_file_uploader')]
final class FocalPointTest extends UnitTestCase {

  /**
   * Ponto focal no centro gera recorte centrado.
   */
  public function testCenteredFocalPointCentersCrop(): void {
    $rect = FocalPoint::cropRectangle(1000, 1000, 50, 50, 1.0);
    $this->assertSame(['x' => 0, 'y' => 0, 'width' => 1000, 'height' => 1000], $rect);
  }

  /**
   * Ponto focal perto da borda é empurrado para dentro da imagem.
   */
  public function testFocalPointNearEdgeIsClampedInsideImage(): void {
    $rect = FocalPoint::cropRectangle(1000, 500, 0, 0, 1.0);
    $this->assertSame(0, $rect['x']);
    $this->assertSame(0, $rect['y']);
    $this->assertLessThanOrEqual(1000, $rect['x'] + $rect['width']);
  }

  /**
   * Imagem larga cortada para proporção quadrada, centrada no ponto focal.
   */
  public function testWideImageIsCroppedToRatio(): void {
    $rect = FocalPoint::cropRectangle(2000, 1000, 50, 50, 1.0);
    $this->assertSame(1000, $rect['width']);
    $this->assertSame(1000, $rect['height']);
    $this->assertSame(500, $rect['x']);
  }

  /**
   * Coordenadas fora de 0..100 são limitadas antes do cálculo.
   */
  public function testOutOfRangeFocalPointIsClamped(): void {
    $rect = FocalPoint::cropRectangle(1000, 1000, 250, -40, 1.0);
    $this->assertSame(1000, $rect['height']);
    $this->assertSame(0, $rect['y']);
    $this->assertLessThanOrEqual(1000, $rect['x'] + $rect['width']);
  }

  /**
   * Dimensão ou proporção não positiva é recusada.
   */
  public function testNonPositiveInputIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    FocalPoint::cropRectangle(0, 100, 50, 50, 1.0);
  }

}
