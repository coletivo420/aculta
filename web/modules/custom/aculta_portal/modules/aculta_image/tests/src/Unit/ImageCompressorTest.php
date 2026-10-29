<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\Compression\ImageCompressor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Valida os comandos de compressão sem perda.
 *
 * Só usa os binários documentados e argumentos fixos.
 */
#[Group('aculta_image')]
class ImageCompressorTest extends UnitTestCase {

  /**
   * Confirms compressor commands are selected by image type.
   */
  public function testComandosPorTipo(): void {
    $this->assertSame(['jpegoptim', '--strip-all', '--all-progressive', '-q', '/a.jpg'], ImageCompressor::comando('image/jpeg', '/a.jpg'));
    $this->assertSame(['optipng', '-o2', '-quiet', '/a.png'], ImageCompressor::comando('image/png', '/a.png'));
    $this->assertSame(['gifsicle', '-O2', '--batch', '/a.gif'], ImageCompressor::comando('image/gif', '/a.gif'));
  }

  /**
   * Valida o redimensionamento com limite de largura.
   *
   * Só reduz a imagem e usa argumentos fixos, sem shell.
   */
  public function testRedimensionamentoParaLargura(): void {
    $this->assertSame(['mogrify', '-resize', '1200x>', '/a.jpg'], ImageCompressor::redimensionamento('/a.jpg'));
    $this->assertSame(['mogrify', '-resize', '480x>', '/a.png'], ImageCompressor::redimensionamento('/a.png', 480));
    $this->assertSame(1200, ImageCompressor::LARGURA_MAXIMA);
  }

  /**
   * Ignora tipos sem compressão sem perda disponível.
   *
   * WebP e outros tipos não geram comandos neste sistema.
   */
  public function testTiposSemCompressao(): void {
    $this->assertNull(ImageCompressor::comando('image/webp', '/a.webp'));
    $this->assertNull(ImageCompressor::comando('application/pdf', '/a.pdf'));
  }

}
