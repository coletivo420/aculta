<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\UploadPolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Catálogo e política do aculta_image: toda finalidade aponta para um estilo conhecido; a política é a única.
 */
#[Group('aculta_image')]
class ImageCatalogTest extends UnitTestCase {

  /** Toda finalidade resolve para um estilo que existe no projeto. */
  public function testFinalidadesApontamParaEstilosConhecidos(): void {
    $catalogo = new ImageCatalog();
    foreach ($catalogo->purposes() as $finalidade) {
      $estilo = $catalogo->styleFor($finalidade);
      $this->assertContains($estilo, ImageCatalog::STYLES, "Finalidade $finalidade aponta para estilo desconhecido.");
    }
  }

  /** Finalidade inexistente não resolve para estilo. */
  public function testFinalidadeDesconhecidaNaoResolve(): void {
    $this->assertNull((new ImageCatalog())->styleFor('estilo_inventado'));
  }

  /** A política aceita só as extensões e o limite de 10 MB. */
  public function testPoliticaUnicaDeUpload(): void {
    $validadores = (new UploadPolicy())->validators();
    $this->assertSame('png jpg jpeg webp', $validadores['FileExtension']['extensions']);
    $this->assertSame(10485760, $validadores['FileSizeLimit']['fileLimit']);
    $this->assertArrayNotHasKey('FileImageDimensions', $validadores);
  }

}
